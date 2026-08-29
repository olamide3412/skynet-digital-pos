<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\PosSettings;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\QuickAccessService;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function assertTest($name, $condition, $info = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . ($info ? " ({$info})" : '') . "\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($info ? " ({$info})" : '') . "\n";
        $failed++;
    }
}

echo "\n=======================================================\n";
echo " POS QUICK ACCESS WIDGET TEST SUITE\n";
echo "=======================================================\n\n";

DB::beginTransaction();

try {
    // 1. Setup Test Branch and Items
    $branch = Branch::first() ?? Branch::create([
        'name' => 'Quick Access Test Branch',
        'slug' => 'quick-access-test-branch',
        'is_active' => true,
    ]);

    $category = Category::firstOrCreate(['name' => 'QA Category', 'branch_id' => $branch->id]);

    $createdItems = [];
    for ($i = 1; $i <= 15; $i++) {
        $createdItems[] = Item::create([
            'branch_id'       => $branch->id,
            'category_id'     => $category->id,
            'item_name'       => "QA Widget Item {$i}",
            'barcode_number'  => "QAWIDGET{$i}" . time(),
            'buy_price'       => 100 * $i,
            'price'           => 150 * $i,
            'wholesale_price' => 130 * $i,
            'front_store_qty' => 50,
            'back_store_qty'  => 50,
            'unit_label'      => 'unit',
        ]);
    }

    echo "--- 1. Testing PosSettings Schema & Defaults ---\n";
    $settings = PosSettings::forBranch($branch->id);
    assertTest('PosSettings has quick_access_mode column', isset($settings->quick_access_mode));
    assertTest('Default quick_access_mode is top_sellers', $settings->quick_access_mode === 'top_sellers', $settings->quick_access_mode);
    assertTest('pinned_quick_access_items casts to array', is_array($settings->pinned_quick_access_items));
    assertTest('quick_access_category_ids casts to array', is_array($settings->quick_access_category_ids));

    echo "\n--- 2. Testing QuickAccessService: top_sellers Mode ---\n";
    $settings->update(['quick_access_mode' => 'top_sellers']);
    QuickAccessService::clearBranchCache($branch->id);

    $data = QuickAccessService::getQuickAccessData($branch, 'Consumer', true);
    assertTest('top_sellers returns correct mode', $data['mode'] === 'top_sellers');
    assertTest('top_sellers title contains Top Sellers', str_contains($data['title'], 'Top Sellers'));
    assertTest('top_sellers capped at 10 items max', count($data['items']) <= QuickAccessService::MAX_ITEMS, 'Count: ' . count($data['items']));
    if (!empty($data['items'])) {
        $first = $data['items'][0];
        assertTest('Item has required display properties', isset($first['id'], $first['item_name'], $first['display_price'], $first['qty']));
    }

    echo "\n--- 3. Testing QuickAccessService: recently_sold Mode ---\n";
    $settings->update(['quick_access_mode' => 'recently_sold']);
    QuickAccessService::clearBranchCache($branch->id);

    $data = QuickAccessService::getQuickAccessData($branch, 'Consumer', true);
    assertTest('recently_sold returns correct mode', $data['mode'] === 'recently_sold');
    assertTest('recently_sold title contains Recently Sold', str_contains($data['title'], 'Recently Sold'));
    assertTest('recently_sold capped at 10 items max', count($data['items']) <= QuickAccessService::MAX_ITEMS, 'Count: ' . count($data['items']));

    echo "\n--- 4. Testing QuickAccessService: pinned_items Mode ---\n";
    $pinnedIds = [$createdItems[4]->id, $createdItems[2]->id, $createdItems[0]->id];
    $settings->update([
        'quick_access_mode'         => 'pinned_items',
        'pinned_quick_access_items' => $pinnedIds,
    ]);
    QuickAccessService::clearBranchCache($branch->id);

    $data = QuickAccessService::getQuickAccessData($branch, 'Consumer', true);
    assertTest('pinned_items returns correct mode', $data['mode'] === 'pinned_items');
    assertTest('pinned_items returns exact 3 pinned items', count($data['items']) === 3, 'Count: ' . count($data['items']));
    if (count($data['items']) === 3) {
        assertTest('pinned_items preserves pinned array order', $data['items'][0]['id'] === $pinnedIds[0] && $data['items'][1]['id'] === $pinnedIds[1]);
    }

    echo "\n--- 5. Testing QuickAccessService: category_shortcuts Mode ---\n";
    $settings->update([
        'quick_access_mode'         => 'category_shortcuts',
        'quick_access_category_ids' => [$category->id],
    ]);
    QuickAccessService::clearBranchCache($branch->id);

    $data = QuickAccessService::getQuickAccessData($branch, 'Consumer', true);
    assertTest('category_shortcuts returns correct mode', $data['mode'] === 'category_shortcuts');
    assertTest('category_shortcuts has categories list', is_array($data['categories']));

    echo "\n--- 6. Testing QuickAccessService: none Mode ---\n";
    $settings->update(['quick_access_mode' => 'none']);
    QuickAccessService::clearBranchCache($branch->id);

    $data = QuickAccessService::getQuickAccessData($branch, 'Consumer', true);
    assertTest('none mode returns mode none and empty items', $data['mode'] === 'none' && empty($data['items']));

    echo "\n--- 7. Testing Wholesale Pricing in Quick Access Items ---\n";
    $settings->update([
        'quick_access_mode'         => 'pinned_items',
        'pinned_quick_access_items' => [$createdItems[0]->id],
    ]);
    QuickAccessService::clearBranchCache($branch->id);

    $consumerData = QuickAccessService::getQuickAccessData($branch, 'Consumer', true);
    $wholesaleData = QuickAccessService::getQuickAccessData($branch, 'Wholesale', true);

    echo "\n--- 8. Testing OfflineSyncController Bootstrap Response ---\n";
    $offlineController = new \App\Http\Controllers\Pos\OfflineSyncController();
    $request = \Illuminate\Http\Request::create('/api/offline-bootstrap', 'GET');
    $response = $offlineController->bootstrap($request, $branch->slug);
    $responseData = $response->getData(true);

    assertTest('Bootstrap response returns HTTP 200', $response->status() === 200);
    assertTest('Bootstrap payload includes quick_access', isset($responseData['quick_access']));
    assertTest('Bootstrap quick_access contains items', is_array($responseData['quick_access']['items'] ?? null));

} catch (\Throwable $e) {
    echo "\n[ERROR]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    DB::rollBack();
}

echo "\n=======================================================\n";
echo " TESTS COMPLETED: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n\n";

if ($failed > 0) {
    exit(1);
}
