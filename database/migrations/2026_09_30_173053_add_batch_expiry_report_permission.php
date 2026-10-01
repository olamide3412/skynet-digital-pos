<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        // 1. Ensure permissions exist
        $permissions = [
            'canViewExpiryReport',
            'canViewBatchExpiryReport',
        ];

        foreach ($permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        // 2. Assign to all existing branch-admin and manager roles
        $targetRoles = Role::whereIn('name', ['branch-admin', 'manager', 'inventory-manager'])->get();
        foreach ($targetRoles as $role) {
            foreach ($permissions as $perm) {
                if (!$role->hasPermissionTo($perm)) {
                    $role->givePermissionTo($perm);
                }
            }
        }

        // 3. Clear permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $perm = Permission::where('name', 'canViewBatchExpiryReport')->where('guard_name', 'web')->first();
        if ($perm) {
            $perm->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
