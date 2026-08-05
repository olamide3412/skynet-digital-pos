<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return redirect()->route('superadmin.login');
        }

        if (!$user->is_active) {
            Auth::guard('web')->logout();
            return redirect()->route('superadmin.login')->withErrors([
                'login' => 'Your account has been disabled.',
            ]);
        }

        if (!$user->isSuperAdmin()) {
            abort(403, 'You do not have Super Admin access.');
        }

        return $next($request);
    }
}
