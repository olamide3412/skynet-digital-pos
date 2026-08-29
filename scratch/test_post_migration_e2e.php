<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Category;
use App\Models\PosCustomer;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\SaleService;
use App\Services\ReturnService;
use Illuminate\Support\Facades\DB;

echo "=====================================================\n";
echo "  COMPREHENSIVE POST-MIGRATION E2E TEST SUITE        \n";
echo "=====================================================\n\n";

$branch = Branch::first();
if (!$branch) {
    echo "ERROR: No branch found.\n";
    exit(1);
}
echo "Testing on Branch: {$branch->name} (ID: {$branch->id})\n";

$user = User::where('branch_id', $branch->id)->first() ?? User::first();
$category = Category::where('branch_id', $branch->id)->first() ?? Category::first();
$customer = PosCustomer::where('branch_id', $branch->id)->first() ?? PosCustomer::first();

DB::beginTransaction();
try {
    // 1. Create a dynamic Multi-Unit Item without any legacy columns
    echo "\n--- 1. Creating Multi-Unit Item (Base: Sachet) ---\n";
    $item = Item::create([
        'branch_id'       => $branch->id,
        'category_id'     => $category?->id,
        'item_name'       => 'Test Dynamic Unit Drug ' . rand(1000, 9999),
        'barcode_number'  => 'TEST-DYN-' . rand(100000, 999999),
        'buy_price'       => 50.00,
        'price'           => 100.00,
        'wholesale_price' => 90.00,
        'unit_label'      => 'Sachet',
        'front_store_qty' => 500, // 500 sachets
        'back_store_qty'  => 1000,
        'price_locked'    => true,
    ]);

    // Create 3 dynamic units
    $unitRoll = ItemUnit::create([
        'item_id'                => $item->id,
        'branch_id'              => $branch->id,
        'unit_name'              => 'Roll',
        'quantity_in_base_units' => 10, // 1 Roll = 10 Sachets
        'selling_price'          => 950.00,
        'wholesale_price'        => 850.00,
        'sort_order'             => 1,
        'status'                 => 'active',
    ]);

    $unitCarton = ItemUnit::create([
        'item_id'                => $item->id,
        'branch_id'              => $branch->id,
        'unit_name'              => 'Carton',
        'quantity_in_base_units' => 100, // 1 Carton = 100 Sachets
        'selling_price'          => 9000.00,
        'wholesale_price'        => 8000.00,
        'sort_order'             => 2,
        'status'                 => 'active',
    ]);

    echo "Item created: ID {$item->id} ({$item->item_name})\n";
    echo "Base Unit: {$item->unit_label} @ ₦{$item->price}\n";
    echo "Unit 1: {$unitRoll->unit_name} (10 base) @ ₦{$unitRoll->selling_price}\n";
    echo "Unit 2: {$unitCarton->unit_name} (100 base) @ ₦{$unitCarton->selling_price}\n";

    // 2. Test Unit Conversions & Price Resolution
    echo "\n--- 2. Testing Unit Conversions & Pricing ---\n";
    $item->load('units');

    $priceSachet = $item->getPriceForUnitLevel('Sachet', 'Consumer');
    $priceRoll   = $item->getPriceForUnitLevel('Roll', 'Consumer');
    $priceCarton = $item->getPriceForUnitLevel('Carton', 'Consumer');
    $priceRollWs = $item->getPriceForUnitLevel('Roll', 'Wholesale');

    echo "Resolved Sachet Price: ₦{$priceSachet} (Expected 100.00)\n";
    echo "Resolved Roll Price: ₦{$priceRoll} (Expected 950.00)\n";
    echo "Resolved Carton Price: ₦{$priceCarton} (Expected 9000.00)\n";
    echo "Resolved Roll Wholesale: ₦{$priceRollWs} (Expected 850.00)\n";

    if ($priceSachet != 100.0 || $priceRoll != 950.0 || $priceCarton != 9000.0 || $priceRollWs != 850.0) {
        throw new \Exception("Price resolution failed!");
    }

    $baseUnits2Rolls   = $item->toBaseUnits(2, 'Roll');
    $baseUnits1Carton  = $item->toBaseUnits(1, 'Carton');
    $baseUnits5Sachets = $item->toBaseUnits(5, 'Sachet');

    echo "2 Rolls -> {$baseUnits2Rolls} base units (Expected 20)\n";
    echo "1 Carton -> {$baseUnits1Carton} base units (Expected 100)\n";
    echo "5 Sachets -> {$baseUnits5Sachets} base units (Expected 5)\n";

    if ($baseUnits2Rolls !== 20 || $baseUnits1Carton !== 100 || $baseUnits5Sachets !== 5) {
        throw new \Exception("Base unit conversion failed!");
    }

    // 3. Test POS Sale Checkout with Mixed Units
    echo "\n--- 3. Testing POS Checkout with Mixed Units ---\n";
    $initialStock = $item->front_store_qty; // 500
    $saleData = [
        'customer_id'       => $customer?->id,
        'purchase_type'     => 'Consumer',
        'payment_method'    => 'Cash',
        'cash'              => 11900.00,
        'bank_transfer'     => 0,
        'discount_type'     => null,
        'discount_value'    => 0,
        'consultation_fee'  => 0,
        'items' => [
            [
                'item_id'        => $item->id,
                'qty'            => 1,
                'unit_used'      => 'Carton', // 1 Carton = 100 base units @ 9000
                'unit_price'     => 9000.00,
                'buy_price'      => 50.00 * 100,
                'line_total'     => 9000.00,
            ],
            [
                'item_id'        => $item->id,
                'qty'            => 2,
                'unit_used'      => 'Roll', // 2 Rolls = 20 base units @ 950 each = 1900
                'unit_price'     => 950.00,
                'buy_price'      => 50.00 * 10,
                'line_total'     => 1900.00,
            ],
            [
                'item_id'        => $item->id,
                'qty'            => 10,
                'unit_used'      => 'Sachet', // 10 Sachets = 10 base units @ 100 each = 1000
                'unit_price'     => 100.00,
                'buy_price'      => 50.00,
                'line_total'     => 1000.00,
            ],
        ]
    ];

    $sale = SaleService::process($saleData, $user);
    echo "Sale created: Receipt #{$sale->receipt_id}, Grand Total: ₦" . number_format($sale->final_total, 2) . "\n";

    // Expected deduction: 100 + 20 + 10 = 130 base units
    $item->refresh();
    $expectedStock = 500 - 130; // 370
    echo "New Stock: {$item->front_store_qty} Sachets (Expected: {$expectedStock})\n";

    if ($item->front_store_qty !== $expectedStock) {
        throw new \Exception("Stock deduction mismatch! Expected {$expectedStock}, got {$item->front_store_qty}");
    }

    // 4. Test Return Processing with Unit Restock
    echo "\n--- 4. Testing Return Processing (Returning 1 Roll = 10 Sachets) ---\n";
    $returnItems = [
        [
            'item_id'   => $item->id,
            'qty'       => 1, // Returning 1 roll (10 sachets)
            'unit_used' => 'Roll',
            'reason'    => 'Customer ordered too many rolls',
        ]
    ];

    ReturnService::process($sale->id, $returnItems, $user);
    echo "Return processed successfully.\n";

    $item->refresh();
    $expectedRestocked = $expectedStock + 10; // 370 + 10 = 380
    echo "Stock After Restock: {$item->front_store_qty} Sachets (Expected: {$expectedRestocked})\n";

    if ($item->front_store_qty !== $expectedRestocked) {
        throw new \Exception("Restock stock mismatch! Expected {$expectedRestocked}, got {$item->front_store_qty}");
    }

    echo "\n=====================================================\n";
    echo "  ALL POST-MIGRATION TESTS PASSED 100% CLEANLY!      \n";
    echo "=====================================================\n";

    DB::rollBack();
    echo "\n[INFO] Transaction rolled back safely (no dummy records left in database).\n";
} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[FAIL] Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
