<?php

namespace Tests\Feature;

use App\Models\AiCreditUsage;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\AI\Services\AiCreditService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiCreditMeteringTest extends TestCase
{
    use DatabaseTransactions;

    protected PricingPlan $starterPlan;
    protected PricingPlan $silverPlan;
    protected PricingPlan $enterprisePlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        // Ensure plans exist with capabilities
        $this->starterPlan = PricingPlan::firstOrCreate(
            ['name' => 'Starter'],
            [
                'first_year_price' => '₦150,000',
                'renewal_price'    => '₦100,000 / year',
                'branches_limit'   => '1 Branch',
                'users_limit'      => 'Up to 3 Users',
                'ai_credits'       => '25 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 1,
            ]
        );
        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->starterPlan->id],
            [
                'max_branches'        => 1,
                'max_users_per_branch'           => 3,
                'ai_credit_pool_type' => 'account_wide',
                'ai_credits_monthly'  => 25,
            ]
        );

        $this->silverPlan = PricingPlan::firstOrCreate(
            ['name' => 'Silver'],
            [
                'first_year_price' => '₦450,000',
                'renewal_price'    => '₦300,000 / year',
                'branches_limit'   => 'Up to 7 Branches',
                'users_limit'      => 'Up to 20 Users per branch',
                'ai_credits'       => '150 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 3,
            ]
        );
        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->silverPlan->id],
            [
                'max_branches'        => 7,
                'max_users_per_branch'           => 20,
                'ai_credit_pool_type' => 'per_branch',
                'ai_credits_monthly'  => 150,
            ]
        );

        $this->enterprisePlan = PricingPlan::firstOrCreate(
            ['name' => 'Enterprise'],
            [
                'first_year_price' => 'Custom',
                'renewal_price'    => 'Custom',
                'branches_limit'   => 'Unlimited',
                'users_limit'      => 'Unlimited',
                'ai_credits'       => 'Unlimited',
                'is_active'        => true,
                'sort_order'       => 5,
            ]
        );
        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->enterprisePlan->id],
            [
                'max_branches'        => null,
                'max_users_per_branch'           => null,
                'ai_credit_pool_type' => 'account_wide',
                'ai_credits_monthly'  => null,
            ]
        );
    }

    /**
     * Helper to create an organization with an active subscription and branch.
     */
    protected function createOrgWithSubscription(PricingPlan $plan, string $namePrefix = 'Org'): array
    {
        $org = Organization::create([
            'name'   => $namePrefix . ' ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subMonths(2)->toDateString(),
            'renewal_date'    => Carbon::now()->addMonths(10)->toDateString(),
        ]);

        $branch = Branch::create([
            'name'            => 'Branch ' . uniqid(),
            'slug'            => 'branch-' . uniqid(),
            'organization_id' => $org->id,
            'is_active'       => true,
        ]);

        $user = User::factory()->create([
            'name'            => 'Manager ' . uniqid(),
            'organization_id' => $org->id,
            'branch_id'       => $branch->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        setPermissionsTeamId(0);
        $user->assignRole('organization_admin');

        return [$org, $branch, $user];
    }

    public function test_starter_plan_uses_organization_wide_pool(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Starter Org');

        $initialSummary = AiCreditService::getSummary($org, $branch);
        $this->assertEquals('account_wide', $initialSummary['pool_type']);
        $this->assertEquals(25, $initialSummary['monthly_quota']);
        $this->assertEquals(0, $initialSummary['used']);
        $this->assertEquals(25, $initialSummary['remaining']);
        $this->assertFalse($initialSummary['is_exhausted']);

        // Consume 5 credits
        $consumed = AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 5);
        $this->assertTrue($consumed);

        $summaryAfter = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(5, $summaryAfter['used']);
        $this->assertEquals(20, $summaryAfter['remaining']);
        $this->assertFalse($summaryAfter['is_exhausted']);

        // Check via branch model helper
        $branchSummary = $branch->getAiCreditSummary();
        $this->assertEquals(20, $branchSummary['remaining']);
        $this->assertEquals(5, $branchSummary['used']);
    }

    public function test_silver_plan_uses_per_branch_pool_independently(): void
    {
        [$org, $branchA, $userA] = $this->createOrgWithSubscription($this->silverPlan, 'Multi-Branch Org');

        $branchB = Branch::create([
            'name'            => 'Branch B ' . uniqid(),
            'slug'            => 'branch-b-' . uniqid(),
            'organization_id' => $org->id,
            'is_active'       => true,
        ]);

        $userB = User::factory()->create([
            'name'            => 'User B ' . uniqid(),
            'organization_id' => $org->id,
            'branch_id'       => $branchB->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        // Consume 30 credits on Branch A
        $consumedA = AiCreditService::consumeCredit($org, $branchA, $userA, 'chat_query', 30);
        $this->assertTrue($consumedA);

        $summaryA = AiCreditService::getSummary($org, $branchA);
        $summaryB = AiCreditService::getSummary($org, $branchB);

        $this->assertEquals('per_branch', $summaryA['pool_type']);
        $this->assertEquals(30, $summaryA['used']);
        $this->assertEquals(120, $summaryA['remaining']); // 150 - 30

        // Branch B must have 0 used and full 150 remaining
        $this->assertEquals(0, $summaryB['used']);
        $this->assertEquals(150, $summaryB['remaining']);
    }

    public function test_strict_cross_organization_credit_isolation(): void
    {
        [$org1, $branch1, $user1] = $this->createOrgWithSubscription($this->starterPlan, 'Org One');
        [$org2, $branch2, $user2] = $this->createOrgWithSubscription($this->starterPlan, 'Org Two');

        // Org 1 consumes all 25 credits
        AiCreditService::consumeCredit($org1, $branch1, $user1, 'chat_query', 25);

        $summary1 = AiCreditService::getSummary($org1, $branch1);
        $summary2 = AiCreditService::getSummary($org2, $branch2);

        $this->assertEquals(25, $summary1['used']);
        $this->assertEquals(0, $summary1['remaining']);
        $this->assertTrue($summary1['is_exhausted']);
        $this->assertFalse(AiCreditService::hasCreditsRemaining($org1, $branch1, 1));

        // Org 2 must be completely untouched!
        $this->assertEquals(0, $summary2['used']);
        $this->assertEquals(25, $summary2['remaining']);
        $this->assertFalse($summary2['is_exhausted']);
        $this->assertTrue(AiCreditService::hasCreditsRemaining($org2, $branch2, 1));
    }

    public function test_exhausted_credits_blocks_consumption_and_returns_friendly_response(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Exhaustion Org');

        // Exhaust all 25 credits
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 25);

        // Attempting to consume 1 more credit via service returns false
        $consumed = AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 1);
        $this->assertFalse($consumed);

        // Calling ask endpoint should return credits_exhausted response without crashing
        $url = route('org.ai.chat.ask');
        $response = $this->actingAs($user)->postJson($url, [
            'question'  => 'What are our top sales today?',
            'branch_id' => $branch->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'intent' => 'credits_exhausted',
            'structured_data' => [
                'error' => 'credits_exhausted',
            ],
        ]);

        // Verify no extra credit usage record was added
        $totalUsageCount = AiCreditUsage::where('organization_id', $org->id)->count();
        $this->assertEquals(1, $totalUsageCount);
        $this->assertEquals(25, AiCreditUsage::where('organization_id', $org->id)->sum('credits_used'));
    }

    public function test_enterprise_plan_is_unmetered_and_never_blocked(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->enterprisePlan, 'Enterprise Org');

        $summary = AiCreditService::getSummary($org, $branch);
        $this->assertTrue($summary['is_unmetered']);
        $this->assertNull($summary['remaining']);
        $this->assertFalse($summary['is_exhausted']);
        $this->assertTrue(AiCreditService::hasCreditsRemaining($org, $branch, 1000));

        // Consuming credits logs the event, but never exhausts
        $consumed = AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 500);
        $this->assertTrue($consumed);

        $summaryAfter = AiCreditService::getSummary($org, $branch);
        $this->assertTrue($summaryAfter['is_unmetered']);
        $this->assertEquals(500, $summaryAfter['used']);
        $this->assertFalse($summaryAfter['is_exhausted']);
        $this->assertTrue(AiCreditService::hasCreditsRemaining($org, $branch, 1000));
    }

    public function test_superadmin_bypasses_credit_exhaustion_in_chat(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Exhausted Starter Org');

        // Exhaust credits completely
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 25);
        $this->assertTrue(AiCreditService::getSummary($org, $branch)['is_exhausted']);

        // Platform Super Admin
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($superAdmin)->postJson(route('superadmin.ai.chat.ask'), [
            'question'  => 'What are the top sales?',
            'branch_id' => $branch->id,
        ]);

        $response->assertStatus(200);
        $this->assertNotEquals('credits_exhausted', $response->json('intent'));
    }
}
