<?php

namespace Tests\Feature;

use App\Http\Controllers\Pos\RoleController;
use App\Http\Controllers\SuperAdmin\BranchRoleController;
use App\Http\Controllers\SuperAdmin\BranchUserController;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaundryPermissionsAndRolesTest extends TestCase
{
    use DatabaseTransactions;

    private const EXPECTED_LAUNDRY_PERMISSIONS = [
        'laundry.access',
        'laundry.create_order',
        'laundry.update_status',
        'laundry.record_payment',
        'laundry.cancel_order',
        'laundry.refund',
        'laundry.release_unpaid',
        'laundry.override_price',
        'laundry.manage_services',
        'laundry.manage_discounts',
        'laundry.view_reports',
        'laundry.manage_settings',
    ];

    public function test_laundry_permissions_are_present_in_controllers(): void
    {
        foreach (self::EXPECTED_LAUNDRY_PERMISSIONS as $perm) {
            $this->assertArrayHasKey(
                $perm,
                BranchUserController::ALL_PERMISSIONS,
                "Permission {$perm} must exist in BranchUserController::ALL_PERMISSIONS"
            );
            $this->assertArrayHasKey(
                $perm,
                BranchRoleController::ALL_PERMISSIONS,
                "Permission {$perm} must exist in BranchRoleController::ALL_PERMISSIONS"
            );
        }

        $posRoleController = new \ReflectionClass(RoleController::class);
        $availablePerms = $posRoleController->getConstant('AVAILABLE_PERMISSIONS');
        foreach (self::EXPECTED_LAUNDRY_PERMISSIONS as $perm) {
            $this->assertContains(
                $perm,
                $availablePerms,
                "Permission {$perm} must exist in Pos RoleController::AVAILABLE_PERMISSIONS"
            );
        }
    }

    public function test_branch_ensure_default_roles_provisions_laundry_staff_and_cashier_permissions(): void
    {
        $org = Organization::create([
            'name'                => 'Org Test ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Laundry Test Branch ' . uniqid(),
            'slug'            => 'laundry-branch-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $branch->id]);

        $branch->ensureDefaultRoles();

        setPermissionsTeamId($branch->id);

        // Check laundry-staff role
        $laundryStaffRole = Role::where('name', 'laundry-staff')
            ->where('team_id', $branch->id)
            ->first();
        $this->assertNotNull($laundryStaffRole, 'laundry-staff role should be created for branch');
        $staffPermNames = $laundryStaffRole->permissions->pluck('name')->toArray();
        $this->assertContains('laundry.access', $staffPermNames);
        $this->assertContains('laundry.create_order', $staffPermNames);
        $this->assertContains('laundry.update_status', $staffPermNames);
        $this->assertContains('laundry.record_payment', $staffPermNames);
        $this->assertContains('canManageCustomers', $staffPermNames);

        // Check cashier role
        $cashierRole = Role::where('name', 'cashier')
            ->where('team_id', $branch->id)
            ->first();
        $this->assertNotNull($cashierRole);
        $cashierPermNames = $cashierRole->permissions->pluck('name')->toArray();
        $this->assertContains('laundry.access', $cashierPermNames);
        $this->assertContains('laundry.create_order', $cashierPermNames);
        $this->assertContains('laundry.update_status', $cashierPermNames);
        $this->assertContains('laundry.record_payment', $cashierPermNames);
    }

    public function test_user_can_be_created_with_laundry_permissions_without_validation_errors(): void
    {
        $org = Organization::create([
            'name'                => 'Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Branch ' . uniqid(),
            'slug'            => 'branch-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $branch->id]);
        $branch->ensureDefaultRoles();

        $admin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $username = 'laundryuser_' . uniqid();
        $response = $this->actingAs($admin)
            ->post(route('superadmin.branches.users.store', ['branch' => $branch->slug]), [
                'name'        => 'Laundry Staff User',
                'username'    => $username,
                'email'       => $username . '@example.com',
                'password'    => 'Password123!',
                'role'        => 'laundry-staff',
                'permissions' => [
                    'laundry.access',
                    'laundry.create_order',
                    'laundry.update_status',
                    'laundry.record_payment',
                    'canManageCustomers',
                ],
                'is_active'   => true,
            ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('username', $username)->first();
        $this->assertNotNull($user);

        setPermissionsTeamId($branch->id);
        $this->assertTrue($user->hasRole('laundry-staff'));
        $this->assertTrue($user->hasPermissionTo('laundry.access'));
        $this->assertTrue($user->hasPermissionTo('laundry.create_order'));
    }

    public function test_laundry_discount_permission_allows_and_forbids_management(): void
    {
        $org = Organization::create([
            'name'                => 'Org Test Disc ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Discount Branch ' . uniqid(),
            'slug'            => 'discount-branch-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create([
            'branch_id'       => $branch->id,
            'laundry_enabled' => true,
        ]);
        $branch->ensureDefaultRoles();

        setPermissionsTeamId($branch->id);

        $managerRole = Role::where('name', 'manager')->where('team_id', $branch->id)->first();
        $this->assertNotNull($managerRole);
        $this->assertTrue($managerRole->hasPermissionTo('laundry.manage_discounts'));

        $staffUser = User::create([
            'name'            => 'Staff No Discount',
            'username'        => 'staff_nodisc_' . uniqid(),
            'password'        => bcrypt('secret'),
            'organization_id' => $org->id,
            'branch_id'       => $branch->id,
            'is_active'       => true,
        ]);
        $staffUser->assignRole('laundry-staff');

        // Without laundry.manage_discounts, access is forbidden (403)
        $this->actingAs($staffUser, 'web');
        $forbiddenRes = $this->get(route('laundry.discounts.index', ['branch' => $branch->slug]));
        $forbiddenRes->assertStatus(403);

        // Grant permission explicitly
        $staffUser->givePermissionTo('laundry.manage_discounts');
        $allowedRes = $this->get(route('laundry.discounts.index', ['branch' => $branch->slug]));
        $allowedRes->assertOk();
    }
}
