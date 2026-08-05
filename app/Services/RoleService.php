<?php

namespace App\Services;

use App\Models\PosSettings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * RoleService — now backed by Spatie permissions.
 *
 * Permission names (e.g. 'canManageUsers') are stored as Spatie Permission records
 * and seeded in RolePermissionSeeder. The `pos.role:canManageUsers` middleware
 * calls PosRoleMiddleware which calls hasPermissionTo() directly.
 *
 * This service is kept for:
 * 1. Settings-aware checks (e.g. canEditPrice respects is_price_editable setting)
 * 2. Sharing allPermissions() to the Inertia frontend for UI show/hide
 */
class RoleService
{
    protected static function getUser(): ?User
    {
        return Auth::guard('web')->user();
    }

    protected static function can(string $permission): bool
    {
        $user = static::getUser();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return true;

        // Ensure Spatie team context is set for this user's branch
        if ($user->branch_id) {
            setPermissionsTeamId($user->branch_id);
        }

        if ($user->isBranchAdmin()) return true;
        return $user->hasPermissionTo($permission);
    }

    // ── Ability checks ────────────────────────────────────────────────────────

    public static function canEditPrice(): bool
    {
        $settings = PosSettings::current();
        if (!$settings->is_price_editable) return false;
        return static::can('canEditPrice');
    }

    public static function canAccessPos(): bool
    {
        return static::can('canAccessPos');
    }

    public static function canViewEndOfDay(): bool
    {
        return static::canAccessPos() || static::canViewReports();
    }

    public static function canDeleteSale(): bool
    {
        return static::can('canDeleteSale');
    }

    public static function canApplyDiscount(): bool
    {
        return static::can('canApplyDiscount');
    }

    public static function canViewBuyPrice(): bool
    {
        $settings = PosSettings::current();
        if (!$settings->is_show_buy_price) return false;
        return static::can('canViewBuyPrice');
    }

    public static function canManageUsers(): bool
    {
        return static::can('canManageUsers');
    }

    public static function canEditSettings(): bool
    {
        return static::can('canEditSettings');
    }

    public static function canManagePurchases(): bool
    {
        return static::can('canManagePurchases');
    }

    public static function canAdjustStock(): bool
    {
        return static::can('canAdjustStock');
    }

    public static function canProcessReturn(): bool
    {
        return static::can('canProcessReturn');
    }

    public static function canViewReports(): bool
    {
        return static::can('canViewReports');
    }

    public static function canViewAllReports(): bool
    {
        $user = static::getUser();
        if (!$user) return false;
        return $user->isSuperAdmin() || $user->isBranchAdmin();
    }

    public static function canViewSales(): bool
    {
        return static::can('canViewSales') || static::canAccessPos();
    }

    public static function canViewProfitLoss(): bool
    {
        return static::can('canViewProfitLoss') || static::canViewReports();
    }

    public static function canManageDebt(): bool
    {
        return static::can('canManageDebt') || static::canManageCustomers() || static::canViewReports();
    }

    public static function canGiveDebt(): bool
    {
        return static::can('canGiveDebt') || static::canManageCustomers();
    }

    public static function canManageItems(): bool
    {
        return static::can('canManageItems');
    }

    public static function canManageCustomers(): bool
    {
        return static::can('canManageCustomers');
    }

    public static function canTransferStock(): bool
    {
        return static::can('canTransferStock');
    }

    public static function isActiveUser(): bool
    {
        $user = static::getUser();
        return $user ? (bool) $user->is_active : false;
    }

    public static function canResetPassword(): bool
    {
        $user = static::getUser();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return true;

        if ($user->branch_id) {
            setPermissionsTeamId($user->branch_id);
        }

        return $user->hasDirectPermission('canResetPassword');
    }

    /**
     * Return all permission flags for Inertia frontend sharing.
     */
    public static function allPermissions(): array
    {
        $user = static::getUser();

        // Ensure team context for Spatie checks
        if ($user && $user->branch_id) {
            setPermissionsTeamId($user->branch_id);
        }

        return [
            'canAccessPos'       => static::canAccessPos(),
            'canViewSales'       => static::canViewSales(),
            'canViewEndOfDay'    => static::canViewEndOfDay(),
            'canEditPrice'       => static::canEditPrice(),
            'canDeleteSale'      => static::canDeleteSale(),
            'canApplyDiscount'   => static::canApplyDiscount(),
            'canViewBuyPrice'    => static::canViewBuyPrice(),
            'canManageUsers'     => static::canManageUsers(),
            'canEditSettings'    => static::canEditSettings(),
            'canResetPassword'   => static::canResetPassword(),
            'canManagePurchases' => static::canManagePurchases(),
            'canAdjustStock'     => static::canAdjustStock(),
            'canTransferStock'   => static::canTransferStock(),
            'canProcessReturn'   => static::canProcessReturn(),
            'canViewReports'     => static::canViewReports(),
            'canViewAllReports'  => static::canViewAllReports(),
            'canViewProfitLoss'  => static::canViewProfitLoss(),
            'canManageDebt'      => static::canManageDebt(),
            'canGiveDebt'        => static::canGiveDebt(),
            'canManageItems'     => static::canManageItems(),
            'canManageCustomers' => static::canManageCustomers(),
            'isBranchAdmin'      => $user?->isBranchAdmin() ?? false,
            'isSuperAdmin'       => $user?->isSuperAdmin() ?? false,
        ];
    }
}
