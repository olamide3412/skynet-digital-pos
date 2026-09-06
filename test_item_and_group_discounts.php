<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\DiscountGroup;
use App\Models\Item;
use App\Models\ItemDiscount;
use App\Models\ItemUnit;
use App\Models\SaleDiscount;
use App\Services\DiscountService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "=== TESTING ITEM-LEVEL & GROUP DISCOUNTS ===\n\n";

$branch = Branch::first();
if (!$branch) {
    echo "No branch found. Creating test branch...\n";
    $branch = Branch::create([
        'name' => 'Test Promo Branch',
        'slug' => 'test-promo-branch',
        'address' => '123 Test St',
        'phone' => '08012345678',
    ]);
}

// Clean up test discounts for clean state
DB::table('discount_group_items')->delete();
DB::table('discount_groups')->delete();
DB::table('item_discounts')->delete();

// Create or find Test Items
$item1 = Item::where('branch_id', $branch->id)->where('item_name', 'Test Promo Cola')->first();
if (!$item1) {
    $item1 = Item::create([
        'branch_id' => $branch->id,
        'item_name' => 'Test Promo Cola',
        'barcode_number' => 'TESTCOLA001',
        'price' => 1000.00,
        'wholesale_price' => 800.00,
        'buy_price' => 600.00,
        'unit_label' => 'Bottle',
        'front_store_qty' => 100,
        'back_store_qty' => 0,
    ]);
} else {
    $item1->update(['price' => 1000.00, 'wholesale_price' => 800.00]);
}

$item2 = Item::where('branch_id', $branch->id)->where('item_name', 'Test Promo Chips')->first();
if (!$item2) {
    $item2 = Item::create([
        'branch_id' => $branch->id,
        'item_name' => 'Test Promo Chips',
        'barcode_number' => 'TESTCHIPS001',
        'price' => 500.00,
        'wholesale_price' => 400.00,
        'buy_price' => 300.00,
        'unit_label' => 'Pack',
        'front_store_qty' => 100,
        'back_store_qty' => 0,
    ]);
} else {
    $item2->update(['price' => 500.00, 'wholesale_price' => 400.00]);
}

// ── TEST 1: Item-Level Percentage Discount ───────────────────────────────────
echo "[1] Testing Item-Level 10% Percentage Discount on Cola (₦1,000 -> ₦900)...\n";
$itemDisc = ItemDiscount::create([
    'branch_id'      => $branch->id,
    'item_id'        => $item1->id,
    'discount_type'  => 'percentage',
    'discount_value' => 10.00,
    'starts_at'      => now()->subHour(),
    'ends_at'        => now()->addHour(),
    'is_active'      => true,
]);

$res1 = DiscountService::resolveItemDiscount($item1, 'unit', 'Consumer');
assert($res1['discount_layer'] === 'item', "Expected discount_layer === 'item'");
assert($res1['discounted_price'] == 900.00, "Expected discounted_price == 900.00, got {$res1['discounted_price']}");
echo "  ✓ Cola discounted to ₦{$res1['discounted_price']} (Discount: {$res1['discount_name']})\n\n";

// ── TEST 2: Group Discount on Chips (20% Off) ────────────────────────────────
echo "[2] Testing Group Discount 'Snacks Promo' 20% on Chips (₦500 -> ₦400)...\n";
$group = DiscountGroup::create([
    'branch_id'      => $branch->id,
    'name'           => 'Snacks Promo',
    'discount_type'  => 'percentage',
    'discount_value' => 20.00,
    'starts_at'      => now()->subHour(),
    'ends_at'        => now()->addHour(),
    'is_active'      => true,
]);
$group->items()->sync([$item2->id]);

$res2 = DiscountService::resolveItemDiscount($item2, 'unit', 'Consumer');
assert($res2['discount_layer'] === 'group', "Expected discount_layer === 'group'");
assert($res2['discounted_price'] == 400.00, "Expected discounted_price == 400.00, got {$res2['discounted_price']}");
echo "  ✓ Chips discounted to ₦{$res2['discounted_price']} (Discount: {$res2['discount_name']})\n\n";

// ── TEST 3: Priority - Item Discount Overrides Group Discount ────────────────
echo "[3] Testing Priority: Assign Cola (has 10% item disc) to Snacks Promo (20% group disc)...\n";
$group->items()->sync([$item1->id, $item2->id]);

