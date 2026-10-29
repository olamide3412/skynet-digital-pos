<?php

use App\Models\Branch;
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
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $prevLazyLoading = \Illuminate\Database\Eloquent\Model::preventsLazyLoading();
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);

        try {
            // 1. Create the permission globally
            $permission = Permission::firstOrCreate([
                'name'       => 'laundry.manage_discounts',
                'guard_name' => 'web',
            ]);

            // 2. Grant to global blueprint branch-admin role
            $globalAdmin = Role::where('name', 'branch-admin')
                ->where('guard_name', 'web')
                ->whereNull('team_id')
                ->first();
            if ($globalAdmin) {
                $globalAdmin->givePermissionTo($permission);
            }

            // 3. Grant to global blueprint manager role
            $globalManager = Role::where('name', 'manager')
                ->where('guard_name', 'web')
                ->whereNull('team_id')
                ->first();
            if ($globalManager) {
                $globalManager->givePermissionTo($permission);
            }

            // 4. For every existing branch, grant to branch-admin and manager roles
            $branches = Branch::all();
            foreach ($branches as $branch) {
                setPermissionsTeamId($branch->id);

                $branchAdmin = Role::where('name', 'branch-admin')
                    ->where('guard_name', 'web')
                    ->where('team_id', $branch->id)
                    ->first();
                if ($branchAdmin) {
                    $branchAdmin->givePermissionTo($permission);
                }

                $manager = Role::where('name', 'manager')
                    ->where('guard_name', 'web')
                    ->where('team_id', $branch->id)
                    ->first();
                if ($manager) {
                    $manager->givePermissionTo($permission);
                }
            }
        } finally {
            \Illuminate\Database\Eloquent\Model::preventLazyLoading($prevLazyLoading);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', 'laundry.manage_discounts')
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
