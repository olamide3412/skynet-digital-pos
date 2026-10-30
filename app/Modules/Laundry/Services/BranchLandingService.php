<?php

namespace App\Modules\Laundry\Services;

use App\Models\Branch;
use App\Models\User;

class BranchLandingService
{
    /**
     * Determine available interfaces for a given user in a branch.
     *
     * @return array{can_pos: bool, can_laundry: bool}
     */
    public static function getUserInterfaceAccess(Branch $branch, ?User $user = null): array
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return ['can_pos' => false, 'can_laundry' => false];
        }

        // Ensure Spatie team permissions are scoped to the branch context
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($branch->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        }

        $isFullAdmin = $user->isSuperAdmin()
            || $user->isOrganizationAdmin()
            || $user->isBranchAdmin();

        $canPos = $branch->posEnabled() && ($isFullAdmin || $user->hasPermissionTo('canAccessPos'));
        $canLaundry = $branch->laundryEnabled() && ($isFullAdmin || $user->hasPermissionTo('laundry.access'));

        return [
            'can_pos'     => $canPos,
            'can_laundry' => $canLaundry,
        ];
    }

    /**
     * Resolve the target landing route name for the user in this branch.
     */
    public static function resolveLandingRoute(Branch $branch, ?User $user = null): string
    {
        $access = self::getUserInterfaceAccess($branch, $user);
        $defaultInterface = $branch->defaultInterface(); // 'pos' or 'laundry'

        // 1. If user has access to both interfaces, honor branch default interface
        if ($access['can_pos'] && $access['can_laundry']) {
            return $defaultInterface === 'laundry' ? 'laundry.home' : 'pos.index';
        }

        // 2. If user can only access one interface, land there
        if ($access['can_laundry']) {
            return 'laundry.home';
        }

        if ($access['can_pos']) {
            return 'pos.index';
        }

        // 3. Fallback based on branch module availability (never default to customer directory)
        if ($defaultInterface === 'laundry' && $branch->laundryEnabled()) {
            return 'laundry.home';
        }

        if ($branch->posEnabled()) {
            return 'pos.index';
        }

        if ($branch->laundryEnabled()) {
            return 'laundry.home';
        }

        return 'pos.index';
    }

    /**
     * Generate redirect URL to the appropriate landing interface.
     */
    public static function resolveLandingUrl(Branch $branch, ?User $user = null): string
    {
        $route = self::resolveLandingRoute($branch, $user);
        return route($route, ['branch' => $branch->slug]);
    }
}
