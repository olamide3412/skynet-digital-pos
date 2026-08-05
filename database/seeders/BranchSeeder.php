<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Sample branch: Felix Enterprise ──────────────────────────────────
        $branch = Branch::firstOrCreate(
            ['slug' => 'skynet-digital-enterprise'],
            [
                'name' => 'Skynet Digital Enterprise',
                'address' => '12 Commercial Road, Asaba, Delta State',
                'phone' => '+2348012345678',
                'email' => 'digital@skynetpos.com',
                'is_active' => true,
            ]
        );

        // ── Create branch-scoped roles (team_id = branch_id) ────────────────
        setPermissionsTeamId($branch->id);

        $branchAdminRole = Role::firstOrCreate([
            'name' => 'branch-admin',
            'guard_name' => 'web',
            'team_id' => $branch->id,
        ]);
        $branchAdminRole->syncPermissions(Permission::where('name', '!=', 'canResetPassword')->get());

        $cashierRole = Role::firstOrCreate([
            'name' => 'cashier',
            'guard_name' => 'web',
            'team_id' => $branch->id,
        ]);
        $cashierRole->syncPermissions([
            'canAccessPos',
            'canViewEndOfDay',
            'canApplyDiscount',
            'canProcessReturn',
            'canManageCustomers',
        ]);

        // ── Branch Admin user ─────────────────────────────────────────────────
        $admin = User::updateOrCreate(
            ['username' => 'skynet-admin'],
            [
                'name' => 'SD Admin',
                'full_name' => 'Skynet Digital Enterprise Admin',
                'username' => 'skynet-digital-admin',
                'email' => 'admin@skynet.com',
                'password' => Hash::make('admin123'),
                'is_active' => true,
                'is_super_admin' => false,
                'branch_id' => $branch->id,
            ]
        );
        setPermissionsTeamId($branch->id);
        $admin->syncRoles(['branch-admin']);

        // ── Sample Cashier ────────────────────────────────────────────────────
        $cashier = User::updateOrCreate(
            ['username' => 'skynet-cashier1'],
            [
                'name' => 'Jane Cashier',
                'full_name' => 'Jane Cashier',
                'username' => 'skynet-cashier1',
                'email' => 'cashier@skynet.com',
                'password' => Hash::make('cashier123'),
                'is_active' => true,
                'is_super_admin' => false,
                'branch_id' => $branch->id,
            ]
        );
        setPermissionsTeamId($branch->id);
        $cashier->syncRoles(['cashier']);

        // ── Initialize branch settings ────────────────────────────────────────
        $branch->getSettings();

        $this->command->info("Branch '{$branch->name}' seeded with admin + cashier accounts.");
    }
}
