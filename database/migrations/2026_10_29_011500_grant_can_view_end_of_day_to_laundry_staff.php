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

        $perm = Permission::firstOrCreate([
            'name'       => 'canViewEndOfDay',
            'guard_name' => 'web',
        ]);

        // 1. Give to global blueprint laundry-staff role
        $globalStaff = Role::where('name', 'laundry-staff')
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->first();

        if ($globalStaff && !$globalStaff->hasPermissionTo('canViewEndOfDay')) {
            $globalStaff->givePermissionTo($perm);
        }

        // 2. Give to all branch-scoped laundry-staff roles
        $branches = Branch::all();
        foreach ($branches as $branch) {
            setPermissionsTeamId($branch->id);

            $branchStaff = Role::where('name', 'laundry-staff')
                ->where('guard_name', 'web')
                ->where('team_id', $branch->id)
                ->first();

            if ($branchStaff && !$branchStaff->hasPermissionTo('canViewEndOfDay')) {
                $branchStaff->givePermissionTo($perm);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op or remove permission from laundry-staff roles if needed
    }
};
