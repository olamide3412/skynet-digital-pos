<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CommerceApiV1VersioningTest extends TestCase
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
            'name'   => 'V1 Test Org',
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
            'name'            => 'V1 Test Branch',
            'slug'            => 'v1-test-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'V1 Store',
            'platform'        => 'custom',
            'status'          => 'active',
            'settings'        => ['reservation_ttl_minutes' => 30],
        ]);

        $token = $this->integration->createToken('v1_test_token');
        $this->plainTextToken = $token->plainTextToken;

        $this->item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'V1 Item',
            'barcode_number'  => 'BAR-V1-' . uniqid(),
            'price'           => 150.00,
            'buy_price'       => 100.00,
            'front_store_qty' => 20,
            'back_store_qty'  => 0,
        ]);
    }

    public function test_v1_ping_endpoint_on_both_prefix_styles(): void
    {
        // 1. /api/v1/pos-integration/ping
        $resp1 = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson('/api/v1/pos-integration/ping');

        $resp1->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('data.status', 'connected');

        // 2. /api/pos-integration/v1/ping
        $resp2 = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson('/api/pos-integration/v1/ping');

        $resp2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('data.status', 'connected');
    }

    public function test_unversioned_routes_are_not_supported_due_to_no_backward_compatibility(): void
    {
        // Legacy unversioned ping should return 404
        $resp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson('/api/pos-integration/ping');

        $resp->assertStatus(404);

        // Legacy unversioned catalog should return 404
        $catResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson('/api/pos-integration/catalog');

        $catResp->assertStatus(404);
    }

    public function test_v1_catalog_and_stock_endpoints(): void
    {
        // Catalog
        $response = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson('/api/v1/pos-integration/catalog');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonStructure(['data', 'pagination', 'meta', 'next_page_url']);

        // Single Item Stock
        $stockResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson("/api/v1/pos-integration/catalog/{$this->item->id}/stock");

        $stockResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('data.available_qty', 20)
            ->assertJsonPath('data.front_store_qty', 20)
            ->assertJsonPath('data.reserved_qty', 0);

        // Batch Availability
        $availResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->postJson('/api/v1/pos-integration/availability', [
                'item_ids' => [$this->item->id],
            ]);

        $availResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version');
    }

    public function test_v1_reservation_lifecycle_endpoints(): void
    {
        $orderRef = 'ORD-V1-' . uniqid();

        // 1. Reserve via /api/v1/pos-integration/reservations
        $resResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->postJson('/api/v1/pos-integration/reservations', [
                'external_order_reference' => $orderRef,
                'items' => [
                    ['item_id' => $this->item->id, 'quantity' => 2, 'unit_price' => 150.00],
                ],
                'ttl_minutes' => 30,
            ]);

        $resResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('data.external_order_reference', $orderRef);

        // 2. Status via /api/v1/pos-integration/reservations/{ref}
        $statusResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->getJson("/api/v1/pos-integration/reservations/{$orderRef}");

        $statusResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('data.status', 'pending');

        // 3. Confirm via /api/v1/pos-integration/reservations/{ref}/confirm
        $confirmResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->postJson("/api/v1/pos-integration/reservations/{$orderRef}/confirm");

        $confirmResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('data.status', 'confirmed');

        $this->item->refresh();
        $this->assertEquals(18, $this->item->front_store_qty);
    }

    public function test_v1_mapping_registration_endpoints(): void
    {
        $regResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->postJson('/api/v1/pos-integration/mappings/register', [
                'mappings' => [
                    [
                        'pos_item_id' => $this->item->id,
                        'ecommerce_product_id' => 'ECOM-PROD-999',
                        'type' => 'simple',
                    ],
                ],
            ]);

        $regResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('registered', 1);

        // Unregister
        $unregResp = $this->withHeader('Authorization', "Bearer {$this->plainTextToken}")
            ->postJson('/api/v1/pos-integration/mappings/unregister', [
                'pos_item_ids' => [$this->item->id],
            ]);

        $unregResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('api_version')
            ->assertJsonPath('unregistered', 1);
    }
}
