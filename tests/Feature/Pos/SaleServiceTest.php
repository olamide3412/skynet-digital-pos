<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PosCustomer;
use App\Models\Item;
use App\Models\User;
use App\Models\PosSettings;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_creates_a_sale_and_deducts_inventory()
    {
        // 1. Create branch
        $branch = Branch::create([
            'name' => 'Test Branch',
            'slug' => 'test-branch',
            'is_active' => true,
        ]);

        // Bind branch to container so current_branch() works in SaleService
        app()->instance('current_branch', $branch);

        // 2. Create user for branch
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
        $this->actingAs($user);

        // 3. PosSettings for branch
        PosSettings::updateOrCreate(['branch_id' => $branch->id], [
            'is_qty_deduction' => true,
            'sell_interface' => 'classic',
            'business_sector' => 'commerce',
        ]);

        $customer = PosCustomer::create([
            'branch_id' => $branch->id,
            'name' => 'John Doe',
            'phone' => '08012345678',
        ]);

        $item = Item::create([
            'branch_id' => $branch->id,
            'item_name' => 'Test Product',
            'front_store_qty' => 10,
            'back_store_qty' => 20,
            'buy_price' => 50,
            'price' => 100,
        ]);

        $data = [
            'customer_id' => $customer->id,
            'payment_method' => 'Cash',
            'discount_amount' => 10,
            'amount_paid' => 200,
            'items' => [
                [
                    'item_id' => $item->id,
                    'price' => 100,
                    'qty' => 2,
                ]
            ],
        ];

        // Process
        $sale = SaleService::process($data, $user);

        // Assert Sale
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'final_total' => 190, // (100*2) - 10
            'payment_method' => 'Cash',
        ]);

        // Assert Sale Order Item
        $this->assertDatabaseHas('sale_orders', [
            'sale_id' => $sale->id,
            'item_id' => $item->id,
            'qty' => 2,
            'selling_price' => 100,
            'total_selling_price' => 200,
        ]);

        // Assert Inventory Deduction from front_store_qty
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'front_store_qty' => 8, // 10 - 2
        ]);

        // Assert Inventory Transaction Log
        $this->assertDatabaseHas('inventory_transactions', [
            'branch_id' => $branch->id,
            'item_id' => $item->id,
            'transaction_type' => 'sale',
            'qty' => 2,
            'previous_qty' => 10,
            'new_qty' => 8,
            'location' => 'front_store',
        ]);
    }
}
