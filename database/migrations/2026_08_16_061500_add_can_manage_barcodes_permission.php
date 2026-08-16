<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create permission for web guard
        $perm = Permission::findOrCreate('canManageBarcodes', 'web');

        // 2. Grant to all existing branch-admin roles
        $adminRoles = Role::where('name', 'branch-admin')->get();
        foreach ($adminRoles as $role) {
            if (!$role->hasPermissionTo('canManageBarcodes')) {
                $role->givePermissionTo($perm);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'canManageBarcodes')->where('guard_name', 'web')->delete();
    }
};
