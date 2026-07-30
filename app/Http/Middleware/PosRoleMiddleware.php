<?php

namespace App\Http\Middleware;

use App\Services\RoleService;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware to guard a route based on a RoleService capability.
 *
 * Usage in route: ->middleware('pos.role:canManageUsers')
 * Supported abilities (map to RoleService::method names):
 *   canEditPrice, canDeleteSale, canApplyDiscount, canViewBuyPrice,
 *   canManageUsers, canEditSettings, canManagePurchases,
 *   canAdjustStock, canProcessReturn, canViewReports
 */
class PosRoleMiddleware
{
    public function handle(Request $request, Closure $next, string $ability): mixed
    {
        if (! method_exists(RoleService::class, $ability)) {
            abort(500, "Unknown POS role ability: {$ability}");
        }

        if (! RoleService::{$ability}()) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
