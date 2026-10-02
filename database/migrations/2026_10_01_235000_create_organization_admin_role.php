<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add organization_admin role with team_id = null (global blueprint) and zero permissions
        Role::firstOrCreate([
            'name'       => 'organization_admin',
            'guard_name' => 'web',
            'team_id'    => null,
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $role = Role::where('name', 'organization_admin')
            ->where('guard_name', 'web')
            ->whereNull('team_id')
            ->first();

        if ($role) {
            $role->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
