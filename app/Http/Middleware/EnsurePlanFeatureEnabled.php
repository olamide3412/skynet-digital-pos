<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePlanFeatureEnabled
{
    /**
     * Handle an incoming request and verify the organization's plan includes the feature.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $feature
     */
    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        $user = auth()->user();

        // Platform super admins are never restricted by plan feature gates
        if ($user?->is_super_admin) {
            return $next($request);
        }

        $branch = current_branch();
        $org = $branch?->organization ?? $user?->organization;

        // Grandfathering: If no organization assigned or no active subscription, feature is unrestricted
        if (!$org || !$org->hasSubscription()) {
            return $next($request);
        }

        if (!$org->hasFeature($feature)) {
            $readableFeature = ucwords(str_replace('_', ' ', $feature));

            if ($request->expectsJson()) {
                return response()->json([
                    'message'               => "The '{$readableFeature}' feature is not included in your organization's current plan. Upgrade your plan to access this feature.",
                    'feature_locked'        => true,
                    'feature_name'          => $feature,
                ], 403);
            }

            return inertia('Error', [
                'status'  => 403,
                'message' => "The '{$readableFeature}' feature is not included in your organization's current plan. Please contact your administrator to upgrade your subscription.",
            ])->toResponse($request)->setStatusCode(403);
        }

        return $next($request);
    }
}
