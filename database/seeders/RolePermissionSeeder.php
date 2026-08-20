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
        'canManagePurchases',
        'canManageItems',
        'canManageBarcodes',
        'canManageCustomers',
        'canManageUsers',
        'canEditSettings',
        'canResetPassword',
        'canViewReorderPoints',
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
        // Cashiers can only use POS, view EOD, process returns, manage customers
        $cashierRole->givePermissionTo([
            'canAccessPos',
            'canViewEndOfDay',
            'canApplyDiscount',
            'canProcessReturn',
            'canManageCustomers',
        ]);

        $this->command->info('Permissions and default roles seeded.');
    }
}
