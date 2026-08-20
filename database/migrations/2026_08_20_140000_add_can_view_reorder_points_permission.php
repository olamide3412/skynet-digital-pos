<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure all core system permissions exist in database
        $permissions = [
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

        foreach ($permissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        // 2. Grant canViewReorderPoints to all branch-admin roles
        $reorderPerm = Permission::findByName('canViewReorderPoints', 'web');
        $adminRoles = Role::where('name', 'branch-admin')->get();
        foreach ($adminRoles as $role) {
            if (!$role->hasPermissionTo('canViewReorderPoints')) {
                $role->givePermissionTo($reorderPerm);
            }
        }

        // 3. Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'canViewReorderPoints')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
