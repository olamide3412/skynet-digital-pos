<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubscriptionTrackingTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $superAdmin;
    protected User $orgAdmin;
    protected User $branchAdmin;
    protected PricingPlan $silverPlan;
    protected PricingPlan $goldPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        // Retrieve pricing plans
        $this->silverPlan = PricingPlan::where('name', 'Silver')->first() ?? PricingPlan::firstOrCreate([
            'name'             => 'Silver',
            'first_year_price' => '₦450,000',
            'renewal_price'    => '₦300,000 / year',
            'branches_limit'   => 'Up to 7 Branches',
            'users_limit'      => 'Up to 20 Users per branch',
            'ai_credits'       => '150 AI credits monthly',
            'is_active'        => true,
            'sort_order'       => 3,
        ]);

        $this->goldPlan = PricingPlan::where('name', 'Gold')->first() ?? PricingPlan::firstOrCreate([
            'name'             => 'Gold',
            'first_year_price' => '₦700,000',
            'renewal_price'    => '₦450,000 / year',
            'branches_limit'   => 'Up to 10 Branches',
            'users_limit'      => 'Up to 50 Users per branch',
            'ai_credits'       => '400 AI credits monthly',
            'is_active'        => true,
            'sort_order'       => 4,
        ]);

        // Create organization & branch
        $this->org = Organization::create([
            'name'   => 'Test Client ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Client Branch ' . uniqid(),
            'slug'            => 'client-branch-' . substr(uniqid(), 0, 6),
            'is_active'       => true,
        ]);

        // Super Admin
        $this->superAdmin = User::where('is_super_admin', true)->first() ?? User::create([
            'name'           => 'Super Admin',
            'username'       => 'super_' . substr(uniqid(), 0, 6),
            'email'          => 'super_' . uniqid() . '@example.com',
            'password'       => bcrypt('password'),
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        // Org Admin
        $this->orgAdmin = User::create([
            'name'            => 'Org Admin',
            'username'        => 'orgadmin_' . substr(uniqid(), 0, 6),
            'email'           => 'orgadmin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);
        setPermissionsTeamId(0);
        $this->orgAdmin->assignRole('organization_admin');

        // Branch Admin
        $this->branchAdmin = User::create([
            'name'           => 'Branch Admin',
            'username'       => 'badmin_' . substr(uniqid(), 0, 6),
            'email'          => 'badmin_' . uniqid() . '@example.com',
            'password'       => bcrypt('password'),
            'branch_id'      => $this->branch->id,
            'is_super_admin' => false,
            'is_active'      => true,
        ]);
    }

    /**
     * Test 1: Super Admin can assign a subscription plan to an organization
     */
    public function test_super_admin_can_assign_subscription_plan()
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.organizations.subscription.update', $this->org->id), [
                'pricing_plan_id' => $this->silverPlan->id,
                'started_at'      => Carbon::today()->format('Y-m-d'),
                'renewal_date'    => Carbon::today()->addYear()->format('Y-m-d'),
                'status'          => 'active',
                'notes'           => 'Initial Silver plan assignment for client.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify database state
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'status'          => 'active',
            'notes'           => 'Initial Silver plan assignment for client.',
        ]);

        // Verify history row was created
        $this->assertDatabaseHas('subscription_histories', [
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'status'          => 'active',
        ]);

        // Verify activity log
        $this->assertDatabaseHas('organization_activity_log', [
            'organization_id' => $this->org->id,
            'action'          => 'subscription_updated',
        ]);
    }

    /**
     * Test 2: Plan change updates active row and appends new history row
     */
    public function test_plan_change_updates_subscription_and_appends_history()
    {
        // 1. Initial assignment: Silver
        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.organizations.subscription.update', $this->org->id), [
                'pricing_plan_id' => $this->silverPlan->id,
                'started_at'      => '2026-01-01',
                'renewal_date'    => '2027-01-01',
                'status'          => 'active',
                'notes'           => 'Initial Tier',
            ]);

        $this->assertEquals(1, SubscriptionHistory::where('organization_id', $this->org->id)->count());

        // 2. Upgrade to Gold
        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.organizations.subscription.update', $this->org->id), [
                'pricing_plan_id' => $this->goldPlan->id,
                'started_at'      => '2026-06-01',
                'renewal_date'    => '2027-06-01',
                'status'          => 'active',
                'notes'           => 'Upgraded to Gold plan',
            ]);

        // Active subscription is now Gold
        $subscription = Subscription::where('organization_id', $this->org->id)->first();
        $this->assertEquals($this->goldPlan->id, $subscription->pricing_plan_id);
        $this->assertEquals('2027-06-01', $subscription->renewal_date->format('Y-m-d'));

        // History now has exactly 2 entries (audit ledger)
        $this->assertEquals(2, SubscriptionHistory::where('organization_id', $this->org->id)->count());
    }

    /**
     * Test 3: Non-SuperAdmin cannot update organization subscription
     */
    public function test_non_superadmin_cannot_update_subscription()
    {
        $response = $this->actingAs($this->orgAdmin)
            ->post(route('superadmin.organizations.subscription.update', $this->org->id), [
                'pricing_plan_id' => $this->silverPlan->id,
                'started_at'      => '2026-01-01',
                'renewal_date'    => '2027-01-01',
                'status'          => 'active',
            ]);

        // SuperAdmin routes are restricted by superadmin middleware -> redirect to login or 403
        $this->assertTrue(in_array($response->status(), [302, 403]));

        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $this->org->id,
        ]);
    }

    /**
     * Test 4: Renewal calculations for approaching expiration and past due
     */
    public function test_renewal_calculations_and_computed_status()
    {
        // 1. Plan with renewal in 5 days -> isExpiringSoon is true, status is active
        $subSoon = Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'started_at'      => Carbon::today()->subMonths(11),
            'renewal_date'    => Carbon::today()->addDays(5),
            'status'          => 'active',
        ]);

        $this->assertEquals(5, $subSoon->daysUntilRenewal());
        $this->assertTrue($subSoon->isExpiringSoon());
        $this->assertFalse($subSoon->isPastDue());
        $this->assertEquals('active', $subSoon->computedStatus());

        // 2. Plan with renewal date 3 days ago -> isPastDue is true, computedStatus is grace_period
        $subSoon->update([
            'renewal_date' => Carbon::today()->subDays(3),
        ]);
        $subPastDue = $subSoon->fresh();

        $this->assertEquals(-3, $subPastDue->daysUntilRenewal());
        $this->assertTrue($subPastDue->isPastDue());
        $this->assertEquals('grace_period', $subPastDue->computedStatus());

        // 3. Plan with renewal date 30 days ago -> computedStatus is expired
        $subPastDue->update([
            'renewal_date' => Carbon::today()->subDays(30),
        ]);
        $subExpired = $subPastDue->fresh();

        $this->assertEquals(-30, $subExpired->daysUntilRenewal());
        $this->assertTrue($subExpired->isPastDue());
        $this->assertEquals('expired', $subExpired->computedStatus());
    }

    /**
     * Test 5: Shared Inertia props include read-only current_subscription for Org Admin and Branch
     */
    public function test_inertia_shares_subscription_details_read_only()
    {
        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'started_at'      => Carbon::today()->subMonths(6),
            'renewal_date'    => Carbon::today()->addMonths(6),
            'status'          => 'active',
        ]);

        // Org Admin visiting org dashboard
        $response = $this->actingAs($this->orgAdmin)->get(route('org.dashboard'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Org/Dashboard')
                ->has('current_subscription')
                ->where('current_subscription.plan_name', 'Silver')
                ->where('current_subscription.organization_id', $this->org->id)
        );
    }
}
