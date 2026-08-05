<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;

/**
 * Reads {branch} route parameter (slug), resolves the Branch model,
 * checks if it's active, and binds it into the app container as 'current_branch'.
 *
 * Must be applied before BranchScopeMiddleware and auth checks on branch routes.
 */
class ResolveBranchMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $slug = $request->route('branch');

        if (!$slug) {
            return $next($request);
        }

        // Resolve branch by slug
        $branch = Branch::where('slug', $slug)->first();

        if (!$branch) {
            return $this->branchNotFound($request);
        }

        // Check if branch is active
        if (!$branch->is_active) {
            return $this->branchUnavailable($request, $branch);
        }

        // Bind to app container so current_branch() helper works everywhere
        app()->instance('current_branch', $branch);

        // Set URL defaults so route() helper automatically includes {branch} parameter
        \Illuminate\Support\Facades\URL::defaults(['branch' => $branch->slug]);

        // Also set on the request for controllers that prefer DI
        $request->attributes->set('current_branch', $branch);

        return $next($request);
    }

    protected function branchNotFound(Request $request): mixed
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Branch not found.'], 404);
        }
        return inertia('Error', [
            'status' => 404,
            'message' => 'Branch not found.',
        ])->toResponse($request)->setStatusCode(404);
    }

    protected function branchUnavailable(Request $request, Branch $branch): mixed
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This branch is currently unavailable.',
                'branch'  => $branch->name,
            ], 503);
        }

        // Render branded "unavailable" Inertia page
        return inertia('BranchUnavailable', [
            'branchName' => $branch->name,
            'message'    => 'This branch is currently unavailable. Please contact support.',
        ])->toResponse($request)->setStatusCode(503);
    }
}
