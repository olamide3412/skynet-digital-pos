<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgActivityLogsTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $orgA;
    protected Organization $orgB;
    protected Branch $branchA1;
    protected Branch $branchA2;
    protected Branch $branchB;
    protected User $orgAdminA;
    protected User $staffA;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);

        // 1. Create Org A with two branches
        $this->orgA = Organization::create([
            'name'   => 'Org Alpha ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branchA1 = Branch::create([
            'name'            => 'Branch Alpha 1 ' . uniqid(),
            'slug'            => 'branch-a1-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
        ]);

        $this->branchA2 = Branch::create([
            'name'            => 'Branch Alpha 2 ' . uniqid(),
            'slug'            => 'branch-a2-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
        ]);

        // 2. Create Org B with one branch
        $this->orgB = Organization::create([
            'name'   => 'Org Beta ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branchB = Branch::create([
            'name'            => 'Branch Beta ' . uniqid(),
            'slug'            => 'branch-b-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->orgB->id,
            'is_active'       => true,
        ]);

        // 3. Create Org Admin A
        $this->orgAdminA = User::create([
            'name'            => 'Org Admin Alpha',
            'username'        => 'org_admin_' . substr(uniqid(), 0, 6),
            'email'           => 'org_admin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        setPermissionsTeamId(0);
        $this->orgAdminA->assignRole('organization_admin');

        // 4. Create Staff in Org A
        $this->staffA = User::create([
            'name'            => 'Staff Alice',
            'username'        => 'staff_alice_' . substr(uniqid(), 0, 6),
            'email'           => 'alice_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        // 5. Create Super Admin
        $this->superAdmin = User::create([
            'name'            => 'Platform Super Admin',
            'username'        => 'superadmin_' . substr(uniqid(), 0, 6),
            'email'           => 'superadmin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
            'is_super_admin'  => true,
        ]);
    }

    public function test_guest_cannot_access_org_activity_logs(): void
    {
        $response = $this->get('/org/activity-logs');
        $response->assertRedirect('/org/login');
    }

    public function test_org_admin_can_view_activity_logs_across_all_branches(): void
    {
        // Create logs for branch A1 and branch A2
        $logA1 = PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'sale',
            'log'         => 'Completed sale #1001 for Branch A1',
            'created_at'  => now(),
        ]);

        $logA2 = PosLog::create([
            'branch_id'   => $this->branchA2->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'inventory',
            'log'         => 'Adjusted inventory stock for Branch A2',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($this->orgAdminA, 'web')
            ->get('/org/activity-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/ActivityLogs/Index')
            ->has('logs.data', 2)
            ->where('branches.0.id', $this->branchA1->id)
            ->where('organization.id', $this->orgA->id)
        );
    }

    public function test_org_admin_can_filter_activity_logs_by_individual_branch(): void
    {
        PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'sale',
            'log'         => 'Sale on branch A1',
            'created_at'  => now(),
        ]);

        PosLog::create([
            'branch_id'   => $this->branchA2->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'sale',
            'log'         => 'Sale on branch A2',
            'created_at'  => now(),
        ]);

        // Filter specifically for Branch A1
        $response = $this->actingAs($this->orgAdminA, 'web')
            ->get("/org/activity-logs?branch_id={$this->branchA1->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.branch_id', $this->branchA1->id)
            ->where('logs.data.0.log', 'Sale on branch A1')
        );
    }

    public function test_superadmin_logs_are_strictly_hidden_from_org_admin(): void
    {
        // 1. Regular staff log on branch A1
        PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'sale',
            'log'         => 'Valid branch staff operation',
            'created_at'  => now(),
        ]);

        // 2. SuperAdmin authored log on branch A1
        PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->superAdmin->id,
            'action_type' => 'system',
            'log'         => 'SuperAdmin adjusted system parameters on branch A1',
            'created_at'  => now(),
        ]);

        // 3. Log referencing Super Admin in text
        PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'system',
            'log'         => 'Action performed by Super Admin proxy',
            'created_at'  => now(),
        ]);

        $response = $this->actingAs($this->orgAdminA, 'web')
            ->get('/org/activity-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.log', 'Valid branch staff operation')
        );
    }

    public function test_activity_logs_do_not_leak_across_organizations(): void
    {
        // Log on Org A
        PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'sale',
            'log'         => 'Org A staff action',
            'created_at'  => now(),
        ]);

        // Log on Org B
        PosLog::create([
            'branch_id'   => $this->branchB->id,
            'user_id'     => null,
            'action_type' => 'sale',
            'log'         => 'Org B confidential action',
            'created_at'  => now(),
        ]);

        // Org Admin A accesses logs
        $response = $this->actingAs($this->orgAdminA, 'web')
            ->get('/org/activity-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.log', 'Org A staff action')
        );

        // Org Admin A maliciously tries filtering by Org B's branch_id
        $tamperedResponse = $this->actingAs($this->orgAdminA, 'web')
            ->get("/org/activity-logs?branch_id={$this->branchB->id}");

        $tamperedResponse->assertStatus(200);
        // Because branchB is not in Org A's branch IDs, controller falls back to Org A's branches only
        $tamperedResponse->assertInertia(fn ($page) => $page
            ->component('Org/ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.log', 'Org A staff action')
        );
    }

    public function test_org_admin_can_export_activity_logs(): void
    {
        PosLog::create([
            'branch_id'   => $this->branchA1->id,
            'user_id'     => $this->staffA->id,
            'action_type' => 'sale',
            'log'         => 'Exportable sale log #1',
            'created_at'  => now(),
        ]);

        // 1. Export Excel (.xlsx)
        $excelResponse = $this->actingAs($this->orgAdminA, 'web')
            ->get('/org/activity-logs/export?format=xlsx');

        $excelResponse->assertStatus(200);
        $this->assertStringContainsString('vnd.openxmlformats-officedocument.spreadsheetml.sheet', $excelResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $excelResponse->headers->get('Content-Disposition'));

        // 2. Export CSV (.csv)
        $csvResponse = $this->actingAs($this->orgAdminA, 'web')
            ->get('/org/activity-logs/export?format=csv');

        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv', $csvResponse->headers->get('Content-Disposition'));
    }
}
