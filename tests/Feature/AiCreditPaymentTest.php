<?php

namespace Tests\Feature;

use App\Models\AiCreditPack;
use App\Models\AiCreditTopup;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\AI\Services\AiCreditService;
use App\Services\Payment\PaymentConfigService;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCreditPaymentTest extends TestCase
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
                'max_branches'         => 7,
                'max_users_per_branch' => 20,
                'ai_credit_pool_type'  => 'per_branch',
                'ai_credits_monthly'   => 100,
            ]
        );
    }

    protected function createOrgWithAdmin(): array
    {
        $org = Organization::create([
            'name'                => 'Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->silverPlan->id,
            'started_at'      => now()->startOfMonth(),
            'renewal_date'    => now()->addYear(),
            'status'          => 'active',
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Main Branch ' . uniqid(),
            'slug'            => 'branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $admin = User::factory()->create([
            'name'            => 'Org Admin ' . uniqid(),
            'email'           => 'admin_' . uniqid() . '@example.com',
            'organization_id' => $org->id,
            'is_active'       => true,
        ]);
        setPermissionsTeamId(0);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $admin->assignRole('organization_admin');

        return [$org, $branch, $admin];
    }

    public function test_credit_packs_exist_and_can_be_queried(): void
    {
        $pack = AiCreditPack::first();
        $this->assertNotNull($pack);
        $this->assertGreaterThan(0, $pack->credits);
        $this->assertGreaterThan(0, $pack->price);

        $activePacks = AiCreditPack::active()->get();
        $this->assertNotEmpty($activePacks);
    }

    public function test_superadmin_can_save_payment_settings(): void
    {
        PaymentConfigService::saveSettings([
            'is_sandbox'             => true,
            'flutterwave_enabled'    => true,
            'flutterwave_public_key' => 'FLWPUBK_TEST_123',
            'flutterwave_secret_key' => 'FLWSECK_TEST_456',
            'paystack_enabled'       => true,
            'paystack_public_key'    => 'pk_test_789',
            'paystack_secret_key'    => 'sk_test_abc',
        ]);

        $settings = PaymentConfigService::getSettings();
        $this->assertTrue($settings['is_sandbox']);
        $this->assertTrue($settings['flutterwave_enabled']);
        $this->assertTrue($settings['paystack_enabled']);
        $this->assertEquals('FLWPUBK_TEST_123', $settings['flutterwave_public_key']);
        $this->assertEquals('pk_test_789', $settings['paystack_public_key']);

        $this->assertEquals('FLWSECK_TEST_456', PaymentConfigService::getSecretKey('flutterwave'));
        $this->assertEquals('sk_test_abc', PaymentConfigService::getSecretKey('paystack'));
    }

    public function test_organization_admin_can_view_credits_page(): void
    {
        [$org, $branch, $admin] = $this->createOrgWithAdmin();

        $response = $this->actingAs($admin)
            ->get(route('org.credits.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/Credits/Index')
            ->has('summary')
            ->has('packs')
            ->has('history')
        );
    }

    public function test_initiate_checkout_creates_pending_topup_in_ledger(): void
    {
        [$org, $branch, $admin] = $this->createOrgWithAdmin();
        $pack = AiCreditPack::active()->first();

        // Configure mock key
        PaymentConfigService::saveSettings([
            'flutterwave_enabled'    => true,
            'flutterwave_secret_key' => 'FLWSECK_MOCK',
        ]);

        Http::fake([
            'https://api.flutterwave.com/v3/payments' => Http::response([
                'status'  => 'success',
                'message' => 'Hosted Link',
                'data'    => [
                    'link' => 'https://checkout.flutterwave.com/v3/hosted/pay/mock_link',
                ],
            ], 200),
        ]);

        $result = PaymentService::initiateAiCreditPackCheckout(
            $org,
            $pack,
            'flutterwave',
            $branch,
            $admin,
            'http://localhost/org/credits/callback'
        );

        $this->assertNotEmpty($result['checkout_url']);
        $this->assertNotEmpty($result['reference']);
        $this->assertInstanceOf(AiCreditTopup::class, $result['topup']);

        // Verify topup record in database is 'pending'
        $this->assertDatabaseHas('ai_credit_topups', [
            'id'                => $result['topup']->id,
            'organization_id'   => $org->id,
            'branch_id'         => $branch->id,
            'credit_pack_id'    => $pack->id,
            'credits'           => $pack->credits,
            'credits_remaining' => $pack->credits,
            'gateway'           => 'flutterwave',
            'gateway_reference' => $result['reference'],
            'status'            => 'pending',
            'type'              => 'addon_purchase',
        ]);

        // Verify pending top-up is NOT counted in active balance
        $summary = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(0, $summary['topup_remaining']);
    }

    public function test_webhook_confirms_purchase_idempotently(): void
    {
        [$org, $branch, $admin] = $this->createOrgWithAdmin();
        $pack = AiCreditPack::active()->first();

        $reference = 'aic_test_' . uniqid();
        $topup = AiCreditService::createPendingPurchase(
            $org,
            $pack,
            'flutterwave',
            $reference,
            $branch,
            $admin
        );

        $this->assertEquals('pending', $topup->status);

        // First confirmation
        $confirmed = AiCreditService::confirmPurchase($reference, 'FLW_TX_12345');
        $this->assertNotNull($confirmed);
        $this->assertEquals('successful', $confirmed->status);

        // Verify active balance now reflects the purchased credits
        $summary = AiCreditService::getSummary($org, $branch);
        $this->assertEquals($pack->credits, $summary['topup_remaining']);

        // Second confirmation (simulating duplicate webhook replay)
        $reconfirmed = AiCreditService::confirmPurchase($reference, 'FLW_TX_12345');
        $this->assertEquals('successful', $reconfirmed->status);

        // Verify balance was NOT double-credited
        $summaryAfterReplay = AiCreditService::getSummary($org, $branch);
        $this->assertEquals($pack->credits, $summaryAfterReplay['topup_remaining']);
    }

    public function test_failed_webhook_marks_purchase_as_failed(): void
    {
        [$org, $branch, $admin] = $this->createOrgWithAdmin();
        $pack = AiCreditPack::active()->first();

        $reference = 'aic_test_fail_' . uniqid();
        $topup = AiCreditService::createPendingPurchase(
            $org,
            $pack,
            'paystack',
            $reference,
            $branch,
            $admin
        );

        $this->assertEquals('pending', $topup->status);

        AiCreditService::failPurchase($reference, 'Card declined');

        $this->assertDatabaseHas('ai_credit_topups', [
            'id'     => $topup->id,
            'status' => 'failed',
        ]);

        // Failed top-ups are not counted in active balance
        $summary = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(0, $summary['topup_remaining']);
    }

    public function test_credit_consumption_uses_monthly_quota_before_purchased_topup(): void
    {
        [$org, $branch, $admin] = $this->createOrgWithAdmin();
        $pack = AiCreditPack::active()->first();

        // Confirm 500 top-up credits
        $reference = 'aic_test_consume_' . uniqid();
        AiCreditService::createPendingPurchase($org, $pack, 'paystack', $reference, $branch, $admin);
        AiCreditService::confirmPurchase($reference, 'TX_999');

        $initialSummary = AiCreditService::getSummary($org, $branch);
        // Monthly quota is 100, topup is pack->credits
        $this->assertEquals(100, $initialSummary['plan_remaining']);
        $this->assertEquals($pack->credits, $initialSummary['topup_remaining']);
        $this->assertEquals(100 + $pack->credits, $initialSummary['remaining']);

        // Consume 60 credits (less than monthly quota of 100)
        AiCreditService::consumeCredit($org, $branch, $admin, 'chat_query', 60);

        $after60 = AiCreditService::getSummary($org, $branch);
        // Monthly plan remaining should drop to 40, topup should remain untouched at full pack->credits
        $this->assertEquals(40, $after60['plan_remaining']);
        $this->assertEquals($pack->credits, $after60['topup_remaining']);

        // Consume another 50 credits (exhausts monthly plan 40, takes remaining 10 from topup)
        AiCreditService::consumeCredit($org, $branch, $admin, 'chat_query', 50);

        $afterExhaustPlan = AiCreditService::getSummary($org, $branch);
        $this->assertEquals(0, $afterExhaustPlan['plan_remaining']);
        $this->assertEquals($pack->credits - 10, $afterExhaustPlan['topup_remaining']);
    }

    public function test_callback_and_webhook_idempotency_avoids_duplicate_transaction(): void
    {
        [$org, $branch, $admin] = $this->createOrgWithAdmin();
        $pack = AiCreditPack::active()->first();

        $reference = 'aic_test_concur_' . uniqid();
        $topup = AiCreditService::createPendingPurchase($org, $pack, 'paystack', $reference, $branch, $admin);

        // Simulate gateway webhook confirming transaction first
        AiCreditService::confirmPurchase($reference, 'PSTK_98765');
        $this->assertEquals('successful', $topup->fresh()->status);

        // Simulate user browser being redirected to callback URL afterwards
        $response = $this->actingAs($admin)
            ->get(route('org.credits.callback', ['reference' => $reference]));

        $response->assertRedirect(route('org.credits.index'));
        $response->assertSessionHas('success');

        // Verify balance remained exactly 1 pack of credits, zero duplicate crediting
        $summary = AiCreditService::getSummary($org, $branch);
        $this->assertEquals($pack->credits, $summary['topup_remaining']);
    }

    public function test_webhook_endpoints_are_excluded_from_csrf_verification(): void
    {
        // Calling webhook without any CSRF token header should NOT return 419
        $flwResp = $this->postJson(route('webhooks.payments.flutterwave'), [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => 'invalid_ref_test',
                'status' => 'successful',
            ],
        ]);
        $this->assertNotEquals(419, $flwResp->getStatusCode());

        $pstkResp = $this->postJson(route('webhooks.payments.paystack'), [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'invalid_ref_test',
                'status' => 'success',
            ],
        ]);
        $this->assertNotEquals(419, $pstkResp->getStatusCode());
    }
}
