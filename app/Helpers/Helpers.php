<?php

use Illuminate\Support\Facades\Log;

if (!function_exists('log_new')) {
    function log_new($log_msg) {
        \Illuminate\Support\Facades\Log::info($log_msg, [
            'user_id' => \Illuminate\Support\Facades\Auth::check() ? \Illuminate\Support\Facades\Auth::id() : null
        ]);
    }
}

if (!function_exists('current_branch')) {
    /**
     * Return the currently-resolved Branch from the request context.
     * Set by ResolveBranchMiddleware when a /{branch:slug}/ route is active.
     * Returns null in Super Admin context or outside branch routes.
     *
     * @return \App\Models\Branch|null
     */
    function current_branch(): ?\App\Models\Branch
    {
        try {
            return app('current_branch');
        } catch (\Throwable) {
            return null;
        }
    }
}
