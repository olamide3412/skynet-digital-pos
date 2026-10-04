<?php

namespace Tests\Feature;

use App\Models\AiCreditTopup;
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

class AiCreditTopupTest extends TestCase
{
    use DatabaseTransactions;

    protected PricingPlan $silverPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->silverPlan = PricingPlan::firstOrCreate(
            ['name' => 'Silver Test Plan'],
            [
                'first_year_price' => '₦450,000',
                'renewal_price'    => '₦300,000 / year',
                'branches_limit'   => 'Up to 7 Branches',
                'users_limit'      => 'Up to 20 Users per branch',
                'ai_credits'       => '100 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 3,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->silverPlan->id],
            [
                'max_branches'        => 7,
                'max_users_per_branch' => 20,
                'ai_credit_pool_type' => 'per_branch',
                'ai_credits_monthly'  => 100, // 100 per branch each month
            ]
        );
    }

    protected function createOrgWithBranch(): array
    {
        $org = Organization::create([
            'name'                => 'Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'started_at'      => now(),
            'renewal_date'    => now()->addYear(),
            'status'          => 'active',
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Main Branch ' . uniqid(),
            'slug'            => 'main-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $user = User::factory()->create([
            'organization_id' => $org->id,
        ]);

        return [$org, $branch, $user];
    }

    /** @test */
    public function plan_quota_expires_at_month_end_and_does_not_carry_over(): void
    {
        [$org, $branch, $user] = $this->createOrgWithBranch();

        // Simulate usage in the previous month
        Carbon::setTestNow(Carbon::now()->subMonth()->startOfMonth()->addDays(5));
        // Use 40 out of 100 plan credits in previous month
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 40);

        $summaryPreviousMonth = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(40, $summaryPreviousMonth['used']);
        $this->assertEquals(60, $summaryPreviousMonth['plan_remaining']);

        // Advance to current month
        Carbon::setTestNow(Carbon::now()->addMonth()->startOfMonth()->addDays(2));

        $summaryCurrentMonth = AiCreditService::getSummary($org, $branch);
        // Previous month's 60 unused credits did NOT carry over. Fresh 100 credits for current month.
        $this->assertEquals(0, $summaryCurrentMonth['used']);
        $this->assertEquals(100, $summaryCurrentMonth['plan_remaining']);
        $this->assertEquals(100, $summaryCurrentMonth['remaining']);

        Carbon::setTestNow(); // Reset time
    }

    /** @test */
    public function topup_credits_carry_over_across_months_without_conflict(): void
    {
        [$org, $branch, $user] = $this->createOrgWithBranch();

        // In previous month, grant a +50 topup with no expiration
        Carbon::setTestNow(Carbon::now()->subMonth()->startOfMonth()->addDays(5));

        $topup = AiCreditService::grantTopup(
            organization: $org,
            credits: 50,
            branch: $branch,
            notes: 'Test rollover topup'
        );

        $this->assertEquals(50, $topup->credits_remaining);

        // Previous month usage: 20 credits (covered completely by monthly plan quota of 100)
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 20);

        $topup->refresh();
        $this->assertEquals(50, $topup->credits_remaining, 'Topup was not touched because plan quota was available');

        // Move to current month:
        Carbon::setTestNow(Carbon::now()->addMonth()->startOfMonth()->addDays(2));

        $summary = AiCreditService::getSummary($org, $branch);
        // Plan quota is fresh (100), and topup carried over (50) -> total 150
        $this->assertEquals(100, $summary['plan_remaining']);
        $this->assertEquals(50, $summary['topup_remaining']);
        $this->assertEquals(150, $summary['remaining']);

        Carbon::setTestNow();
    }

    /** @test */
    public function consumption_uses_plan_quota_first_and_dips_into_topup_only_when_plan_is_exhausted(): void
    {
        [$org, $branch, $user] = $this->createOrgWithBranch();

        // Grant a 30 credit topup
        $topup = AiCreditService::grantTopup(
            organization: $org,
            credits: 30,
            branch: $branch
        );

        // Consume 95 credits -> Plan quota has 100, so all 95 come from plan quota
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 95);

        $topup->refresh();
        $this->assertEquals(30, $topup->credits_remaining, 'Topup remains untouched while plan quota is available');

        $summary1 = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(5, $summary1['plan_remaining']);
        $this->assertEquals(30, $summary1['topup_remaining']);
        $this->assertEquals(35, $summary1['remaining']);

        // Now consume 15 credits: 5 from remaining plan quota, 10 from topup
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 15);

        $topup->refresh();
        $this->assertEquals(20, $topup->credits_remaining, '10 credits were deducted from topup after plan quota was exhausted');

        $summary2 = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(0, $summary2['plan_remaining']);
        $this->assertEquals(20, $summary2['topup_remaining']);
        $this->assertEquals(20, $summary2['remaining']);
        $this->assertFalse($summary2['is_exhausted']);
    }

    /** @test */
    public function new_month_resets_plan_quota_leaving_remaining_topup_intact(): void
    {
        [$org, $branch, $user] = $this->createOrgWithBranch();

        // Grant 50 credit topup
        $topup = AiCreditService::grantTopup(
            organization: $org,
            credits: 50,
            branch: $branch
        );

        Carbon::setTestNow(Carbon::now()->subMonth()->startOfMonth()->addDays(10));

        // Exhaust all 100 plan credits + 15 topup credits in previous month
        AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 115);

        $topup->refresh();
        $this->assertEquals(35, $topup->credits_remaining);

        // Advance to new month:
        Carbon::setTestNow(Carbon::now()->addMonth()->startOfMonth()->addDays(1));

        $newMonthSummary = AiCreditService::getSummary($org, $branch);
        // In new month: Plan quota is fully refreshed to 100, topup remaining is 35, total is 135!
        $this->assertEquals(100, $newMonthSummary['plan_remaining']);
        $this->assertEquals(35, $newMonthSummary['topup_remaining']);
        $this->assertEquals(135, $newMonthSummary['remaining']);

        Carbon::setTestNow();
    }

    /** @test */
    public function superadmin_can_grant_ai_credit_topup_via_post_route(): void
    {
        [$org, $branch, $user] = $this->createOrgWithBranch();

        $superAdmin = User::factory()->create([
            'email'          => 'superadmin_' . uniqid() . '@example.com',
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('superadmin.organizations.ai-credits.topup', $org->id), [
            'credits'    => 200,
            'branch_id'  => $branch->id,
            'notes'      => 'Purchased 200 credit add-on',
            'type'       => 'addon_purchase',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('ai_credit_topups', [
            'organization_id'   => $org->id,
            'branch_id'         => $branch->id,
            'credits'           => 200,
            'credits_remaining' => 200,
            'type'              => 'addon_purchase',
            'notes'             => 'Purchased 200 credit add-on',
        ]);
    }
}
