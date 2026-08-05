<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * After the user is authenticated, verify that they belong to the current branch.
 * Super Admins bypass this check (they can access any branch for management).
 * If mismatched, log out and redirect to the branch's login page.
 *
 * ⚡ Crucially: sets setPermissionsTeamId($branch->id) so all downstream
 *    Spatie calls (hasRole, hasPermissionTo) are scoped to this branch's team.
 */
class BranchScopeMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return $next($request);
        }

        $branch = current_branch();

        if (!$branch) {
            return $next($request);
        }

        // ⚠️ Set Spatie team context BEFORE any role/permission check
        setPermissionsTeamId($branch->id);

        // Super Admin may access any branch context
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // User is not active
        if (!$user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()
                ->route('pos.login', ['branch' => $branch->slug])
                ->withErrors(['login' => 'Your account has been disabled.']);
        }

        // User belongs to a different branch
        if ($user->branch_id !== $branch->id) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()
                ->route('pos.login', ['branch' => $branch->slug])
                ->withErrors(['login' => 'You do not have access to this branch.']);
        }

        return $next($request);
    }
}
