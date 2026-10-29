<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * All POS permissions available in the system.
     * These are global (no team_id) so they can be assigned to any branch-scoped role.
     */
    private const PERMISSIONS = [
        'canAccessPos',
        'canViewSales',
        'canDeleteSale',
        'canProcessReturn',
        'canApplyDiscount',
        'canEditPrice',
        'canViewBuyPrice',
        'canViewEndOfDay',
        'canViewReports',
        'canViewProfitLoss',
        'canManageDebt',
        'canGiveDebt',
        'canAdjustStock',
        'canTransferStock',
        'canTransferInterBranchStock',
        'canManagePurchases',
        'canManageItems',
        'canManageBarcodes',
        'canManageCustomers',
        'canManageUsers',
        'canEditSettings',
        'canResetPassword',
        'canViewReorderPoints',
        'view_ecommerce_activity',
        'canViewAccounting',
        'canManageChartOfAccounts',
        'canManageJournalEntries',
        'canCloseFinancialPeriods',
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.record_payment',
        'laundry.cancel_order',
        'laundry.refund',
        'laundry.release_unpaid',
        'laundry.override_price',
        'laundry.manage_services',
        'laundry.manage_discounts',
        'laundry.view_reports',
        'laundry.manage_settings',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions (global, no team)
        foreach (self::PERMISSIONS as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
            );
        }

        // ── Default global roles (team_id = null) ────────────────────────────
        // These are blueprint templates — actual branch-scoped roles are created
        // per-branch when a branch is set up. But we create these as references.
        //
        // Note: branch-admin and cashier roles with team_id ARE created in BranchSeeder.
        // This creates the global (non-team) versions for reference only.

        $branchAdminRole = Role::firstOrCreate(
            ['name' => 'branch-admin', 'guard_name' => 'web', 'team_id' => null],
        );
        $branchAdminRole->syncPermissions(collect(self::PERMISSIONS)->reject(fn($p) => $p === 'canResetPassword')->toArray());

        $cashierRole = Role::firstOrCreate(
            ['name' => 'cashier', 'guard_name' => 'web', 'team_id' => null],
        );
        // Cashiers can use POS, laundry front desk, view EOD, process returns, manage customers
        $cashierRole->givePermissionTo([
            'canAccessPos',
            'canViewEndOfDay',
            'canProcessReturn',
            'canManageCustomers',
            'laundry.access',
            'laundry.create_order',
            'laundry.update_status',
            'laundry.record_payment',
        ]);

        $laundryStaffRole = Role::firstOrCreate(
            ['name' => 'laundry-staff', 'guard_name' => 'web', 'team_id' => null],
        );
        $laundryStaffRole->givePermissionTo([
            'laundry.access',
            'laundry.create_order',
            'laundry.update_status',
            'laundry.record_payment',
            'canManageCustomers',
            'canViewEndOfDay',
        ]);

        $this->command->info('Permissions and default roles seeded.');
    }
}
