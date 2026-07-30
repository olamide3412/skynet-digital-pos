<?php

namespace Tests\Feature;

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
        // Setup
        $user = User::factory()->create();
        $this->actingAs($user);

        // Turn on qty deduction
        PosSettings::updateOrCreate(['id' => 1], [
            'is_qty_deduction' => 1,
            'sell_interface' => 'classic',
            'business_sector' => 'commerce',
        ]);

        $customer = PosCustomer::factory()->create(['name' => 'John Doe']);
        $item = Item::factory()->create([
            'item_name' => 'Test Product',
            'qty' => 10,
            'buy_price' => 50,
            'retail_price' => 100,
        ]);

        $data = [
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'discount' => 10,
            'tax' => 5,
            'amount_paid' => 100,
            'status' => 'Completed',
            'notes' => 'Test sale',
            'items' => [
                [
                    'id' => $item->id,
                    'name' => $item->item_name,
                    'price' => $item->retail_price,
                    'qty' => 2,
                    'total_price' => 200,
                ]
            ],
            'subtotal' => 200,
            'total' => 195, // 200 - 10 + 5
        ];

        // Process
        $sale = SaleService::process($data, $user);

        // Assert Sale
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'total_amount' => 195,
            'payment_method' => 'cash',
        ]);

        // Assert Sale Order Item
        $this->assertDatabaseHas('sale_orders', [
            'sale_id' => $sale->id,
            'item_id' => $item->id,
            'qty' => 2,
            'selling_price' => 100,
            'total_selling_price' => 200,
        ]);

        // Assert Inventory Deduction
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'qty' => 8, // 10 - 2
        ]);
        
        // Assert Inventory Transaction Log
        $this->assertDatabaseHas('inventory_transactions', [
            'item_id' => $item->id,
            'transaction_type' => 'sale',
            'qty' => 2,
            'previous_qty' => 10,
            'new_qty' => 8,
        ]);
    }
}
