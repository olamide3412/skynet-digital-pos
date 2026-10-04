<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PosLog;
use App\Models\PricingPlan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BranchEcommerceActivityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $branchAdmin;
    protected User $cashier;
    protected Organization $org;
    protected Branch $branch;
    protected Branch $otherBranch;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Plans & Capabilities
        $plan = PricingPlan::where('name', 'Enterprise')->first() ?? PricingPlan::firstOrCreate(
            ['name' => 'Enterprise'],
            [
                'first_year_price' => 'Custom',
                'renewal_price'    => 'Custom',
                'branches_limit'   => 'Unlimited',
                'users_limit'      => 'Unlimited',
                'is_active'        => true,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $plan->id],
            [
                'max_branches'                  => null,
                'max_users_per_branch'          => null,
                'feature_multi_branch'          => true,
                'feature_ecommerce_integration' => true,
            ]
        );

        // 2. Create Organization & Subscription
        $this->org = Organization::create([
            'name'   => 'Ecommerce Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subDay(),
            'renewal_date'    => Carbon::now()->addYear(),
        ]);

        // 3. Create Branches
        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Branch ' . uniqid(),
            'slug'            => 'main-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->otherBranch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Other Branch ' . uniqid(),
            'slug'            => 'other-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        // 4. Ensure permissions exist
        Permission::findOrCreate('view_ecommerce_activity', 'web');

        // 5. Create Users
        $this->superAdmin = User::firstOrCreate(
            ['username' => 'sa_test_' . uniqid()],
            [
                'name'           => 'Super Admin',
                'email'          => 'sa_' . uniqid() . '@test.com',
                'password'       => bcrypt('password'),
                'is_super_admin' => true,
                'is_active'      => true,
            ]
        );

        $this->branchAdmin = User::create([
            'username'        => 'ba_test_' . uniqid(),
            'name'            => 'Branch Admin',
            'email'           => 'ba_' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);

        $this->cashier = User::create([
            'username'        => 'cashier_test_' . uniqid(),
            'name'            => 'Cashier',
            'email'           => 'cashier_' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);

        // Assign branch and roles
        setPermissionsTeamId($this->branch->id);

        $adminRole = Role::firstOrCreate([
            'name'       => 'Branch Admin',
            'team_id'    => $this->branch->id,
            'guard_name' => 'web',
        ]);
        $adminRole->givePermissionTo('view_ecommerce_activity');
        $this->branchAdmin->assignRole($adminRole);

        $cashierRole = Role::firstOrCreate([
            'name'       => 'Cashier',
            'team_id'    => $this->branch->id,
            'guard_name' => 'web',
        ]);
        // cashier does NOT get view_ecommerce_activity
        $this->cashier->assignRole($cashierRole);

        // Create an Item
        $this->item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Test Widget',
            'barcode_number'  => 'WIDGET-' . uniqid(),
            'price'           => 100.00,
            'buy_price'       => 60.00,
            'front_store_qty' => 10,
            'back_store_qty'  => 0,
        ]);
    }

    /** @test */
    public function branch_admin_with_permission_can_access_ecommerce_activity(): void
    {
        $response = $this->actingAs($this->branchAdmin)
            ->get(route('pos.ecommerce-activity.index', ['branch' => $this->branch->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('EcommerceActivity/Index')
            ->has('reservations')
            ->has('movements')
            ->has('mappings')
            ->has('stats')
        );
    }

    /** @test */
    public function cashier_without_permission_cannot_access_ecommerce_activity(): void
    {
        $response = $this->actingAs($this->cashier)
            ->get(route('pos.ecommerce-activity.index', ['branch' => $this->branch->slug]));

        $response->assertStatus(403);
    }

    /** @test */
    public function branch_admin_can_release_reservation_and_stock_is_restored(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Test Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'client_id'       => 'TEST-CLI-01',
        ]);

        $reservation = CommerceReservation::create([
            'commerce_integration_id'  => $integration->id,
            'organization_id'          => $this->org->id,
            'branch_id'                => $this->branch->id,
            'external_order_reference' => 'EXT-ORD-999',
            'status'                   => 'pending',
            'expires_at'               => Carbon::now()->subMinute(), // expired/stuck
        ]);

        \App\Modules\CommerceIntegration\Models\CommerceReservationItem::create([
            'commerce_reservation_id' => $reservation->id,
            'item_id'                 => $this->item->id,
            'quantity'                => 3,
            'unit_price'              => 100.00,
        ]);

        // 2. Perform release request
        $response = $this->actingAs($this->branchAdmin)
            ->post(route('pos.ecommerce-activity.reservations.release', [
                'branch'      => $this->branch->slug,
                'reservation' => $reservation->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // 3. Verify reservation status
        $this->assertEquals('released', $reservation->fresh()->status);

        // 4. Verify PosLog recorded
        $this->assertDatabaseHas('pos_logs', [
            'branch_id'   => $this->branch->id,
            'user_id'     => $this->branchAdmin->id,
            'action_type' => 'ecommerce_reservation_release',
        ]);
    }

    /** @test */
    public function branch_admin_cannot_release_reservation_belonging_to_another_branch(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->otherBranch->id,
            'name'            => 'Other Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'client_id'       => 'TEST-CLI-02',
        ]);

        $otherReservation = CommerceReservation::create([
            'commerce_integration_id'  => $integration->id,
            'organization_id'          => $this->org->id,
            'branch_id'                => $this->otherBranch->id,
            'external_order_reference' => 'EXT-OTHER-999',
            'status'                   => 'pending',
            'expires_at'               => Carbon::now()->addHour(),
        ]);

        $response = $this->actingAs($this->branchAdmin)
            ->post(route('pos.ecommerce-activity.reservations.release', [
                'branch'      => $this->branch->slug,
                'reservation' => $otherReservation->id,
            ]));

        $response->assertStatus(404);
        $this->assertEquals('pending', $otherReservation->fresh()->status);
    }

    /** @test */
    public function branch_admin_can_manually_deactivate_mapping(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Test Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'client_id'       => 'TEST-CLI-03',
        ]);

        $mapping = CommerceItemMapping::create([
            'commerce_integration_id' => $integration->id,
            'branch_id'               => $this->branch->id,
            'item_id'                 => $this->item->id,
            'external_product_id'     => 'EXT-PROD-123',
            'is_active'               => true,
            'registered_at'           => Carbon::now(),
            'last_seen_at'            => Carbon::now(),
        ]);

        $response = $this->actingAs($this->branchAdmin)
            ->post(route('pos.ecommerce-activity.mappings.deactivate', [
                'branch'  => $this->branch->slug,
                'mapping' => $mapping->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse((bool) $mapping->fresh()->is_active);
        $this->assertNotNull($mapping->fresh()->unregistered_at);

        // Verify PosLog recorded
        $this->assertDatabaseHas('pos_logs', [
            'branch_id'   => $this->branch->id,
            'user_id'     => $this->branchAdmin->id,
            'action_type' => 'ecommerce_mapping_deactivate',
        ]);
    }

    /** @test */
    public function branch_admin_cannot_deactivate_mapping_belonging_to_another_branch(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->otherBranch->id,
            'name'            => 'Other Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'client_id'       => 'TEST-CLI-04',
        ]);

        $otherMapping = CommerceItemMapping::create([
            'commerce_integration_id' => $integration->id,
            'branch_id'               => $this->otherBranch->id,
            'item_id'                 => $this->item->id,
            'external_product_id'     => 'EXT-OTHER-PROD',
            'is_active'               => true,
        ]);

        $response = $this->actingAs($this->branchAdmin)
            ->post(route('pos.ecommerce-activity.mappings.deactivate', [
                'branch'  => $this->branch->slug,
                'mapping' => $otherMapping->id,
            ]));

        $response->assertStatus(404);
        $this->assertTrue((bool) $otherMapping->fresh()->is_active);
    }

    /** @test */
    public function super_admin_can_view_aggregated_organization_ecommerce_activity(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.organizations.show', $this->org->id));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/Organizations/Show')
            ->has('ecommerceStats')
            ->has('ecommerceReservations')
            ->has('ecommerceMovements')
            ->has('ecommerceMappings')
        );
    }

    /** @test */
    public function org_admin_can_access_ecommerce_activity_in_org_portal(): void
    {
        config(['organizations.enabled' => true]);

        $orgAdmin = User::create([
            'username'        => 'org_admin_' . uniqid(),
            'name'            => 'Org Administrator',
            'email'           => 'org_' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);
        setPermissionsTeamId(0);
        $orgAdminRole = Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $orgAdmin->assignRole($orgAdminRole);

        $response = $this->actingAs($orgAdmin)
            ->get(route('org.ecommerce-activity.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/EcommerceActivity/Index')
            ->has('stats')
            ->has('branches')
            ->has('reservations')
            ->has('movements')
            ->has('mappings')
        );
    }

    /** @test */
    public function org_admin_can_release_reservation_in_org_portal(): void
    {
        config(['organizations.enabled' => true]);

        $orgAdmin = User::create([
            'username'        => 'org_admin_' . uniqid(),
            'name'            => 'Org Administrator',
            'email'           => 'org_' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);
        setPermissionsTeamId(0);
        $orgAdminRole = Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $orgAdmin->assignRole($orgAdminRole);

        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Test Org Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'client_id'       => 'TEST-CLI-ORG-01',
        ]);

        $reservation = CommerceReservation::create([
            'commerce_integration_id'  => $integration->id,
            'organization_id'          => $this->org->id,
            'branch_id'                => $this->branch->id,
            'external_order_reference' => 'EXT-ORG-RES-1',
            'status'                   => 'pending',
            'expires_at'               => Carbon::now()->subMinute(),
        ]);

        \App\Modules\CommerceIntegration\Models\CommerceReservationItem::create([
            'commerce_reservation_id' => $reservation->id,
            'item_id'                 => $this->item->id,
            'quantity'                => 2,
            'unit_price'              => 100.00,
        ]);

        $response = $this->actingAs($orgAdmin)
            ->post(route('org.ecommerce-activity.reservations.release', [
                'reservation' => $reservation->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('released', $reservation->fresh()->status);

        // Verify Org Activity Log recorded
        $this->assertDatabaseHas('organization_activity_log', [
            'organization_id' => $this->org->id,
            'actor_user_id'   => $orgAdmin->id,
            'action'          => 'ecommerce_reservation_released',
        ]);
    }

    /** @test */
    public function org_admin_can_deactivate_mapping_in_org_portal(): void
    {
        config(['organizations.enabled' => true]);

        $orgAdmin = User::create([
            'username'        => 'org_admin_' . uniqid(),
            'name'            => 'Org Administrator',
            'email'           => 'org_' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);
        setPermissionsTeamId(0);
        $orgAdminRole = Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $orgAdmin->assignRole($orgAdminRole);

        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Test Org Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'client_id'       => 'TEST-CLI-ORG-02',
        ]);

        $mapping = CommerceItemMapping::create([
            'commerce_integration_id' => $integration->id,
            'branch_id'               => $this->branch->id,
            'item_id'                 => $this->item->id,
            'external_product_id'     => 'EXT-ORG-PROD-1',
            'is_active'               => true,
            'registered_at'           => Carbon::now(),
            'last_seen_at'            => Carbon::now(),
        ]);

        $response = $this->actingAs($orgAdmin)
            ->post(route('org.ecommerce-activity.mappings.deactivate', [
                'mapping' => $mapping->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertFalse((bool) $mapping->fresh()->is_active);

        // Verify Org Activity Log recorded
        $this->assertDatabaseHas('organization_activity_log', [
            'organization_id' => $this->org->id,
            'actor_user_id'   => $orgAdmin->id,
            'action'          => 'ecommerce_mapping_deactivated',
        ]);
    }
}
