<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\EcommerceProductLink;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EcommerceMappingAndDeletionProtectionTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $user;
    protected CommerceIntegration $integration;
    protected string $plainTextToken;
    protected Item $item1;
    protected Item $item2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Plan & Capability
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

        // 2. Setup Organization & Subscription
        $this->org = Organization::create([
            'name'   => 'Test Ecom Org ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => now()->subDays(5),
            'renewal_date'    => now()->addYear(),
        ]);

        // 3. Setup Branch & User
        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Ecom Branch ' . uniqid(),
            'slug'            => 'ecom-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->user = User::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Test Manager',
            'username'        => 'mgr_' . substr(uniqid(), 0, 8),
            'email'           => 'manager_' . uniqid() . '@example.com',
            'password'        => bcrypt('secret123'),
            'is_super_admin'  => true,
            'is_active'       => true,
        ]);

        // 4. Setup Commerce Integration
        $this->integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Main Online Store',
            'platform'        => 'custom',
            'status'          => 'active',
            'settings'        => ['reservation_ttl_minutes' => 15],
        ]);

        $tokenResult = $this->integration->createToken('ecom_test_token');
        $this->plainTextToken = $tokenResult->plainTextToken;

        // 5. Setup POS Items
        $this->item1 = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Organic Honey ' . uniqid(),
            'price'           => 4500,
            'buy_price'       => 3000,
            'front_store_qty' => 50,
            'back_store_qty'  => 50,
        ]);

        $this->item2 = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Green Tea Box ' . uniqid(),
            'price'           => 2000,
            'buy_price'       => 1200,
            'front_store_qty' => 30,
            'back_store_qty'  => 20,
        ]);
    }

    public function test_can_register_ecommerce_product_links(): void
    {
        $payload = [
            'mappings' => [
                [
                    'pos_item_id'          => $this->item1->id,
                    'ecommerce_product_id' => 'wc_prod_991',
                    'type'                 => 'simple',
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->postJson('/api/v1/pos-integration/mappings/register', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'registered' => 1,
            ]);

        $this->assertDatabaseHas('ecommerce_product_links', [
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'wc_prod_991',
            'is_active'            => true,
        ]);

        $this->assertTrue($this->item1->fresh()->is_linked_to_ecommerce);
        $this->assertTrue($this->item1->fresh()->isLinkedToEcommerce());
    }

    public function test_deletion_of_linked_item_is_blocked_by_model_hook(): void
    {
        EcommerceProductLink::create([
            'branch_id'            => $this->branch->id,
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'store_item_11',
            'type'                 => 'simple',
            'is_active'            => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("It is currently linked to an active product in your online Ecommerce store");

        $this->item1->delete();
    }

    public function test_item_controller_destroy_rejects_linked_item(): void
    {
        EcommerceProductLink::create([
            'branch_id'            => $this->branch->id,
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'store_item_22',
            'type'                 => 'simple',
            'is_active'            => true,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('pos.items.destroy', [
                'branch' => $this->branch->slug,
                'item'   => $this->item1->id,
            ]));

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ]);

        $this->assertDatabaseHas('items', ['id' => $this->item1->id]);
    }

    public function test_bulk_delete_skips_linked_items(): void
    {
        // Link item1, keep item2 unlinked
        EcommerceProductLink::create([
            'branch_id'            => $this->branch->id,
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'store_item_33',
            'type'                 => 'simple',
            'is_active'            => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('pos.items.bulk-action', ['branch' => $this->branch->slug]), [
                'action'   => 'delete',
                'item_ids' => [$this->item1->id, $this->item2->id],
            ]);

        $response->assertSessionHas('warning');

        // Item1 should be preserved, Item2 deleted
        $this->assertDatabaseHas('items', ['id' => $this->item1->id]);
        $this->assertDatabaseMissing('items', ['id' => $this->item2->id]);
    }

    public function test_can_unregister_ecommerce_product_links(): void
    {
        EcommerceProductLink::create([
            'branch_id'            => $this->branch->id,
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'store_item_44',
            'type'                 => 'simple',
            'is_active'            => true,
        ]);

        $this->assertTrue($this->item1->fresh()->is_linked_to_ecommerce);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->postJson('/api/v1/pos-integration/mappings/unregister', [
                'pos_item_ids' => [$this->item1->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'unregistered' => 1,
            ]);

        $this->assertFalse($this->item1->fresh()->is_linked_to_ecommerce);

        // Can now be deleted safely
        $delResponse = $this->withoutHeader('Authorization')
            ->actingAs($this->user, 'web')
            ->delete(route('pos.items.destroy', [
                'branch' => $this->branch->slug,
                'item'   => $this->item1->id,
            ]));

        $delResponse->assertRedirect();
        $this->assertDatabaseMissing('items', ['id' => $this->item1->id]);
    }

    public function test_can_reconcile_ecommerce_product_links(): void
    {
        EcommerceProductLink::create([
            'branch_id'            => $this->branch->id,
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'old_store_item_1',
            'type'                 => 'simple',
            'is_active'            => true,
        ]);

        // Reconcile with only item2 active
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainTextToken)
            ->postJson('/api/v1/pos-integration/mappings/reconcile', [
                'mappings' => [
                    [
                        'pos_item_id'          => $this->item2->id,
                        'ecommerce_product_id' => 'new_store_item_2',
                        'type'                 => 'simple',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'    => true,
                'reconciled' => 1,
            ]);

        $this->assertDatabaseMissing('ecommerce_product_links', [
            'pos_item_id' => $this->item1->id,
        ]);

        $this->assertDatabaseHas('ecommerce_product_links', [
            'pos_item_id'          => $this->item2->id,
            'ecommerce_product_id' => 'new_store_item_2',
            'is_active'            => true,
        ]);
    }

    public function test_superadmin_branch_item_controller_destroy_rejects_linked_item(): void
    {
        EcommerceProductLink::create([
            'branch_id'            => $this->branch->id,
            'pos_item_id'          => $this->item1->id,
            'ecommerce_product_id' => 'sa_store_item_55',
            'type'                 => 'simple',
            'is_active'            => true,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('superadmin.branches.items.destroy', [
                'branch' => $this->branch->slug,
                'item'   => $this->item1->id,
            ]));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('items', ['id' => $this->item1->id]);
    }
}
