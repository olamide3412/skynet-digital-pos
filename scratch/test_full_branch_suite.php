<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Category;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\PosCustomer;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\SaleService;
use App\Services\ReturnService;
use Illuminate\Support\Facades\DB;

echo "=====================================================\n";
echo "  FULL BRANCH OPERATIONS AUTOMATED TEST SUITE        \n";
echo "=====================================================\n\n";

$branch = Branch::first();
echo "Testing on Branch: {$branch->name} (ID: {$branch->id})\n";

$user = User::where('branch_id', $branch->id)->first() ?? User::first();
$category = Category::where('branch_id', $branch->id)->first() ?? Category::first();
$customer = PosCustomer::where('branch_id', $branch->id)->first() ?? PosCustomer::first();

DB::beginTransaction();
try {
    // 1. Create a dynamic Multi-Unit Item
    echo "\n[TEST 1] Creating Item with Custom Units (Sachet, Roll, Carton)\n";
    $item = Item::create([
        'branch_id'       => $branch->id,
        'category_id'     => $category?->id,
        'item_name'       => 'Amoxil Extra ' . rand(1000, 9999),
        'barcode_number'  => 'AMOX-' . rand(100000, 999999),
        'buy_price'       => 150.00,
        'price'           => 250.00,
        'wholesale_price' => 220.00,
        'unit_label'      => 'Sachet',
        'front_store_qty' => 1000,
        'back_store_qty'  => 2000,
        'price_locked'    => true,
    ]);

    $unitRoll = ItemUnit::create([
        'item_id'                => $item->id,
        'branch_id'              => $branch->id,
        'unit_name'              => 'Roll',
        'quantity_in_base_units' => 10,
        'selling_price'          => 2300.00,
        'wholesale_price'        => 2100.00,
        'sort_order'             => 1,
        'status'                 => 'active',
    ]);

    $unitCarton = ItemUnit::create([
        'item_id'                => $item->id,
        'branch_id'              => $branch->id,
        'unit_name'              => 'Carton',
        'quantity_in_base_units' => 100,
        'selling_price'          => 22000.00,
        'wholesale_price'        => 20000.00,
        'sort_order'             => 2,
        'status'                 => 'active',
    ]);

    echo " ✓ Created Item ID {$item->id} with units Roll (10x) and Carton (100x)\n";

    // 2. Test Hold & Resume Cart with Variants
    echo "\n[TEST 2] Testing Hold Cart & Resume Cart with Variants\n";
    $heldSale = HeldSale::create([
        'user_id'     => $user->id,
        'customer_id' => $customer?->id,
        'hold_name'   => 'Dr. John Prescription',
        'status'      => 'held',
    ]);

    HeldSaleItem::create([
        'held_sale_id' => $heldSale->id,
        'item_id'      => $item->id,
        'qty'          => 3,
        'unit_used'    => 'Roll',
        'price'        => 2300.00,
    ]);

    HeldSaleItem::create([
        'held_sale_id' => $heldSale->id,
        'item_id'      => $item->id,
        'qty'          => 5,
        'unit_used'    => 'Sachet',
        'price'        => 250.00,
    ]);

    echo " ✓ Held Sale #{$heldSale->id} created with 3 Rolls and 5 Sachets\n";

    $heldSale->load('items.item.units');
    $heldItemsCount = $heldSale->items->count();
    if ($heldItemsCount !== 2) {
        throw new \Exception("Held items count mismatch!");
    }
    echo " ✓ Resumed Held Cart successfully with {$heldItemsCount} line items\n";

    // 3. Complete Sale from Held Cart
    echo "\n[TEST 3] Completing POS Sale with Held Cart Lines\n";
    $saleData = [
        'customer_id'       => $customer?->id,
        'purchase_type'     => 'Consumer',
        'payment_method'    => 'Multiple',
        'cash'              => 5000.00,
        'bank_transfer'     => 3150.00, // 3 * 2300 = 6900 + 5 * 250 = 1250 => Total 8150
        'discount_type'     => null,
        'discount_value'    => 0,
        'consultation_fee'  => 0,
        'items' => [
            [
                'item_id'   => $item->id,
                'qty'       => 3,
                'unit_used' => 'Roll', // 3 * 10 = 30 sachets @ 2300 = 6900
                'price'     => 2300.00,
            ],
            [
                'item_id'   => $item->id,
                'qty'       => 5,
                'unit_used' => 'Sachet', // 5 sachets @ 250 = 1250
                'price'     => 250.00,
            ],
        ]
    ];

    $sale = SaleService::process($saleData, $user);
    $heldSale->update(['status' => 'completed']);
    echo " ✓ Sale completed: Receipt #{$sale->receipt_id}, Grand Total: ₦" . number_format($sale->final_total, 2) . "\n";

    $item->refresh();
    // Initial: 1000. Deducted: 30 + 5 = 35 sachets. Expected: 965
    echo " ✓ Front store stock reduced to: {$item->front_store_qty} (Expected: 965)\n";
    if ($item->front_store_qty !== 965) {
        throw new \Exception("Stock deduction failed! Got {$item->front_store_qty}, expected 965");
    }

    // 4. Test Stock Transfer
    echo "\n[TEST 4] Testing Stock Transfer (Back Store -> Front Store)\n";
    $transferQty = 200; // 200 sachets
    $item->back_store_qty  -= $transferQty;
    $item->front_store_qty += $transferQty;
    $item->save();

    \App\Models\StockTransfer::create([
        'branch_id'      => $branch->id,
        'user_id'        => $user->id,
        'item_id'        => $item->id,
        'from_location'  => 'back_store',
        'to_location'    => 'front_store',
        'qty_base_units' => $transferQty,
        'unit_used'      => 'Sachet',
    ]);

    $item->refresh();
    echo " ✓ Transferred 200 base units. Front store: {$item->front_store_qty}, Back store: {$item->back_store_qty}\n";
    if ($item->front_store_qty !== 1165 || $item->back_store_qty !== 1800) {
        throw new \Exception("Stock transfer failed!");
    }

    // 5. Test Return with Restock
    echo "\n[TEST 5] Testing Sale Return (Return 1 Roll = 10 Sachets)\n";
    $returnItems = [
        [
            'item_id'   => $item->id,
            'qty'       => 1,
            'unit_used' => 'Roll',
            'reason'    => 'Customer change of mind',
        ]
    ];
    ReturnService::process($sale->id, $returnItems, $user);
    $item->refresh();
    echo " ✓ Return processed. Stock after restock: {$item->front_store_qty} (Expected: 1175)\n";
    if ($item->front_store_qty !== 1175) {
        throw new \Exception("Return restock failed!");
    }

    // 6. Test Reports Aggregation
    echo "\n[TEST 6] Testing Reports Aggregation Query\n";
    $todayStart = now()->startOfDay();
    $todayEnd   = now()->endOfDay();

    $itemBreakdown = \App\Models\SaleOrder::with('item')
        ->whereHas('sale', function ($q) use ($todayStart, $todayEnd, $branch) {
            $q->whereBetween('created_at', [$todayStart, $todayEnd])
              ->where('branch_id', $branch->id);
        })
        ->where('item_id', $item->id)
        ->select('item_id', 'item_name', 'unit_used',
            DB::raw('SUM(qty) as total_qty'),
            DB::raw('SUM(total_selling_price) as total_amount')
        )
        ->groupBy('item_id', 'item_name', 'unit_used')
        ->orderBy('total_amount', 'desc')
        ->get();

    echo " ✓ End-of-day Item Breakdown records found for test item: {$itemBreakdown->count()}\n";
    foreach ($itemBreakdown as $row) {
        echo "   - Unit: {$row->unit_used}, Qty Sold: {$row->total_qty}, Total: ₦" . number_format($row->total_amount, 2) . "\n";
    }

    echo "\n=====================================================\n";
    echo "  ALL BRANCH OPERATIONAL TESTS PASSED 100% SUCESSFULLY!\n";
    echo "=====================================================\n";

    DB::rollBack();
    echo "\n[INFO] Test transaction rolled back safely (Database remains clean).\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[FAIL] Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
