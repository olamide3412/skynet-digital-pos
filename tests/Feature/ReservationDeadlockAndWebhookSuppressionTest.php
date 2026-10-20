<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use App\Modules\CommerceIntegration\Services\CommerceWebhookDispatcher;
use App\Modules\CommerceIntegration\Services\InventoryConfirmationService;
use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReservationDeadlockAndWebhookSuppressionTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected CommerceIntegration $integration;
    protected string $plainTextToken;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = PricingPlan::firstOrCreate(
            ['name' => 'Enterprise'],
            [
                'first_year_price' => 'Custom',
                'renewal_price'    => 'Custom',
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

        $this->org = Organization::create([
            'name'   => 'Deadlock Test Org',
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subDay(),
            'renewal_date'    => Carbon::now()->addYear(),
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Deadlock Test Branch',
            'slug'            => 'deadlock-test-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Deadlock Test Store',
            'platform'        => 'custom',
            'webhook_url'     => 'http://127.0.0.1:8002/api/webhooks/pos-inventory',
            'webhook_secret'  => 'test-secret',
            'status'          => 'active',
            'settings'        => ['reservation_ttl_minutes' => 30],
        ]);

        $token = $this->integration->createToken('test_token');
        $this->plainTextToken = $token->plainTextToken;

        $this->item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Smartphone X',
            'barcode_number'  => 'BAR-TEST-123',
            'price'           => 1000.00,
            'buy_price'       => 700.00,
            'front_store_qty' => 50,
            'back_store_qty'  => 0,
        ]);
    }

    public function test_confirming_reservation_suppresses_outbound_webhooks_preventing_deadlock(): void
    {
        Http::fake();

        // 1. Create a reservation
        $reservationService = app(InventoryReservationService::class);
        $orderRef = 'ORD-NO-DEADLOCK-1001';

        $reservation = $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: $orderRef,
            items: [
                ['item_id' => $this->item->id, 'quantity' => 2, 'unit_price' => 1000.00],
            ],
            ttlMinutes: 30
        );

        $this->assertEquals('pending', $reservation->status);

        // 2. Confirm via API
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->plainTextToken}",
            'X-Client-Id'   => $this->integration->client_id,
            'Accept'        => 'application/json',
        ])->postJson("/api/v1/pos-integration/reservations/{$orderRef}/confirm");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Order inventory successfully confirmed and deducted.',
            'data' => [
                'external_order_reference' => $orderRef,
                'status' => 'confirmed',
            ],
        ]);

        // 3. Verify stock was physically deducted
        $this->item->refresh();
        $this->assertEquals(48, $this->item->front_store_qty);

        // 4. Critical Assertion: Zero outbound webhooks were sent to Ecommerce
        // This guarantees port 8002 was not called while waiting for the confirm response!
        Http::assertNothingSent();
    }

    public function test_regular_item_stock_change_outside_confirmation_defers_and_delivers_webhook(): void
    {
        Http::fake([
            'http://127.0.0.1:8002/api/webhooks/pos-inventory' => Http::response(['status' => 'ok'], 200),
        ]);

        // When a cashier or inventory adjustment saves an item in POS
        $this->item->front_store_qty = 45;
        $this->item->save();

        // Immediately after save, webhook should NOT have blocked execution
        // It is queued in deferred list
        CommerceWebhookDispatcher::flushDeferred();

        // Webhook was delivered cleanly
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:8002/api/webhooks/pos-inventory'
                && $request['event'] === 'inventory.updated'
                && $request['data']['pos_item_id'] === $this->item->id
                && $request['data']['available_qty'] === 45;
        });
    }

    public function test_without_webhooks_restores_previous_suppression_state(): void
    {
        $this->assertFalse(CommerceWebhookDispatcher::isSuppressed());

        CommerceWebhookDispatcher::withoutWebhooks(function () {
            $this->assertTrue(CommerceWebhookDispatcher::isSuppressed());
        });

        $this->assertFalse(CommerceWebhookDispatcher::isSuppressed());
    }
}
