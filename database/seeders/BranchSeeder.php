<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
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

        // ── Ensure Default Organization Exists ───────────────────────────────
        $org = Organization::firstOrCreate(
            ['name' => 'Demo Digital Technologies'],
            [
                'status' => 'active',
            ]
        );

        $enterprisePlan = PricingPlan::where('name', 'Enterprise')->first() ?: PricingPlan::first();
        if ($enterprisePlan && !Subscription::where('organization_id', $org->id)->exists()) {
            Subscription::create([
                'organization_id' => $org->id,
                'pricing_plan_id' => $enterprisePlan->id,
                'status' => 'active',
                'started_at' => Carbon::now()->subMonths(1)->toDateString(),
                'renewal_date' => Carbon::now()->addYears(2)->toDateString(),
            ]);
        }

        // ── Seed Org Admin ────────────────────────────────────────────────────
        Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $orgAdmin = User::updateOrCreate(
            ['email' => 'orgadmin@pos.com'],
            [
                'name' => 'Org Admin',
                'full_name' => 'Demo Organization Admin',
                'username' => 'orgadmin',
                'email' => 'orgadmin@pos.com',
                'password' => Hash::make('admin123'),
                'is_active' => true,
                'is_super_admin' => false,
                'organization_id' => $org->id,
            ]
        );
        setPermissionsTeamId(0);
        $orgAdmin->syncRoles(['organization_admin']);

        // ── Branches to seed ─────────────────────────────────────────────────
        $branchesData = [
            [
                'slug' => 'demo-digital-enterprise',
                'name' => 'Demo Digital Enterprise',
                'address' => 'Plot 4 Victoria Island, Lagos',
                'phone' => '+2348000000000',
                'email' => 'enterprise@skynetpos.com',
            ],
            [
                'slug' => 'felix-enterprise',
                'name' => 'Felix Enterprise',
                'address' => '24 Broad Street, Lagos',
                'phone' => '+2348023456789',
                'email' => 'felix@gmail.com',
            ],
            [
                'slug' => 'hub-enterprise',
                'name' => 'HUB Enterprise',
                'address' => '12 Commercial Road, Asaba, Delta State',
                'phone' => '+2348012345678',
                'email' => 'hub@gmail.com',
            ],
        ];

        foreach ($branchesData as $bData) {
            $branch = Branch::firstOrCreate(
                ['slug' => $bData['slug']],
                [
                    'name' => $bData['name'],
                    'organization_id' => $org->id,
                    'address' => $bData['address'],
                    'phone' => $bData['phone'],
                    'email' => $bData['email'],
                    'is_active' => true,
                ]
            );

            // ── Create branch-scoped roles (team_id = branch_id) ────────────
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
                'canProcessReturn',
                'canManageCustomers',
            ]);

            // ── Initialize branch settings ────────────────────────────────────
            $branch->getSettings();
        }

        // ── Primary Branch Admin for main/skynet-digital-enterprise branch ────
        $mainBranch = Branch::where('slug', 'skynet-digital-enterprise')->first();
        if ($mainBranch) {
            $admin = User::updateOrCreate(
                ['username' => 'admin'],
                [
                    'name' => 'Branch Admin',
                    'full_name' => 'Branch Admin',
                    'username' => 'admin',
                    'email' => 'admin@pos.com',
                    'password' => Hash::make('admin123'),
                    'is_active' => true,
                    'is_super_admin' => false,
                    'organization_id' => $org->id,
                    'branch_id' => $mainBranch->id,
                ]
            );
            setPermissionsTeamId($mainBranch->id);
            $admin->syncRoles(['branch-admin']);

            // ── Sample Cashier ────────────────────────────────────────────────
            $cashier = User::updateOrCreate(
                ['username' => 'cashier1'],
                [
                    'name' => 'Jane Cashier',
                    'full_name' => 'Jane Cashier',
                    'username' => 'cashier1',
                    'email' => 'cashier@skynetpos.com',
                    'password' => Hash::make('cashier123'),
                    'is_active' => true,
                    'is_super_admin' => false,
                    'organization_id' => $org->id,
                    'branch_id' => $mainBranch->id,
                ]
            );
            setPermissionsTeamId($mainBranch->id);
            $cashier->syncRoles(['cashier']);
        }

        $this->command?->info("Default branches and users seeded successfully.");
    }
}

