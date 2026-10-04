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

        // 1. Check Sanctum authenticated tokenable
        $tokenable = $request->user('sanctum');
        if ($tokenable instanceof CommerceIntegration) {
            $integration = $tokenable;
        }

        // 2. If not already resolved, inspect Bearer token or alternative headers
        if (!$integration) {
            $bearerToken = $request->bearerToken() 
                ?: $request->header('X-Commerce-Key')
                ?: $request->header('X-Commerce-Secret')
                ?: $request->header('X-API-Secret')
                ?: $request->header('X-Api-Key');

            if ($bearerToken) {
                $accessToken = PersonalAccessToken::findToken($bearerToken);
                if ($accessToken && $accessToken->tokenable instanceof CommerceIntegration) {
                    $integration = $accessToken->tokenable;
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
}
