<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\AI\Services\AiCreditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminPlanCapabilityTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected PricingPlan $testPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        // Create Super Admin user
        $this->superAdmin = User::firstOrCreate(
            ['username' => 'superadmin_plan_test'],
            [
                'name'           => 'Super Admin Plan Test',
                'email'          => 'superadmin_plan_test@skynetpos.com',
                'password'       => bcrypt('password'),
                'is_super_admin' => true,
                'is_active'      => true,
            ]
        );

        // Create a test plan
        $this->testPlan = PricingPlan::firstOrCreate(
            ['name' => 'Test Bronze Tier'],
            [
                'first_year_price' => '₦200,000',
                'renewal_price'    => '₦120,000 / year',
                'branches_limit'   => '3 Branches',
                'users_limit'      => 'Up to 10 Users',
                'ai_credits'       => '60 monthly credits',
                'is_active'        => true,
                'sort_order'       => 2,
            ]
        );
    }

    public function test_superadmin_can_view_plan_capabilities_page(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('superadmin.plan-capabilities.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SuperAdmin/PlanCapabilities/Index')
            ->has('plans')
        );
    }

    public function test_superadmin_can_update_capabilities_and_ai_credits(): void
    {
        $response = $this->actingAs($this->superAdmin)->put(
            route('superadmin.plan-capabilities.update', $this->testPlan->id),
            [
                'ai_credits_monthly'                 => 175,
                'is_ai_unmetered'                    => false,
                'ai_credit_pool_type'                => 'per_branch',
                'max_branches'                       => 4,
                'is_branches_unlimited'              => false,
                'max_users_per_branch'               => 12,
                'is_users_unlimited'                 => false,
                'feature_ecommerce_integration'      => true,
                'feature_multi_branch'               => true,
                'feature_debt_customer_accounts'     => true,
                'feature_discounts'                  => true,
                'feature_batch_expiry_tracking'      => true,
                'feature_imei_tracking'              => false,
                'feature_barcode_label_printing'     => true,
                'feature_inventory_import'           => true,
                'feature_advanced_staff_permissions' => true,
                'feature_ai_chat_assistant'          => true,
                'feature_ai_reorder_prediction'      => true,
                'feature_broadcast_notifications'    => true,
            ]
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('plan_capabilities', [
            'pricing_plan_id'               => $this->testPlan->id,
            'ai_credits_monthly'            => 175,
            'ai_credit_pool_type'           => 'per_branch',
            'max_branches'                  => 4,
            'max_users_per_branch'          => 12,
            'feature_ecommerce_integration' => 1,
            'feature_imei_tracking'         => 0,
        ]);
    }

    public function test_changes_to_plan_capabilities_immediately_reflect_in_ai_credit_service(): void
    {
        // 1. Set initial quota to 50
        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->testPlan->id],
            [
                'ai_credits_monthly'  => 50,
                'ai_credit_pool_type' => 'per_branch',
                'max_branches'        => 2,
            ]
        );

        $org = Organization::create([
            'name'           => 'Delta Enterprises',
            'contact_name'   => 'Delta Admin',
            'contact_email'  => 'delta@example.com',
            'contact_phone'  => '08012345678',
            'status'         => 'active',
        ]);

        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->testPlan->id,
            'started_at'      => Carbon::today(),
            'renewal_date'    => Carbon::today()->addYear(),
            'status'          => 'active',
        ]);

        $summaryBefore = AiCreditService::getSummary($org);
        $this->assertEquals(50, $summaryBefore['monthly_quota']);

        // 2. Superadmin updates plan quota to 120
        $this->actingAs($this->superAdmin)->put(
            route('superadmin.plan-capabilities.update', $this->testPlan->id),
            [
                'ai_credits_monthly'  => 120,
                'is_ai_unmetered'     => false,
                'ai_credit_pool_type' => 'per_branch',
                'max_branches'        => 5,
                'max_users_per_branch'=> 15,
                'feature_ecommerce_integration' => true,
            ]
        );

        // 3. Subscribed organization immediately reflects 120 credits
        $summaryAfter = AiCreditService::getSummary($org);
        $this->assertEquals(120, $summaryAfter['monthly_quota']);
    }

    public function test_setting_unmetered_sets_null_credits_and_allows_unlimited_usage(): void
    {
        $this->actingAs($this->superAdmin)->put(
            route('superadmin.plan-capabilities.update', $this->testPlan->id),
            [
                'is_ai_unmetered'     => true,
                'ai_credit_pool_type' => 'per_branch',
                'max_branches'        => 10,
                'max_users_per_branch'=> 20,
            ]
        );

        $this->assertDatabaseHas('plan_capabilities', [
            'pricing_plan_id'    => $this->testPlan->id,
            'ai_credits_monthly' => null,
        ]);
    }
}
