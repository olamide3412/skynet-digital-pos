<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrganizationAdminMiddleware
{
    /**
     * Handle an incoming request for the Organization Portal.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        // 1. Feature Flag: Entire organization layer must be enabled
        if (!config('organizations.enabled')) {
            abort(404, 'Organization portal is currently disabled.');
        }

        $user = Auth::guard('web')->user();

        // 2. Authentication check
        if (!$user) {
            return redirect()->route('org.login');
        }

        // 3. User account status
        if (!$user->is_active) {
            Auth::guard('web')->logout();
            return redirect()->route('org.login')->withErrors([
                'login' => 'Your account has been disabled. Please contact support.',
            ]);
        }

        // 4. Role Authorization: Either Organization Admin or Platform Super Admin
        if (!$user->isOrganizationAdmin() && !$user->isSuperAdmin()) {
            abort(403, 'You do not have access to the Organization Portal.');
        }

        // 5. Organization status check for Organization Admins
        if ($user->isOrganizationAdmin() && !$user->isSuperAdmin()) {
            if (!$user->organization_id || !$user->organization) {
                abort(403, 'No organization is assigned to your account.');
            }

            if ($user->organization->status === 'suspended') {
                abort(403, 'Your organization account has been suspended. Please contact platform support.');
            }
        }

        return $next($request);
    }
}
