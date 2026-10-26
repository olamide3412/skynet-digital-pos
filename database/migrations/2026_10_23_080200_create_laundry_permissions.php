<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The list of laundry permissions.
     */
    private const LAUNDRY_PERMISSIONS = [
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.cancel_order',
        'laundry.record_payment',
        'laundry.refund',
        'laundry.release_unpaid',
        'laundry.override_price',
        'laundry.manage_services',
        'laundry.view_reports',
        'laundry.manage_settings',
    ];

    private const CASHIER_LAUNDRY_PERMISSIONS = [
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.record_payment',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $prevLazyLoading = \Illuminate\Database\Eloquent\Model::preventsLazyLoading();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);

        try {
            // 1. Create global permissions
            foreach (self::LAUNDRY_PERMISSIONS as $name) {
                Permission::firstOrCreate([
                    'name'       => $name,
                    'guard_name' => 'web',
                ]);
            }

            // 2. Grant all laundry permissions to all branch-admin roles (global and branch-scoped)
            $adminRoles = Role::where('name', 'branch-admin')
                ->where('guard_name', 'web')
                ->get();

            foreach ($adminRoles as $role) {
                $role->givePermissionTo(self::LAUNDRY_PERMISSIONS);
            }

            // 3. Grant cashier laundry permissions to all cashier roles
            $cashierRoles = Role::where('name', 'cashier')
                ->where('guard_name', 'web')
                ->get();

            foreach ($cashierRoles as $role) {
                $role->givePermissionTo(self::CASHIER_LAUNDRY_PERMISSIONS);
            }
        } finally {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading($prevLazyLoading);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::LAUNDRY_PERMISSIONS as $name) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if ($permission) {
                $permission->delete();
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
