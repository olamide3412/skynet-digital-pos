<?php

namespace App\Services;

use App\Models\User;
use App\Models\UsersRole;
use App\Models\PosSettings;

class RoleService
{
    protected static ?User $user = null;

    protected static function getUser(): User
    {
        return static::$user ?? auth()->user();
    }

    public static function hasRole(string $role): bool
    {
        $user = static::getUser();
        if (!$user) return false;
        if ($user->is_admin) return true;
        return $user->roles()->where('name', $role)->exists();
    }

    public static function getTier(): int
    {
        $user = static::getUser();
        return $user?->acct_tier ?? 0;
    }

    public static function canEditPrice(): bool
    {
        $settings = PosSettings::current();
        if (!$settings->is_price_editable) return false;
        return static::hasRole('PriceEdit') || static::getTier() >= 2;
    }

    public static function canAccessPos(): bool
    {
        // All admins and anyone with the PosAccess role may use the POS screen
        $user = static::getUser();
        if (!$user) return false;
        if ($user->is_admin) return true;
        return $user->roles()->where('name', 'PosAccess')->exists();
    }

    public static function canViewAllReports(): bool
    {
        // Admins and tier-2+ managers can see all users' reports
        return static::getTier() >= 2 || (static::getUser()?->is_admin ?? false);
    }

    public static function canViewEndOfDay(): bool
    {
        // Any POS user or anyone with full report access can see their own EOD
        return static::canAccessPos() || static::canViewReports();
    }

    public static function canDeleteSale(): bool
    {
        return static::getTier() >= 3 || static::hasRole('SaleDelete');
    }

    public static function canApplyDiscount(): bool
    {
        return static::hasRole('DiscountApply') || static::getTier() >= 1;
    }

    public static function canViewBuyPrice(): bool
    {
        $settings = PosSettings::current();
        if (!$settings->is_show_buy_price) return false;
        return static::hasRole('ReportView') || static::getTier() >= 2;
    }

    public static function canManageUsers(): bool
    {
        return static::hasRole('UserManage') || static::getTier() >= 3;
    }

    public static function canEditSettings(): bool
    {
        return static::hasRole('SettingsEdit') || static::getTier() >= 3;
    }

    public static function canManagePurchases(): bool
    {
        return static::hasRole('PurchaseManage') || static::getTier() >= 2;
    }

    public static function canAdjustStock(): bool
    {
        return static::hasRole('StockAdjust') || static::getTier() >= 2;
    }

    public static function canProcessReturn(): bool
    {
        return static::hasRole('SaleReturn') || static::getTier() >= 1;
    }

    public static function canViewReports(): bool
    {
        return static::hasRole('ReportView') || static::getTier() >= 2;
    }

    public static function canManageItems(): bool
    {
        return static::hasRole('StockAdjust') || static::getTier() >= 1;
    }

    public static function canManageCustomers(): bool
    {
        return static::hasRole('CustomerManage') || static::getTier() >= 1;
    }

    public static function isActiveUser(): bool
    {
        $user = static::getUser();
        if (!$user) return false;
        return (bool) $user->is_active;
    }

    /**
     * Return all permission flags as an array for frontend sharing.
     */
    public static function allPermissions(): array
    {
        return [
            'canAccessPos'      => static::canAccessPos(),
            'canViewEndOfDay'   => static::canViewEndOfDay(),
            'canEditPrice'      => static::canEditPrice(),
            'canDeleteSale'     => static::canDeleteSale(),
            'canApplyDiscount'  => static::canApplyDiscount(),
            'canViewBuyPrice'   => static::canViewBuyPrice(),
            'canManageUsers'    => static::canManageUsers(),
            'canEditSettings'   => static::canEditSettings(),
            'canManagePurchases'=> static::canManagePurchases(),
            'canAdjustStock'    => static::canAdjustStock(),
            'canProcessReturn'  => static::canProcessReturn(),
            'canViewReports'    => static::canViewReports(),
            'canViewAllReports' => static::canViewAllReports(),
            'canManageItems'    => static::canManageItems(),
            'canManageCustomers'=> static::canManageCustomers(),
            'isAdmin'           => (bool) (static::getUser()?->is_admin ?? false),
        ];
    }
}
