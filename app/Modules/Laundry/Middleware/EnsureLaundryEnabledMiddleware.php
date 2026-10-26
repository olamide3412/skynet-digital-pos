<?php

namespace App\Modules\Laundry\Middleware;

use App\Modules\Laundry\Services\BranchLandingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLaundryEnabledMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branch = current_branch();

        if (!$branch || !$branch->laundryEnabled()) {
            if ($branch && $branch->posEnabled()) {
                return redirect()->route('pos.index', ['branch' => $branch->slug])
                    ->with('error', 'The Laundry module is not enabled for this branch.');
            }

            abort(404, 'Laundry module is not available.');
        }

        // Check user permission
        $user = $request->user('web');
        if ($user && !$user->isSuperAdmin() && !$user->isOrganizationAdmin() && !$user->isBranchAdmin() && !$user->hasPermissionTo('laundry.access')) {
            abort(403, 'You do not have permission to access the Laundry interface.');
        }

        return $next($request);
    }
}
