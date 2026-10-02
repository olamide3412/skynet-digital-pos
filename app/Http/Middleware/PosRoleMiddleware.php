<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Guard a route based on a Spatie permission name.
 *
 * Usage in route: ->middleware('pos.role:canManageUsers')
 *
 * The ability must match a Spatie permission that exists in the database.
 * Super Admins bypass all permission checks.
 */
class PosRoleMiddleware
{
    public function handle(Request $request, Closure $next, string $ability): mixed
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Super Admin bypasses all permission checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Organization Admin bypasses permission checks for branches in their organization
        $branch = current_branch();
        if ($user->isOrganizationAdmin() && $user->organization_id && $branch && (int) $branch->organization_id === (int) $user->organization_id) {
            return $next($request);
        }

        // Branch Admin has full access to all branch abilities
        if ($user->isBranchAdmin()) {
            return $next($request);
        }

        // Check Spatie permission (branch-scoped via team_id)
        try {
            if ($user->hasPermissionTo($ability) || $user->hasDirectPermission($ability)) {
                return $next($request);
            }
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // Auto-create permission record if missing
            \Spatie\Permission\Models\Permission::findOrCreate($ability, 'web');
        }

        // Fallback to RoleService definition check
        if (method_exists(\App\Services\RoleService::class, $ability) && \App\Services\RoleService::$ability()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Permission denied.'], 403);
        }
        abort(403, 'You do not have permission to access this area.');
    }
}
