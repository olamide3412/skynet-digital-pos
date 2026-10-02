<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\User;
use App\Modules\AI\Services\ChatQueryTranslationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $orgA;
    protected Organization $orgB;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $adminA;
    protected User $adminB;
    protected User $superAdmin;
    protected Sale $saleA;
    protected Sale $saleB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);

        // 1. Create Organization A & Branch A
        $this->orgA = Organization::create([
            'name'   => 'Tenant Org Alpha ' . uniqid(),
            'status' => 'active',
        ]);
        $this->branchA = Branch::create([
            'name'            => 'Branch Alpha ' . uniqid(),
            'slug'            => 'branch-alpha-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
        ]);

        // 2. Create Organization B & Branch B
        $this->orgB = Organization::create([
            'name'   => 'Tenant Org Beta ' . uniqid(),
            'status' => 'active',
        ]);
        $this->branchB = Branch::create([
            'name'            => 'Branch Beta ' . uniqid(),
            'slug'            => 'branch-beta-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->orgB->id,
            'is_active'       => true,
        ]);

        // 3. Create Org Admin A
        $this->adminA = User::create([
            'name'            => 'Admin Alpha',
            'username'        => 'admin_a_' . substr(uniqid(), 0, 6),
            'email'           => 'admin_a_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        setPermissionsTeamId(0);
        $this->adminA->assignRole('organization_admin');

        // 4. Create Org Admin B
        $this->adminB = User::create([
            'name'            => 'Admin Beta',
            'username'        => 'admin_b_' . substr(uniqid(), 0, 6),
            'email'           => 'admin_b_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgB->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        setPermissionsTeamId(0);
        $this->adminB->assignRole('organization_admin');

        // 5. Retrieve Super Admin
        $this->superAdmin = User::where('is_super_admin', true)->first() ?? User::create([
            'name'           => 'Super Admin',
            'username'       => 'superadmin_' . substr(uniqid(), 0, 6),
            'email'          => 'superadmin_' . uniqid() . '@example.com',
            'password'       => bcrypt('password'),
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        // 6. Create Sales for Branch A and Branch B
        $this->saleA = Sale::create([
            'branch_id'      => $this->branchA->id,
            'user_id'        => $this->adminA->id,
            'receipt_id'     => 'RC-ALPHA-' . uniqid(),
            'final_total'    => 15000.00,
            'amount_cost'    => 10000.00,
            'profit_made'    => 5000.00,
            'payment_method' => 'cash',
        ]);

        $this->saleB = Sale::create([
            'branch_id'      => $this->branchB->id,
            'user_id'        => $this->adminB->id,
            'receipt_id'     => 'RC-BETA-' . uniqid(),
            'final_total'    => 28000.00,
            'amount_cost'    => 18000.00,
            'profit_made'    => 10000.00,
            'payment_method' => 'pos',
        ]);
    }

    /**
     * Test 1: Branch Scope Visibility Isolation
     */
    public function test_branch_scope_visible_to_isolates_organizations()
    {
        $branchesA = Branch::visibleTo($this->adminA)->pluck('id')->all();
        $branchesB = Branch::visibleTo($this->adminB)->pluck('id')->all();
        $branchesSuper = Branch::visibleTo($this->superAdmin)->pluck('id')->all();

        // Org A admin sees only Branch A
        $this->assertContains($this->branchA->id, $branchesA);
        $this->assertNotContains($this->branchB->id, $branchesA);

        // Org B admin sees only Branch B
        $this->assertContains($this->branchB->id, $branchesB);
        $this->assertNotContains($this->branchA->id, $branchesB);

        // Super Admin sees both
        $this->assertContains($this->branchA->id, $branchesSuper);
        $this->assertContains($this->branchB->id, $branchesSuper);
    }

    /**
     * Test 2: Organization Dashboard Isolation
     */
    public function test_organization_dashboard_scopes_to_own_organization()
    {
        $responseA = $this->actingAs($this->adminA)->get('/org/dashboard');
        $responseA->assertStatus(200);
        $responseA->assertInertia(fn ($page) =>
            $page->component('Org/Dashboard')
                ->where('organization.id', $this->orgA->id)
                ->where('organization.name', $this->orgA->name)
        );

        $responseB = $this->actingAs($this->adminB)->get('/org/dashboard');
        $responseB->assertStatus(200);
        $responseB->assertInertia(fn ($page) =>
            $page->component('Org/Dashboard')
                ->where('organization.id', $this->orgB->id)
                ->where('organization.name', $this->orgB->name)
        );
    }

    /**
     * Test 3: Organization Reports Scope & Parameter Tampering Isolation
     */
    public function test_organization_reports_cannot_query_foreign_branch_sales()
    {
        // Admin A visits reports without filter -> sees Branch A sales
        $response = $this->actingAs($this->adminA)->get('/org/reports');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Org/Reports/Index')
                ->where('kpis.total_revenue', 15000)
        );

        // Admin A attempts to pass Org B's branch_id in query parameter
        $tamperedResponse = $this->actingAs($this->adminA)->get("/org/reports?branch_id={$this->branchB->id}");
        $tamperedResponse->assertStatus(200);

        // Controller ignores foreign branch ID and stays isolated to Org A
        $tamperedResponse->assertInertia(fn ($page) =>
            $page->component('Org/Reports/Index')
                ->where('kpis.total_revenue', 15000)
        );
    }

    /**
     * Test 4: AI Receipt Lookup Isolation (Cross-Org Access Denied)
     */
    public function test_ai_receipt_lookup_denies_cross_organization_receipt_lookup()
    {
        // Admin A tries to look up Branch B's receipt
        $result = ChatQueryTranslationService::process(
            $this->saleB->receipt_id,
            null,
            $this->adminA
        );

        $this->assertEquals('receipt_lookup', $result['intent']);
        $this->assertFalse($result['structured_data']['found']);
        $this->assertEquals('cross_org_denied', $result['structured_data']['error']);

        // Admin A looks up Branch A's own receipt
        $resultOwn = ChatQueryTranslationService::process(
            $this->saleA->receipt_id,
            null,
            $this->adminA
        );

        $this->assertEquals('receipt_lookup', $resultOwn['intent']);
        $this->assertTrue($resultOwn['structured_data']['found']);
        $this->assertEquals($this->saleA->receipt_id, $resultOwn['structured_data']['sale']['receipt_no']);

        // Platform Super Admin can look up receipts across all organizations
        $resultSuperA = ChatQueryTranslationService::process($this->saleA->receipt_id, null, $this->superAdmin);
        $resultSuperB = ChatQueryTranslationService::process($this->saleB->receipt_id, null, $this->superAdmin);
        $this->assertTrue($resultSuperA['structured_data']['found']);
        $this->assertTrue($resultSuperB['structured_data']['found']);
    }

    /**
     * Test 5: Suspended Organization Access Denied
     */
    public function test_suspended_organization_admin_is_denied_access()
    {
        $this->orgA->update(['status' => 'suspended']);

        $response = $this->actingAs($this->adminA)->get('/org/dashboard');
        $response->assertStatus(403);
    }

    /**
     * Test 6: Feature Flag Rollback Gating (404 when disabled)
     */
    public function test_when_organizations_disabled_returns_404()
    {
        config(['organizations.enabled' => false]);

        $response = $this->actingAs($this->adminA)->get('/org/dashboard');
        $response->assertStatus(404);
    }
}
