<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Super Admin panel → super admin login
        if ($request->is('superadmin') || $request->is('superadmin/*')) {
            return route('superadmin.login');
        }

        // Branch POS routes → branch-specific login
        // The first URL segment is the branch slug: /{branch}/...
        $slug = $request->segment(1);
        if ($slug && \App\Models\Branch::where('slug', $slug)->exists()) {
            return route('pos.login', ['branch' => $slug]);
        }

        // Storefront / everything else → generic login
        return route('login');
    }
}
