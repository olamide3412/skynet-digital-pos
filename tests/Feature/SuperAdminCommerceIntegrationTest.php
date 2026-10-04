<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SuperAdminCommerceIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected Organization $enterpriseOrg;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Super Admin User
        $this->superAdmin = User::firstOrCreate(
            ['username' => 'superadmin_test'],
            [
                'name'              => 'Super Administrator',
                'email'             => 'superadmin_test@skynetpos.com',
                'password'          => bcrypt('password'),
                'is_super_admin'    => true,
                'is_active'         => true,
            ]
        );

        // 2. Setup Enterprise Plan with ecommerce capability
        $enterprisePlan = PricingPlan::firstOrCreate(
            ['name' => 'Enterprise'],
            [
                'first_year_price' => 'Custom',
                'renewal_price'    => 'Custom',
                'is_active'        => true,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $enterprisePlan->id],
            [
                'max_branches'                  => null,
                'max_users_per_branch'          => null,
                'feature_multi_branch'          => true,
                'feature_ecommerce_integration' => true,
            ]
        );

        // 3. Create Organization & Subscription
        $this->enterpriseOrg = Organization::create([
            'name'   => 'Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->enterpriseOrg->id,
            'pricing_plan_id' => $enterprisePlan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subDay(),
            'renewal_date'    => Carbon::now()->addYear(),
        ]);

        // 4. Create Branch
        $this->branch = Branch::create([
            'organization_id' => $this->enterpriseOrg->id,
            'name'            => 'Commerce Test Branch',
            'slug'            => 'comm-branch-' . uniqid(),
            'is_active'       => true,
        ]);
    }

    /** @test */
    public function superadmin_can_connect_ecommerce_store_and_receives_token(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.branches.ecommerce.store', $this->branch->slug), [
                'name'                    => 'Downtown Shopify Store',
                'platform'                => 'shopify',
                'reservation_ttl_minutes' => 45,
                'webhook_url'             => 'https://example.com/webhook',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('new_commerce_token');

        $this->assertDatabaseHas('commerce_integrations', [
            'branch_id' => $this->branch->id,
            'name'      => 'Downtown Shopify Store',
            'platform'  => 'shopify',
            'status'    => 'active',
        ]);
    }

    /** @test */
    public function superadmin_can_regenerate_token(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->enterpriseOrg->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Downtown Store',
            'platform'        => 'custom',
            'status'          => 'active',
        ]);

        $integration->createToken('old_token');

        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.branches.ecommerce.regenerate-token', $this->branch->slug));

        $response->assertRedirect();
        $response->assertSessionHas('new_commerce_token');

        // Only 1 token should exist after regeneration
        $this->assertCount(1, $integration->tokens);
    }

    /** @test */
    public function superadmin_can_toggle_integration_status(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->enterpriseOrg->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Downtown Store',
            'platform'        => 'custom',
            'status'          => 'active',
        ]);

        // Toggle to inactive
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.branches.ecommerce.toggle-status', $this->branch->slug));

        $response->assertRedirect();
        $integration->refresh();
        $this->assertEquals('inactive', $integration->status);

        // Toggle back to active
        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.branches.ecommerce.toggle-status', $this->branch->slug));

        $integration->refresh();
        $this->assertEquals('active', $integration->status);
    }

    /** @test */
    public function superadmin_cannot_disconnect_when_active_reservations_exist(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->enterpriseOrg->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Downtown Store',
            'platform'        => 'custom',
            'status'          => 'active',
        ]);

        CommerceReservation::create([
            'commerce_integration_id'  => $integration->id,
            'organization_id'          => $this->enterpriseOrg->id,
            'branch_id'                => $this->branch->id,
            'external_order_reference' => 'ACTIVE-RES-1',
            'status'                   => 'pending',
            'expires_at'               => now()->addMinutes(20),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.branches.ecommerce.destroy', $this->branch->slug));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('commerce_integrations', [
            'id' => $integration->id,
        ]);
    }
}
