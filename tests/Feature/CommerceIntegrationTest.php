<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use App\Modules\CommerceIntegration\Services\InventoryAvailabilityService;
use App\Modules\CommerceIntegration\Services\InventoryConfirmationService;
use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use App\Modules\CommerceIntegration\Services\MappingRegistryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CommerceIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $enterpriseOrg;
    protected Organization $basicOrg;
    protected Branch $branch;
    protected Branch $otherBranch;
    protected CommerceIntegration $integration;
    protected string $plainTextToken;
    protected Item $item1;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Enterprise Plan with ecommerce capability enabled
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

        // 2. Setup Basic Plan without ecommerce capability
        $starterPlan = PricingPlan::firstOrCreate(
            ['name' => 'Starter'],
            [
                'first_year_price' => '₦150,000',
                'renewal_price'    => '₦100,000',
                'is_active'        => true,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $starterPlan->id],
            [
                'max_branches'                  => 1,
                'max_users_per_branch'          => 3,
                'feature_multi_branch'          => false,
                'feature_ecommerce_integration' => false,
            ]
        );

        // 3. Create Organizations
        $this->enterpriseOrg = Organization::create([
            'name'   => 'Acme Global Org',
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->enterpriseOrg->id,
            'pricing_plan_id' => $enterprisePlan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subDay(),
            'renewal_date'    => Carbon::now()->addYear(),
        ]);

        $this->basicOrg = Organization::create([
            'name'   => 'Basic Starter Org',
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->basicOrg->id,
            'pricing_plan_id' => $starterPlan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subDay(),
            'renewal_date'    => Carbon::now()->addYear(),
        ]);

        // 4. Create Branches
        $this->branch = Branch::create([
            'organization_id' => $this->enterpriseOrg->id,
            'name'            => 'Main Downtown Branch',
            'slug'            => 'main-downtown-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->otherBranch = Branch::create([
            'organization_id' => $this->enterpriseOrg->id,
            'name'            => 'Uptown Branch',
            'slug'            => 'uptown-' . uniqid(),
            'is_active'       => true,
        ]);

        // 5. Create Items in Main Branch
        $this->item1 = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Mechanical Keyboard',
            'barcode_number'  => 'KEYB-001-' . uniqid(),
            'price'           => 120.00,
            'buy_price'       => 80.00,
            'front_store_qty' => 10,
            'back_store_qty'  => 50, // Should NEVER be exposed or counted as available
        ]);

        $this->item2 = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Wireless Mouse',
            'barcode_number'  => 'MOUS-001-' . uniqid(),
            'price'           => 45.00,
            'buy_price'       => 25.00,
            'front_store_qty' => 5,
            'back_store_qty'  => 20,
        ]);

        // 6. Create Commerce Integration for Main Branch
        $this->integration = CommerceIntegration::create([
            'organization_id' => $this->enterpriseOrg->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Downtown Shopify Store',
            'platform'        => 'shopify',
            'status'          => 'active',
            'settings'        => ['reservation_ttl_minutes' => 30],
        ]);

        $token = $this->integration->createToken('test_token');
        $this->plainTextToken = $token->plainTextToken;
        $this->integration->update([
            'webhook_secret' => $this->plainTextToken,
            'api_key_hash'   => CommerceIntegration::hashTokenSecret($this->plainTextToken),
        ]);
    }

    /** @test */
    public function availability_service_uses_front_store_qty_only_and_subtracts_active_holds(): void
    {
        $availabilityService = app(InventoryAvailabilityService::class);

        // Before any reservations: available = front_store_qty (10), back_store_qty (50) is ignored
        $this->assertEquals(10, $availabilityService->getAvailableStock($this->item1, $this->branch->id));

        // Create an active reservation for 3 units
        $res = CommerceReservation::create([
            'commerce_integration_id'  => $this->integration->id,
            'organization_id'          => $this->enterpriseOrg->id,
            'branch_id'                => $this->branch->id,
            'external_order_reference' => 'ORDER-TEST-001',
            'status'                   => 'pending',
            'expires_at'               => now()->addMinutes(30),
        ]);

        $res->items()->create([
            'item_id'    => $this->item1->id,
            'quantity'   => 3,
            'unit_price' => 120.00,
        ]);

        // Available should now be 10 - 3 = 7
        $this->assertEquals(7, $availabilityService->getAvailableStock($this->item1, $this->branch->id));

        // Create an expired reservation (should NOT reduce available stock)
        $expiredRes = CommerceReservation::create([
            'commerce_integration_id'  => $this->integration->id,
            'organization_id'          => $this->enterpriseOrg->id,
            'branch_id'                => $this->branch->id,
            'external_order_reference' => 'ORDER-TEST-EXPIRED',
            'status'                   => 'pending',
            'expires_at'               => now()->subMinute(),
        ]);

        $expiredRes->items()->create([
            'item_id'    => $this->item1->id,
            'quantity'   => 4,
            'unit_price' => 120.00,
        ]);

        // Available should still be 7
        $this->assertEquals(7, $availabilityService->getAvailableStock($this->item1, $this->branch->id));
    }

    /** @test */
    public function reservation_service_atomically_reserves_inventory(): void
    {
        $reservationService = app(InventoryReservationService::class);

        $reservation = $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: 'ORDER-100',
            items: [
                ['item_id' => $this->item1->id, 'quantity' => 4],
                ['item_id' => $this->item2->id, 'quantity' => 2],
            ],
            ttlMinutes: 20
        );

        $this->assertDatabaseHas('commerce_reservations', [
            'id'                       => $reservation->id,
            'external_order_reference' => 'ORDER-100',
            'status'                   => 'pending',
        ]);

        $this->assertCount(2, $reservation->items);

        // Physical on-hand stock has not changed yet
        $this->item1->refresh();
        $this->assertEquals(10, $this->item1->front_store_qty);

        // But available stock is reduced
        $availabilityService = app(InventoryAvailabilityService::class);
        $this->assertEquals(6, $availabilityService->getAvailableStock($this->item1, $this->branch->id));
        $this->assertEquals(3, $availabilityService->getAvailableStock($this->item2, $this->branch->id));

        // Audit movements were created
        $this->assertDatabaseHas('commerce_inventory_movements', [
            'commerce_reservation_id' => $reservation->id,
            'type'                    => 'reservation_hold',
            'item_id'                 => $this->item1->id,
            'quantity'                => 4,
        ]);
    }

    /** @test */
    public function reservation_service_rejects_insufficient_stock(): void
    {
        $this->expectException(\App\Modules\CommerceIntegration\Services\InsufficientStockException::class);

        $reservationService = app(InventoryReservationService::class);

        // Attempting to reserve 15 when only 10 are available
        $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: 'ORDER-TOO-MUCH',
            items: [
                ['item_id' => $this->item1->id, 'quantity' => 15],
            ]
        );
    }

    /** @test */
    public function reservation_service_is_idempotent(): void
    {
        $reservationService = app(InventoryReservationService::class);

        $res1 = $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: 'ORDER-IDEMPOTENT',
            items: [
                ['item_id' => $this->item1->id, 'quantity' => 2],
            ]
        );

        // Call again with same external reference
        $res2 = $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: 'ORDER-IDEMPOTENT',
            items: [
                ['item_id' => $this->item1->id, 'quantity' => 2],
            ]
        );

        $this->assertEquals($res1->id, $res2->id);

        // Available stock reduced only once (10 - 2 = 8)
        $availabilityService = app(InventoryAvailabilityService::class);
        $this->assertEquals(8, $availabilityService->getAvailableStock($this->item1, $this->branch->id));
    }

    /** @test */
    public function confirmation_service_deducts_inventory_and_marks_confirmed(): void
    {
        $reservationService = app(InventoryReservationService::class);
        $confirmationService = app(InventoryConfirmationService::class);

        $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: 'ORDER-CONFIRM-1',
            items: [
                ['item_id' => $this->item1->id, 'quantity' => 3],
            ]
        );

        $confirmed = $confirmationService->confirm(
            integration: $this->integration,
            externalOrderReference: 'ORDER-CONFIRM-1',
            fulfillmentDetails: ['tracking_number' => 'DHL-987654']
        );

        $this->assertEquals('confirmed', $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);

        // Physical stock on Item is decremented
        $this->item1->refresh();
        $this->assertEquals(7, $this->item1->front_store_qty);

        // Movement audit is created
        $this->assertDatabaseHas('commerce_inventory_movements', [
            'commerce_reservation_id' => $confirmed->id,
            'type'                    => 'stock_deduction',
            'item_id'                 => $this->item1->id,
            'quantity'                => 3,
            'current_stock'           => 7,
        ]);
    }

    /** @test */
    public function release_restores_available_stock(): void
    {
        $reservationService = app(InventoryReservationService::class);

        $reservationService->reserve(
            integration: $this->integration,
            externalOrderReference: 'ORDER-RELEASE-1',
            items: [
                ['item_id' => $this->item1->id, 'quantity' => 4],
            ]
        );

        $availabilityService = app(InventoryAvailabilityService::class);
        $this->assertEquals(6, $availabilityService->getAvailableStock($this->item1, $this->branch->id));

        $reservationService->release(
            integration: $this->integration,
            externalOrderReference: 'ORDER-RELEASE-1',
            reason: 'cart_abandoned'
        );

        $this->assertEquals(10, $availabilityService->getAvailableStock($this->item1, $this->branch->id));

        $this->assertDatabaseHas('commerce_reservations', [
            'external_order_reference' => 'ORDER-RELEASE-1',
            'status'                   => 'released',
        ]);
    }

    /** @test */
    public function catalog_api_returns_paginated_catalog_with_live_stock(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->getJson('/api/commerce/v1/catalog');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'price',
                        'front_store_qty',
                        'reserved_qty',
                        'available_qty',
                    ],
                ],
                'pagination',
            ]);
    }

    /** @test */
    public function reservations_api_endpoint_workflow(): void
    {
        // 1. Reserve via API
        $reserveRes = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->postJson('/api/commerce/v1/reservations', [
                'external_order_reference' => 'API-ORDER-777',
                'items' => [
                    ['item_id' => $this->item1->id, 'quantity' => 2],
                ],
                'ttl_minutes' => 15,
            ]);

        $reserveRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        // 2. Check Status via API
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->getJson('/api/commerce/v1/reservations/API-ORDER-777');

        $statusRes->assertStatus(200)
            ->assertJsonPath('data.external_order_reference', 'API-ORDER-777')
            ->assertJsonPath('data.status', 'pending');

        // 3. Confirm via API
        $confirmRes = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->postJson('/api/commerce/v1/reservations/confirm', [
                'external_order_reference' => 'API-ORDER-777',
            ]);

        $confirmRes->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->item1->refresh();
        $this->assertEquals(8, $this->item1->front_store_qty);
    }

    /** @test */
    public function mapping_registry_service_and_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->postJson('/api/commerce/v1/mappings', [
                'item_id'             => $this->item1->id,
                'external_product_id' => 'SP-998877',
                'external_variant_id' => 'VAR-1122',
                'external_sku'        => 'SKU-KEYB',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('commerce_item_mappings', [
            'commerce_integration_id' => $this->integration->id,
            'item_id'                 => $this->item1->id,
            'external_product_id'     => 'SP-998877',
        ]);
    }

    /** @test */
    public function item_deletion_guard_blocks_deleting_item_with_active_mapping_or_reservation(): void
    {
        // 1. Create mapping for item1
        CommerceItemMapping::create([
            'commerce_integration_id' => $this->integration->id,
            'item_id'                 => $this->item1->id,
            'external_product_id'     => 'EXT-PROD-1',
        ]);

        // Attempting to delete item1 should fail due to mapping guard
        $this->expectException(\RuntimeException::class);
        $this->item1->delete();
    }

    /** @test */
    public function plan_enforcement_blocks_access_if_organization_lacks_capability(): void
    {
        // Create an integration for the basicOrg branch (which does NOT have feature_ecommerce_integration)
        $basicBranch = Branch::create([
            'organization_id' => $this->basicOrg->id,
            'name'            => 'Basic Branch',
            'slug'            => 'basic-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $basicIntegration = CommerceIntegration::create([
            'organization_id' => $this->basicOrg->id,
            'branch_id'       => $basicBranch->id,
            'name'            => 'Basic Store',
            'platform'        => 'custom',
            'status'          => 'active',
        ]);

        $basicToken = $basicIntegration->createToken('basic_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $basicToken)
            ->getJson('/api/commerce/v1/catalog');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'PLAN_FEATURE_DISABLED');
    }

    /** @test */
    public function bronze_plan_organization_has_ecommerce_capability_enabled_and_can_access_catalog(): void
    {
        $bronzePlan = PricingPlan::firstOrCreate(
            ['name' => 'Bronze'],
            [
                'first_year_price' => '₦250,000',
                'renewal_price'    => '₦180,000',
                'is_active'        => true,
            ]
        );

        // Ensure Bronze plan capability has feature_ecommerce_integration enabled
        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $bronzePlan->id],
            [
                'max_branches'                  => 3,
                'max_users_per_branch'          => 10,
                'feature_multi_branch'          => true,
                'feature_ecommerce_integration' => true,
            ]
        );

        $bronzeOrg = Organization::create([
            'name'   => 'Bronze Retail Org',
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $bronzeOrg->id,
            'pricing_plan_id' => $bronzePlan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subDay(),
            'renewal_date'    => Carbon::now()->addYear(),
        ]);

        $bronzeBranch = Branch::create([
            'organization_id' => $bronzeOrg->id,
            'name'            => 'Bronze Retail Branch',
            'slug'            => 'bronze-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $bronzeIntegration = CommerceIntegration::create([
            'organization_id' => $bronzeOrg->id,
            'branch_id'       => $bronzeBranch->id,
            'name'            => 'Bronze Store',
            'platform'        => 'shopify',
            'status'          => 'active',
        ]);

        $bronzeToken = $bronzeIntegration->createToken('bronze_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $bronzeToken)
            ->getJson('/api/commerce/v1/catalog');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_ping_endpoint_verifies_connection_successfully(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->getJson('/api/commerce/v1/ping');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'POS connection verified successfully.',
            ])
            ->assertJsonPath('data.status', 'connected')
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.branch_name', $this->branch->name)
            ->assertJsonPath('data.client_id', $this->integration->client_id)
            ->assertJsonPath('data.platform', $this->integration->platform);
    }

    public function test_test_connection_alias_endpoint(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->getJson('/api/commerce/v1/test-connection');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_ping_endpoint_validates_client_id_if_provided(): void
    {
        // Valid client_id in header
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTextToken,
            'X-Client-ID' => $this->integration->client_id,
        ])->getJson('/api/commerce/v1/ping');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Mismatched client_id in header should be rejected
        $badResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->plainTextToken,
            'X-Client-ID' => 'pos_cli_invalid_mismatched_key',
        ])->getJson('/api/commerce/v1/ping');

        $badResponse->assertStatus(401)
            ->assertJsonPath('error_code', 'CLIENT_ID_MISMATCH');
    }

    public function test_client_id_is_automatically_assigned_on_creation(): void
    {
        $otherIntegration = CommerceIntegration::create([
            'organization_id' => $this->enterpriseOrg->id,
            'branch_id'       => $this->otherBranch->id,
            'name'            => 'Other Store',
            'platform'        => 'custom',
            'status'          => 'active',
        ]);

        $this->assertNotEmpty($otherIntegration->client_id);
        $this->assertStringStartsWith('pos_cli_', $otherIntegration->client_id);
    }

    public function test_token_never_expires_and_self_heals_if_personal_access_tokens_table_is_wiped(): void
    {
        // Simulate personal_access_tokens rows being wiped during server deployment or DB sync
        $this->integration->tokens()->delete();
        $this->app['auth']->forgetGuards();
        $this->assertEquals(0, $this->integration->tokens()->count());

        // Request with original token still succeeds and self-heals the personal_access_tokens row
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->getJson('/api/commerce/v1/ping');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branch_id', $this->branch->id);

        // Verify token row was automatically restored
        $this->assertEquals(1, $this->integration->tokens()->count());
    }

    public function test_token_authenticates_even_if_token_id_prefix_changes(): void
    {
        $rawSecret = CommerceIntegration::extractRawTokenSecret($this->plainTextToken);
        $shiftedIdToken = '999999|' . $rawSecret;

        $response = $this->withHeader('Authorization', 'Bearer ' . $shiftedIdToken)
            ->getJson('/api/commerce/v1/ping');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.branch_id', $this->branch->id);
    }
}
