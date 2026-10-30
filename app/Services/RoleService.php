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

    public static function can(string $permission): bool
    {
        $user = static::getUser();
        if (!$user) return false;
        if ($user->isSuperAdmin()) return true;
        if ($user->isOrganizationAdmin()) return true;
        if ($user->isBranchAdmin()) return true;

        // Ensure Spatie team context is set for this user's branch
        if ($user->branch_id) {
            setPermissionsTeamId($user->branch_id);
        }

        try {
            // If user has customized direct permissions, respect direct permission overrides
            if ($user->permissions->isNotEmpty()) {
                return $user->hasDirectPermission($permission);
            }

            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission, 'web');
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── Ability checks ────────────────────────────────────────────────────────

    public static function canEditPrice(): bool
    {
        $settings = PosSettings::current();
        if (!$settings->is_price_editable) return false;
        return static::can('canEditPrice');
    }

    public static function canOverrideLaundryPrice(): bool
    {
        return static::can('laundry.override_price') || static::can('canEditPrice');
    }

    public static function canAccessPos(): bool
    {
        return static::can('canAccessPos');
    }

    public static function canViewEndOfDay(): bool
    {
        $user = static::getUser();
        if ($user && ($user->isSuperAdmin() || $user->isBranchAdmin() || $user->hasRole(['cashier', 'laundry-staff', 'manager']))) {
            return true;
        }

        return static::can('canViewEndOfDay')
            || static::canAccessPos()
            || static::canViewReports()
            || static::can('laundry.view_reports')
            || static::can('laundry.record_payment');
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
        return static::can('canEditSettings') || static::can('laundry.manage_settings');
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

    public static function canManageBarcodes(): bool
    {
        return static::can('canManageBarcodes') || static::canManageItems();
    }

    public static function canManageCustomers(): bool
    {
        return static::can('canManageCustomers');
    }

    public static function canTransferStock(): bool
    {
        return static::can('canTransferStock');
    }

    public static function canTransferInterBranchStock(): bool
    {
        return static::can('canTransferInterBranchStock');
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

        try {
            return $user->hasDirectPermission('canResetPassword');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            \Spatie\Permission\Models\Permission::findOrCreate('canResetPassword', 'web');
            return false;
        } catch (\Throwable $e) {
            return false;
        }
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
            'canTransferInterBranchStock' => static::canTransferInterBranchStock(),
            'canProcessReturn'   => static::canProcessReturn(),
            'canViewReports'     => static::canViewReports(),
            'canViewAllReports'  => static::canViewAllReports(),
            'canViewProfitLoss'  => static::canViewProfitLoss(),
            'canManageDebt'      => static::canManageDebt(),
            'canGiveDebt'        => static::canGiveDebt(),
            'canManageItems'     => static::canManageItems(),
            'canManageBarcodes'  => static::canManageBarcodes(),
            'canManageCustomers' => static::canManageCustomers(),
            'canViewReorderPoints' => static::canViewReorderPoints(),
            'canLogExpense'        => static::canLogExpense(),
            'canManageExpenses'    => static::canManageExpenses(),
            'canViewExpenseReports'=> static::canViewExpenseReports(),
            'canViewExpiryReport'      => static::canViewExpiryReport(),
            'canViewBatchExpiryReport' => static::canViewBatchExpiryReport(),
            'view_ecommerce_activity'  => static::canViewEcommerceActivity(),
            'canViewEcommerceActivity' => static::canViewEcommerceActivity(),
            'canManageTillCashMovements' => static::canManageTillCashMovements(),
            'canViewAccounting'        => static::canViewAccounting(),
            'canManageChartOfAccounts' => static::canManageChartOfAccounts(),
            'canManageJournalEntries'  => static::canManageJournalEntries(),
            'canCloseFinancialPeriods' => static::canCloseFinancialPeriods(),
            'laundry.access'           => static::can('laundry.access'),
            'laundry.create_order'     => static::can('laundry.create_order'),
            'laundry.update_status'    => static::can('laundry.update_status'),
            'laundry.cancel_order'     => static::can('laundry.cancel_order'),
            'laundry.record_payment'   => static::can('laundry.record_payment'),
            'laundry.refund'           => static::can('laundry.refund'),
            'laundry.release_unpaid'   => static::can('laundry.release_unpaid'),
            'laundry.override_price'   => static::can('laundry.override_price'),
            'laundry.manage_services'  => static::can('laundry.manage_services'),
            'laundry.manage_discounts' => static::can('laundry.manage_discounts'),
            'laundry.view_reports'     => static::can('laundry.view_reports'),
            'laundry.manage_settings'  => static::can('laundry.manage_settings'),
            'isBranchAdmin'            => $user?->isBranchAdmin() ?? false,
            'isSuperAdmin'             => $user?->isSuperAdmin() ?? false,
        ];
    }

    public static function canViewAccounting(): bool
    {
        return static::can('view_accounting') || static::can('canViewAccounting');
    }

    public static function canManageChartOfAccounts(): bool
    {
        return static::can('manage_chart_of_accounts') || static::can('canManageChartOfAccounts');
    }

    public static function canManageJournalEntries(): bool
    {
        return static::can('manage_journal_entries') || static::can('canManageJournalEntries');
    }

    public static function canCloseFinancialPeriods(): bool
    {
        return static::can('close_financial_periods') || static::can('canCloseFinancialPeriods');
    }

    public static function canManageTillCashMovements(): bool
    {
        return static::can('canManageTillCashMovements');
    }

    public static function canViewEcommerceActivity(): bool
    {
        return static::can('view_ecommerce_activity') || static::can('canViewEcommerceActivity');
    }

    public static function canViewReorderPoints(): bool
    {
        return static::can('canViewReorderPoints')
            || static::canManageItems()
            || static::canAdjustStock();
    }

    public static function canLogExpense(): bool
    {
        return static::can('canLogExpense');
    }

    public static function canManageExpenses(): bool
    {
        return static::can('canManageExpenses');
    }

    public static function canViewExpenseReports(): bool
    {
        return static::can('canViewExpenseReports') || static::canViewReports();
    }

    public static function canViewExpiryReport(): bool
    {
        return static::can('canViewExpiryReport') || static::canViewReports();
    }

    public static function canViewBatchExpiryReport(): bool
    {
        return static::can('canViewBatchExpiryReport') || static::can('canViewExpiryReport') || static::canViewReports();
    }
}
