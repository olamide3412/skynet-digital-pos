<?php

require 'c:/Projects/Laravel/skynet_digital_pos/vendor/autoload.php';
$app = require_once 'c:/Projects/Laravel/skynet_digital_pos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Services\ItemImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;

echo "=== STARTING MULTI-ROW VARIANTS & CSV IMPORT/EXPORT E2E TEST ===\n\n";

// 1. Schema Check: Confirm DB has NO item_ref or is_base_unit columns
echo "1. Checking Database Schema for items & item_units...\n";
if (Schema::hasColumn('items', 'item_ref') || Schema::hasColumn('items', 'is_base_unit') || Schema::hasColumn('items', 'is_based_unit')) {
    echo "❌ ERROR: DB items table contains CSV-only columns!\n";
    exit(1);
}
if (Schema::hasColumn('item_units', 'item_ref') || Schema::hasColumn('item_units', 'is_base_unit')) {
    echo "❌ ERROR: DB item_units table contains CSV-only columns!\n";
    exit(1);
}
echo "✓ Schema verified: No CSV-only columns exist in the database tables.\n\n";

$branch = Branch::first();
if (!$branch) {
    echo "No branch found!\n";
    exit(1);
}
echo "Using Branch: {$branch->name} (ID: {$branch->id})\n";

// 2. Test SuperAdmin ItemImportService Multi-row Parse & Import
echo "\n2. Testing SuperAdmin ItemImportService Multi-Row Import...\n";
$importService = new ItemImportService();

$randCode = rand(10000, 99999);
$barcode1 = "BAR-{$randCode}-1";
$barcode2 = "BAR-{$randCode}-2";

$csvData = "item_ref,item_name,category,group_address,barcode,description,cost_price,unit_name,quantity_in_base_units,selling_price,wholesale_price,is_base_unit,track_by_imei,initial_stock_quantity,expiry_date\n";
// Group 1: Item with 2 additional variants
$csvData .= "REF001,Test MultiItem {$randCode},Beverages,Cooler 1,{$barcode1},Delicious cold beverage,120.00,Bottle,1,180.00,160.00,Yes,No,50,2028-12-31\n";
$csvData .= "REF001,Test MultiItem {$randCode},Beverages,Cooler 1,{$barcode1},,null,Pack (6-in-1),6,1000.00,900.00,No,No,null,null\n";
$csvData .= "REF001,Test MultiItem {$randCode},Beverages,Cooler 1,{$barcode1},,null,Carton (24-in-1),24,3800.00,3500.00,No,No,null,null\n";
// Group 2: Item with base unit only
$csvData .= "REF002,Test SingleItem {$randCode},Provisions,Aisle 2,{$barcode2},Single item test,250.00,Sachet,1,350.00,320.00,Yes,No,100,2028-06-30\n";

$tmpCsv1 = tempnam(sys_get_temp_dir(), 'sa_csv_');
file_put_contents($tmpCsv1, $csvData);

$parsed = $importService->parseFile($tmpCsv1, 'csv');
echo "Parsed {$parsed['total_rows']} total rows, {$parsed['valid_rows']} valid, " . count($parsed['groups']) . " item groups.\n";

$execResult = $importService->executeImport($branch, $parsed['rows']);
echo "Import executed: {$execResult['imported_count']} items imported, {$execResult['skipped_count']} skipped.\n";

// Verify DB records
$item1 = Item::where('branch_id', $branch->id)->where('barcode_number', $barcode1)->first();
if (!$item1) {
    echo "❌ Failed to find imported multi-unit item in DB!\n";
    exit(1);
}
echo "✓ Base item created: '{$item1->item_name}', unit_label: '{$item1->unit_label}', price: ₦{$item1->price}, cost: ₦{$item1->buy_price}\n";