$res3 = DiscountService::resolveItemDiscount($item1, 'unit', 'Consumer');
assert($res3['discount_layer'] === 'item', "Expected discount_layer === 'item' (Priority 1)");
assert($res3['discounted_price'] == 900.00, "Expected discounted_price == 900.00 (10% item disc), got {$res3['discounted_price']}");
echo "  ✓ Item Discount took strict priority! Price is ₦{$res3['discounted_price']} (Layer: {$res3['discount_layer']})\n\n";

// ── TEST 4: Scheduling Window (Expired Discount does NOT apply) ──────────────
echo "[4] Testing Scheduling Window: Expired Item Discount does NOT apply...\n";
$itemDisc->update([
    'starts_at' => now()->subDays(5),
    'ends_at'   => now()->subDays(1), // Expired yesterday
]);

// Now Cola should fall back to Group Discount (Snacks Promo 20% -> ₦800)
$res4 = DiscountService::resolveItemDiscount($item1, 'unit', 'Consumer');
assert($res4['discount_layer'] === 'group', "Expected fallback to group discount when item discount expired");
assert($res4['discounted_price'] == 800.00, "Expected discounted_price == 800.00, got {$res4['discounted_price']}");
echo "  ✓ Expired item discount correctly skipped; fell back to group promo: ₦{$res4['discounted_price']}\n\n";

// ── TEST 5: Inactive Toggle (is_active = false) ──────────────────────────────
echo "[5] Testing Inactive Toggle: When group discount is toggled is_active = false...\n";
$group->update(['is_active' => false]);
$res5 = DiscountService::resolveItemDiscount($item1, 'unit', 'Consumer');
assert($res5['discount_layer'] === null, "Expected no discount when all are inactive/expired");
assert($res5['discounted_price'] == 1000.00, "Expected original price 1000.00, got {$res5['discounted_price']}");
echo "  ✓ Inactive discount correctly skipped; regular price restored: ₦{$res5['discounted_price']}\n\n";

// ── TEST 6: Fixed Amount Discount with Multi-Unit Scaling ─────────────────────
echo "[6] Testing Fixed Amount Discount ₦50 on base unit and Carton variant (12 units multiplier)...\n";
$itemDiscFixed = ItemDiscount::create([
    'branch_id'      => $branch->id,
    'item_id'        => $item1->id,
    'discount_type'  => 'fixed',
    'discount_value' => 50.00, // ₦50 off base unit
    'starts_at'      => now()->subHour(),
    'ends_at'        => now()->addHour(),
    'is_active'      => true,
]);

// Create Carton unit for Cola (12 units @ ₦11,000)
$unitCarton = ItemUnit::updateOrCreate([
    'item_id'   => $item1->id,
    'unit_name' => 'Carton',
], [
    'branch_id'              => $branch->id,
    'quantity_in_base_units' => 12,
    'selling_price'          => 11000.00,
    'wholesale_price'        => 9500.00,
    'status'                 => 'active',
]);


// Test Base Unit
$resBase = DiscountService::resolveItemDiscount($item1, 'unit', 'Consumer');
assert($resBase['discounted_price'] == 950.00, "Expected 1000 - 50 = 950.00, got {$resBase['discounted_price']}");
echo "  ✓ Base unit (Bottle): ₦1,000 - ₦50 = ₦{$resBase['discounted_price']}\n";

// Test Carton Unit: ₦11,000 - (12 * ₦50 = ₦600) = ₦10,400
$item1->load('units');
$resCarton = DiscountService::resolveItemDiscount($item1, 'Carton', 'Consumer');
assert($resCarton['discount_amount'] == 600.00, "Expected discount amount 600.00 (12 * 50), got {$resCarton['discount_amount']}");
assert($resCarton['discounted_price'] == 10400.00, "Expected discounted_price 10400.00, got {$resCarton['discounted_price']}");
echo "  ✓ Variant (Carton of 12): ₦11,000 - (12 × ₦50 = ₦600) = ₦{$resCarton['discounted_price']}\n\n";

echo "=== ALL DISCOUNT HIERARCHY TESTS PASSED SUCCESSFULLY! ===\n";
