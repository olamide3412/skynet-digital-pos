<?php

namespace App\Modules\CommerceIntegration\Middleware;

use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class CommerceApiAuthenticate
{
    /**
     * Handle an incoming request for Commerce Integration API.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $integration = null;
        $accessToken = null;

        // 1. Check Sanctum authenticated tokenable
        $tokenable = $request->user('sanctum');
        if ($tokenable instanceof CommerceIntegration) {
            $integration = $tokenable;
        }

        // 2. If not already resolved, inspect Bearer token or alternative headers
        if (!$integration) {
            $rawToken = $this->extractTokenFromRequest($request);

            if ($rawToken) {
                $rawSecret = CommerceIntegration::extractRawTokenSecret($rawToken);
                $secretHash = hash('sha256', $rawSecret);
                $fullHash = hash('sha256', $rawToken);

                // 2a. Standard Sanctum lookup ({id}|{secret})
                $candidateToken = PersonalAccessToken::findToken($rawToken);
                if ($candidateToken && $candidateToken->tokenable instanceof CommerceIntegration) {
                    $accessToken = $candidateToken;
                    $integration = $candidateToken->tokenable;
                }

                // 2b. Hash-only Sanctum lookup (immune to auto-increment ID changes across DB restores/servers)
                if (!$integration) {
                    $candidateToken = PersonalAccessToken::where('tokenable_type', CommerceIntegration::class)
                        ->whereIn('token', [$secretHash, $fullHash])
                        ->first();

                    if ($candidateToken && $candidateToken->tokenable instanceof CommerceIntegration) {
                        $accessToken = $candidateToken;
                        $integration = $candidateToken->tokenable;
                    }
                }

                // 2c. Direct CommerceIntegration lookup via api_key_hash or webhook_secret (self-heals missing token rows)
                if (!$integration && $rawSecret !== '') {
                    $integration = CommerceIntegration::whereIn('api_key_hash', [$secretHash, $fullHash])
                        ->orWhereIn('webhook_secret', [$rawToken, $rawSecret])
                        ->first();

                    if (!$integration) {
                        $integration = CommerceIntegration::whereNotNull('webhook_secret')
                            ->get()
                            ->first(fn (CommerceIntegration $cand) => hash_equals(
                                CommerceIntegration::extractRawTokenSecret((string) $cand->webhook_secret),
                                $rawSecret
                            ));
                    }

                    if ($integration) {
                        $integration->ensureAccessToken();
                        $accessToken = $integration->tokens()->latest()->first();
                    }
                }
            }
        }

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated commerce integration token.',
                'error_code' => 'COMMERCE_UNAUTHENTICATED',
            ], 401);
        }

        // Ensure token never expires and record last_used_at
        if ($accessToken) {
            $updates = ['last_used_at' => now()];
            if ($accessToken->expires_at !== null) {
                $updates['expires_at'] = null;
            }
            $accessToken->forceFill($updates)->saveQuietly();
        }

        // Optional Client ID verification if provided by external store
        $providedClientId = $request->header('X-Client-ID') 
            ?: $request->header('X-Client-Id') 
            ?: $request->input('client_id');

        if ($providedClientId && $integration->client_id && $integration->client_id !== $providedClientId) {
            return response()->json([
                'success' => false,
                'message' => 'Client ID does not match integration credentials.',
                'error_code' => 'CLIENT_ID_MISMATCH',
            ], 401);
        }

        // 3. Verify integration status
        if (!$integration->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Commerce integration is ' . $integration->status . '.',
                'error_code' => 'COMMERCE_INTEGRATION_INACTIVE',
            ], 403);
        }

        // 4. Verify Plan Capability
        $organization = $integration->organization;
        if ($organization && method_exists($organization, 'hasFeature')) {
            if (!$organization->hasFeature('feature_ecommerce_integration')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ecommerce integration feature is not enabled for your organization plan.',
                    'error_code' => 'PLAN_FEATURE_DISABLED',
                ], 403);
            }
        }

        // Set integration on request attributes for controllers to access
        $request->attributes->set('commerce_integration', $integration);

        return $next($request);
    }

    /**
     * Extract the integration token from Bearer header, server variables (Apache/FastCGI),
     * or custom commerce headers, stripping any accidental "Bearer " prefix or whitespace.
     */
    protected function extractTokenFromRequest(Request $request): ?string
    {
        $raw = $request->bearerToken()
            ?: $request->header('X-Commerce-Key')
            ?: $request->header('X-Commerce-Secret')
            ?: $request->header('X-API-Secret')
            ?: $request->header('X-Api-Key')
            ?: $request->header('X-Access-Token')
            ?: $request->server('HTTP_AUTHORIZATION')
            ?: $request->server('REDIRECT_HTTP_AUTHORIZATION')
            ?: $request->input('api_secret')
            ?: $request->input('api_key');

        if (!$raw && function_exists('apache_request_headers')) {
            $apacheHeaders = array_change_key_case((array) apache_request_headers(), CASE_LOWER);
            $raw = $apacheHeaders['authorization']
                ?? $apacheHeaders['x-commerce-key']
                ?? $apacheHeaders['x-api-key']
                ?? null;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $cleaned = trim(preg_replace('/^Bearer\s+/i', '', trim($raw)), " \t\n\r\0\x0B\"'");

        return $cleaned !== '' ? $cleaned : null;
    }
}