$units1 = ItemUnit::where('item_id', $item1->id)->orderBy('sort_order')->get();
echo "✓ Variants created in item_units (" . $units1->count() . " rows):\n";
foreach ($units1 as $u) {
    echo "   - Unit: {$u->unit_name}, QtyInBase: {$u->quantity_in_base_units}, Sell Price: ₦{$u->selling_price}, WS Price: ₦{$u->wholesale_price}\n";
}
if ($units1->count() !== 2) {
    echo "❌ Expected 2 item_units rows, got " . $units1->count() . "\n";
    exit(1);
}

// 3. Test Re-importing duplicate file (should report 0 new imports and 2 skipped)
echo "\n3. Testing Duplicate Detection on Re-import...\n";
$dupExecResult = $importService->executeImport($branch, $parsed['rows']);
echo "Duplicate import result: {$dupExecResult['imported_count']} imported, {$dupExecResult['skipped_count']} skipped.\n";
if ($dupExecResult['imported_count'] !== 0 || $dupExecResult['skipped_count'] !== 2) {
    echo "❌ Duplicate handling mismatch! Expected 0 imported and 2 skipped.\n";
    exit(1);
}
echo "✓ Duplicate detection working accurately.\n";
@unlink($tmpCsv1);

// 4. Test Branch Admin ItemController importNativeCsv
echo "\n4. Testing Branch Admin ItemController importNativeCsv...\n";
$randCode2 = rand(10000, 99999);
$barcode3 = "BAR-{$randCode2}-3";

$csvData2 = "item_ref,item_name,category_name,group_address_name,barcode_number,cost_price,unit_name,quantity_in_base_units,selling_price,wholesale_price,is_base_unit,front_store_qty,back_store_qty,expiry_date,item_description\n";
$csvData2 .= "P01,Branch MultiItem {$randCode2},Snacks,Rack B,{$barcode3},80.00,Piece,1,120.00,100.00,Yes,30,120,2029-01-01,Test snack item\n";
$csvData2 .= "P01,Branch MultiItem {$randCode2},Snacks,Rack B,{$barcode3},,Roll (12-in-1),12,1300.00,1150.00,No,,,,,\n";
// Also add 1 duplicate from previous test
$csvData2 .= "P02,Test SingleItem {$randCode},Provisions,Aisle 2,{$barcode2},250.00,Sachet,1,350.00,320.00,Yes,10,20,2028-06-30,Duplicate row\n";

$tmpCsv2 = tempnam(sys_get_temp_dir(), 'ba_csv_');
file_put_contents($tmpCsv2, $csvData2);

$uploadedFile = new UploadedFile($tmpCsv2, 'branch_test.csv', 'text/csv', null, true);
$request = Request::create('/pos/items/import-csv', 'POST', [], [], ['csv_file' => $uploadedFile]);
$session = app('session')->driver();
$request->setLaravelSession($session);
$request->setUserResolver(fn() => \App\Models\User::first());
app()->instance('current_branch', $branch);
$session->put('current_branch_id', $branch->id);


$controller = new \App\Http\Controllers\Pos\ItemController();
$response = $controller->importNativeCsv($request);

echo "Response status: " . $response->getStatusCode() . "\n";
echo "Response session all:\n";
print_r($response->getSession() ? $response->getSession()->all() : 'No session on response');

$report = $response->getSession() ? $response->getSession()->get('importReport') : session('importReport');
echo "Branch Admin Import Report:\n";
print_r($report);

if (($report['success'] ?? 0) !== 1 || count($report['failed'] ?? []) !== 1) {
    echo "❌ Branch import mismatch! Expected 1 success and 1 failed duplicate.\n";
    exit(1);
}



echo "✓ Branch Admin importNativeCsv correctly imported 1 new multi-unit item and reported 1 duplicate.\n";
@unlink($tmpCsv2);

// 5. Test Export Streams
echo "\n5. Testing Export and Template Streams...\n";
$templateStream = $importService->generateTemplate('csv', true);
echo "✓ SuperAdmin generateTemplate passed.\n";

$branchExportStream = $importService->exportBranchItems($branch, 'csv', 'total');
echo "✓ SuperAdmin exportBranchItems passed.\n";

echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
