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
        // 1. Ensure permission exists
        $perm = Permission::findOrCreate('canTransferInterBranchStock', 'web');

        // 2. Grant to all branch-admin, manager, and inventory-manager roles
        $targetRoles = Role::whereIn('name', ['branch-admin', 'manager', 'inventory-manager'])->get();
        foreach ($targetRoles as $role) {
            if (!$role->hasPermissionTo('canTransferInterBranchStock')) {
                $role->givePermissionTo($perm);
            }
        }

        // 3. Also grant to any role that already had canTransferStock
        $stockTransferRoles = Role::whereHas('permissions', function ($q) {
            $q->where('name', 'canTransferStock');
        })->get();

        foreach ($stockTransferRoles as $role) {
            if (!$role->hasPermissionTo('canTransferInterBranchStock')) {
                $role->givePermissionTo($perm);
            }
        }

        // 4. Clear cached permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'canTransferInterBranchStock')->where('guard_name', 'web')->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
