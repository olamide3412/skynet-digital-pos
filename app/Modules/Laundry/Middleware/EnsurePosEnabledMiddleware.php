<?php

namespace App\Modules\Laundry\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePosEnabledMiddleware
{
    /**
     * Handle an incoming request.
     * If POS is turned off for this branch, redirect retail-only routes to Laundry home.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branch = current_branch();

        if ($branch && !$branch->posEnabled()) {
            if ($branch->laundryEnabled()) {
                return redirect()->route('laundry.home', ['branch' => $branch->slug])
                    ->with('info', 'POS interface is currently disabled for this branch. Switched to Laundry interface.');
            }

            abort(403, 'POS interface is disabled for this branch.');
        }

        return $next($request);
    }
}
