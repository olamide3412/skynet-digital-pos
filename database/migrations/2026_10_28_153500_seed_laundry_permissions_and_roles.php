<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
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

    private const LAUNDRY_STAFF_PERMISSIONS = [
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.record_payment',
        'canManageCustomers',
    ];

    private const CASHIER_LAUNDRY_PERMISSIONS = [
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.record_payment',
    ];

    private const MANAGER_LAUNDRY_PERMISSIONS = [
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.record_payment',
        'laundry.cancel_order',
        'laundry.refund',
        'laundry.release_unpaid',
        'laundry.manage_services',
        'laundry.view_reports',
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
            // 1. Ensure all 11 laundry permissions exist
            foreach (self::LAUNDRY_PERMISSIONS as $name) {
                Permission::firstOrCreate([
                    'name'       => $name,
                    'guard_name' => 'web',
                ]);
            }

            // 2. Ensure global blueprint roles exist (team_id = null)
            $globalStaff = Role::firstOrCreate([
                'name'       => 'laundry-staff',
                'guard_name' => 'web',
                'team_id'    => null,
            ]);
            $staffPermModels = Permission::where('guard_name', 'web')
                ->whereIn('name', self::LAUNDRY_STAFF_PERMISSIONS)
                ->get();
            $globalStaff->givePermissionTo($staffPermModels);

            $globalCashier = Role::firstOrCreate([
                'name'       => 'cashier',
                'guard_name' => 'web',
                'team_id'    => null,
            ]);
            $cashierPermModels = Permission::where('guard_name', 'web')
                ->whereIn('name', self::CASHIER_LAUNDRY_PERMISSIONS)
                ->get();
            $globalCashier->givePermissionTo($cashierPermModels);

            // 3. For every branch, ensure default roles and sync appropriate permissions
            $branches = Branch::all();
            foreach ($branches as $branch) {
                setPermissionsTeamId($branch->id);
                $branch->ensureDefaultRoles();

                // Ensure branch-admin has all laundry permissions
                $branchAdmin = Role::where('name', 'branch-admin')
                    ->where('guard_name', 'web')
                    ->where('team_id', $branch->id)
                    ->first();
                if ($branchAdmin) {
                    $allLaundry = Permission::where('guard_name', 'web')
                        ->whereIn('name', self::LAUNDRY_PERMISSIONS)
                        ->get();
                    $branchAdmin->givePermissionTo($allLaundry);
                }

                // Ensure cashier has laundry cashier permissions
                $branchCashier = Role::where('name', 'cashier')
                    ->where('guard_name', 'web')
                    ->where('team_id', $branch->id)
                    ->first();
                if ($branchCashier) {
                    $branchCashier->givePermissionTo($cashierPermModels);
                }

                // Ensure manager has laundry manager permissions
                $branchManager = Role::where('name', 'manager')
                    ->where('guard_name', 'web')
                    ->where('team_id', $branch->id)
                    ->first();
                if ($branchManager) {
                    $managerLaundry = Permission::where('guard_name', 'web')
                        ->whereIn('name', self::MANAGER_LAUNDRY_PERMISSIONS)
                        ->get();
                    $branchManager->givePermissionTo($managerLaundry);
                }
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
        // No destructive reverse needed for production safety
    }
};
