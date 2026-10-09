<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Subscription\SubscriptionBillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgSubscriptionPaymentTest extends TestCase
{
    use DatabaseTransactions;

    protected PricingPlan $silverPlan;
    protected PricingPlan $goldPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'organizations.enabled'           => true,
            'services.paystack.secret_key'    => 'sk_test_12345',
            'services.flutterwave.secret_key' => 'FLWSECK_TEST-12345',
        ]);

        SystemSetting::set('payment_ps_enabled', '1');
        SystemSetting::set('payment_flw_enabled', '1');

        $this->silverPlan = PricingPlan::firstOrCreate(
            ['name' => 'Silver Test Tier'],
            [
                'first_year_price' => '₦450,000',
                'renewal_price'    => '₦300,000 / year',
                'branches_limit'   => 'Up to 5 Branches',
                'users_limit'      => 'Up to 15 Users per branch',
                'ai_credits'       => '100 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 2,
            ]
        );

        $this->goldPlan = PricingPlan::firstOrCreate(
            ['name' => 'Gold Test Tier'],
            [
                'first_year_price' => '₦850,000',
                'renewal_price'    => '₦600,000 / year',
                'branches_limit'   => 'Up to 15 Branches',
                'users_limit'      => 'Up to 50 Users per branch',
                'ai_credits'       => '500 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 3,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->silverPlan->id],
            [
                'max_branches'         => 5,
                'max_users_per_branch' => 15,
                'ai_credit_pool_type'  => 'per_branch',
                'ai_credits_monthly'   => 100,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->goldPlan->id],
            [
                'max_branches'         => 15,
                'max_users_per_branch' => 50,
                'ai_credit_pool_type'  => 'per_branch',
                'ai_credits_monthly'   => 500,
            ]
        );
    }

    protected function createOrgWithAdmin(): array
    {
        $org = Organization::create([
            'name'                => 'Sub Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $subscription = Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'started_at'      => now()->startOfMonth(),
            'renewal_date'    => now()->addMonths(6),
            'status'          => 'active',
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Branch ' . uniqid(),
            'slug'            => 'branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $admin = User::factory()->create([
            'name'            => 'Org Admin ' . uniqid(),
            'email'           => 'org_admin_' . uniqid() . '@example.com',
            'organization_id' => $org->id,
            'is_active'       => true,
        ]);

        setPermissionsTeamId(0);
        Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $admin->assignRole('organization_admin');

        return [$org, $subscription, $admin];
    }

    public function test_price_calculation_for_renewal_and_upgrade(): void
    {
        $billingService = app(SubscriptionBillingService::class);

        [$org, $subscription, $admin] = $this->createOrgWithAdmin();

        // Renewal price should match current plan's renewal_price
        $renewalCalc = $billingService->calculatePrice($org, 'renewal');
        $this->assertEquals(300000, $renewalCalc['amount']);
        $this->assertEquals($this->silverPlan->id, $renewalCalc['plan']->id);

        // Upgrade price should match target plan's first_year_price
        $upgradeCalc = $billingService->calculatePrice($org, 'upgrade', $this->goldPlan->id);
        $this->assertEquals(850000, $upgradeCalc['amount']);
        $this->assertEquals($this->goldPlan->id, $upgradeCalc['plan']->id);
    }

    public function test_org_admin_can_access_subscription_page(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();

        $response = $this->actingAs($admin)->get('/org/subscription');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/Subscription/Index')
            ->has('organization')
            ->has('subscription')
            ->has('pricingPlans')
            ->has('usageStats')
            ->has('gateways')
        );
    }

    public function test_checkout_renewal_creates_pending_payment_and_returns_url(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data'   => [
                    'authorization_url' => 'https://checkout.paystack.com/test-renewal-ref',
                    'access_code'       => 'test_code',
                    'reference'         => 'sub_test_renewal',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson('/org/subscription/checkout', [
            'type'    => 'renewal',
            'gateway' => 'paystack',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'checkout_url' => 'https://checkout.paystack.com/test-renewal-ref',
        ]);

        $this->assertDatabaseHas('subscription_payments', [
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'type'            => 'renewal',
            'amount_paid'     => 300000,
            'gateway'         => 'paystack',
            'status'          => 'pending',
        ]);
    }

    public function test_checkout_upgrade_creates_pending_payment(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();

        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status' => 'success',
                'data'   => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/test-upgrade-ref',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->postJson('/org/subscription/checkout', [
            'type'            => 'upgrade',
            'pricing_plan_id' => $this->goldPlan->id,
            'gateway'         => 'flutterwave',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'checkout_url' => 'https://checkout.flutterwave.com/v3/hosted/pay/test-upgrade-ref',
        ]);

        $this->assertDatabaseHas('subscription_payments', [
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->goldPlan->id,
            'type'            => 'upgrade',
            'amount_paid'     => 850000,
            'gateway'         => 'flutterwave',
            'status'          => 'pending',
        ]);
    }

    public function test_confirm_renewal_payment_extends_renewal_date_idempotently(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();
        $billingService = app(SubscriptionBillingService::class);

        $initialRenewalDate = Carbon::parse($subscription->renewal_date)->copy();

        $payment = $billingService->createPendingPayment(
            org: $org,
            plan: $this->silverPlan,
            type: 'renewal',
            amount: 300000,
            gateway: 'paystack',
            reference: 'sub_renew_' . uniqid(),
            user: $admin
        );

        $this->assertEquals('pending', $payment->status);

        // Confirm payment
        $confirmed = $billingService->confirmPayment($payment->gateway_reference, 'trn_12345', ['test' => true]);
        $this->assertNotNull($confirmed);
        $this->assertInstanceOf(SubscriptionPayment::class, $confirmed);

        $payment->refresh();
        $this->assertEquals('successful', $payment->status);

        $subscription->refresh();
        $expectedRenewalDate = $initialRenewalDate->copy()->addMonths(12);
        $this->assertEquals($expectedRenewalDate->toDateString(), Carbon::parse($subscription->renewal_date)->toDateString());

        // Check subscription history recorded
        $this->assertDatabaseHas('subscription_histories', [
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'status'          => 'active',
        ]);

        // Replay confirmation (idempotency check)
        $replayResult = $billingService->confirmPayment($payment->gateway_reference, 'trn_12345', ['test' => true]);
        $this->assertNotNull($replayResult);

        // Date should NOT be extended again
        $subscription->refresh();
        $this->assertEquals($expectedRenewalDate->toDateString(), Carbon::parse($subscription->renewal_date)->toDateString());
    }

    public function test_confirm_upgrade_payment_switches_plan(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();
        $billingService = app(SubscriptionBillingService::class);

        $payment = $billingService->createPendingPayment(
            org: $org,
            plan: $this->goldPlan,
            type: 'upgrade',
            amount: 850000,
            gateway: 'paystack',
            reference: 'sub_upg_' . uniqid(),
            user: $admin
        );

        $confirmed = $billingService->confirmPayment($payment->gateway_reference, 'trn_gold_999');
        $this->assertNotNull($confirmed);

        $subscription->refresh();
        $this->assertEquals($this->goldPlan->id, $subscription->pricing_plan_id);

        $this->assertDatabaseHas('subscription_histories', [
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->goldPlan->id,
            'status'          => 'active',
        ]);
    }

    public function test_callback_verifies_payment_and_redirects(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();
        $billingService = app(SubscriptionBillingService::class);

        $ref = 'sub_call_' . uniqid();
        $payment = $billingService->createPendingPayment(
            org: $org,
            plan: $this->silverPlan,
            type: 'renewal',
            amount: 300000,
            gateway: 'paystack',
            reference: $ref,
            user: $admin
        );

        Http::fake([
            'https://api.paystack.co/transaction/verify/' . $ref => Http::response([
                'status' => true,
                'data'   => [
                    'status'    => 'success',
                    'id'        => 98765432,
                    'reference' => $ref,
                    'amount'    => 30000000, // kobo
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->get('/org/subscription/callback?reference=' . $ref);
        $response->assertRedirect('/org/subscription');

        $payment->refresh();
        $this->assertEquals('successful', $payment->status);
    }

    public function test_duplicate_webhook_does_not_double_extend_or_duplicate_records(): void
    {
        [$org, $subscription, $admin] = $this->createOrgWithAdmin();
        $billingService = app(SubscriptionBillingService::class);

        $ref = 'sub_dup_' . uniqid();
        $payment = $billingService->createPendingPayment(
            org: $org,
            plan: $this->silverPlan,
            type: 'renewal',
            amount: 300000,
            gateway: 'paystack',
            reference: $ref,
            user: $admin
        );

        $initialRenewalDate = Carbon::parse($subscription->renewal_date)->copy();

        $secret = 'sk_test_12345';
        $payload = [
            'event' => 'charge.success',
            'data'  => [
                'id'        => 778899,
                'reference' => $ref,
                'amount'    => 30000000,
                'currency'  => 'NGN',
                'status'    => 'success',
                'metadata'  => [
                    'payment_type' => 'plan_subscription',
                ],
            ],
        ];

        $content = json_encode($payload);
        $signature = hash_hmac('sha512', $content, $secret);

        // First Webhook delivery
        $response1 = $this->call(
            'POST',
            '/webhooks/payments/paystack',
            [],
            [],
            [],
            [
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
                'CONTENT_TYPE'              => 'application/json',
            ],
            $content
        );

        $response1->assertStatus(200);
        $response1->assertJson(['success' => true, 'action' => 'subscription_confirmed']);

        $subscription->refresh();
        $expectedDate = $initialRenewalDate->copy()->addMonths(12);
        $this->assertEquals($expectedDate->toDateString(), Carbon::parse($subscription->renewal_date)->toDateString());

        $historyCount = \App\Models\SubscriptionHistory::where('organization_id', $org->id)->count();

        // Second Webhook delivery (gateway retry or duplicate event)
        $response2 = $this->call(
            'POST',
            '/webhooks/payments/paystack',
            [],
            [],
            [],
            [
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
                'CONTENT_TYPE'              => 'application/json',
            ],
            $content
        );

        $response2->assertStatus(200);
        $response2->assertJson(['success' => true, 'action' => 'subscription_already_confirmed']);

        // Assert subscription was NOT extended a second time
        $subscription->refresh();
        $this->assertEquals($expectedDate->toDateString(), Carbon::parse($subscription->renewal_date)->toDateString());

        // Assert no duplicate history row was inserted
        $this->assertEquals($historyCount, \App\Models\SubscriptionHistory::where('organization_id', $org->id)->count());
    }
}
