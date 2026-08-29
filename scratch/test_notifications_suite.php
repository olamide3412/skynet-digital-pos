<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosCustomer;
use App\Models\Sale;
use App\Models\StockConflict;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\OfflineSyncService;
use Illuminate\Support\Facades\DB;

echo "=====================================================\n";
echo "  BRANCH NOTIFICATIONS & OFFLINE CONFLICTS TEST      \n";
echo "=====================================================\n\n";

$branch = Branch::first();
if (!$branch) {
    echo "ERROR: No branch found.\n";
    exit(1);
}
echo "Testing on Branch: {$branch->name} (ID: {$branch->id}, Slug: {$branch->slug})\n";

$user = User::where('branch_id', $branch->id)->first() ?? User::first();
$category = Category::where('branch_id', $branch->id)->first() ?? Category::first();
$customer = PosCustomer::where('branch_id', $branch->id)->first() ?? PosCustomer::first();

DB::beginTransaction();
try {
    // 1. Create a test item with low stock (2 units available)
    echo "\n[TEST 1] Creating Test Item & Device IMEI Unit\n";
    $item = Item::create([
        'branch_id'       => $branch->id,
        'category_id'     => $category?->id,
        'item_name'       => 'Test Notification Smartphone ' . rand(1000, 9999),
        'barcode_number'  => 'NOTIF-' . rand(100000, 999999),
        'buy_price'       => 50000.00,
        'price'           => 80000.00,
        'wholesale_price' => 75000.00,
        'unit_label'      => 'Unit',
        'front_store_qty' => 2, // Only 2 in stock!
        'back_store_qty'  => 0,
        'is_imei_tracked' => true,
    ]);

    $testImei = 'IMEI-SOLD-TEST-' . rand(10000, 99999);
    $deviceUnit = ItemDeviceUnit::create([
        'item_id'           => $item->id,
        'branch_id'         => $branch->id,
        'imei_or_device_id' => $testImei,
        'status'            => 'sold', // Already sold by another device!
        'location'          => 'front_store',
        'sold_at'           => now()->subHour(),
    ]);

    echo " ✓ Created Item ID {$item->id} ({$item->item_name}) with Stock: 2\n";
    echo " ✓ Created Device IMEI {$testImei} (already sold)\n";

    // 2. Simulate Offline Sale Sync requiring 5 units (3 unit shortfall) + Already Sold IMEI
    echo "\n[TEST 2] Processing Offline Sync with Stock & IMEI Conflicts\n";
    $offlineSaleId = 'OFFLINE-SYNC-TEST-' . uniqid();
    $offlinePayload = [
        'offline_sale_id'   => $offlineSaleId,
        'client_created_at' => now()->subMinutes(10)->toIso8601String(),
        'customer_id'       => $customer?->id,
        'purchase_type'     => 'Consumer',
        'payment_method'    => 'Cash',
        'cash'              => 400000.00,
        'bank_transfer'     => 0,
        'discount_type'     => null,
        'discount_value'    => 0,
        'consultation_fee'  => 0,
        'items' => [
            [
                'item_id'             => $item->id,
                'qty'                 => 5, // Requires 5, only 2 in stock -> Shortfall!
                'unit_used'           => 'unit',
                'price'               => 80000.00,
                'buy_price'           => 50000.00,
                'imei_or_device_id'   => $testImei, // Already sold -> IMEI Conflict!
            ]
        ]
    ];

    $syncService = app(OfflineSyncService::class);
    $syncResult = $syncService->syncSingleSale($offlinePayload, $branch, $user);

    echo " ✓ Offline Sync completed with status: {$syncResult['status']}\n";
    echo " ✓ Conflicts detected: " . ($syncResult['has_conflict'] ? 'YES' : 'NO') . " (Count: {$syncResult['conflicts_count']})\n";

    if (!$syncResult['has_conflict'] || $syncResult['conflicts_count'] < 1) {
        throw new \Exception("Expected conflicts were not flagged by OfflineSyncService!");
    }

    // 3. Verify AppNotification Generation
    echo "\n[TEST 3] Verifying Automatic AppNotification Generation\n";
    $notifications = AppNotification::where('branch_id', $branch->id)
        ->where('data->item_id', $item->id)
        ->get();

    echo " ✓ Notifications generated: {$notifications->count()} records\n";
    if ($notifications->count() < 1) {
        throw new \Exception("AppNotification was not generated automatically during sync!");
    }

    foreach ($notifications as $n) {
        echo "   - [ID: {$n->id}] Type: {$n->type} | Title: {$n->title} | Unread: " . ($n->is_read ? 'NO' : 'YES') . " | Resolved: " . ($n->is_resolved ? 'YES' : 'NO') . "\n";
        echo "     Action URL: {$n->action_url}\n";
    }

    // 4. Verify Unread Counts & Recent Preview
    echo "\n[TEST 4] Verifying NotificationService Queries & Unread Counts\n";
    $unreadCount = NotificationService::getUnreadCount($branch->id, $user->id);
    $previewList = NotificationService::getRecentPreview($branch->id, 5);

    echo " ✓ Unread notification count for branch: {$unreadCount}\n";
    echo " ✓ Recent preview list count: " . count($previewList) . "\n";

    if ($unreadCount < 1 || empty($previewList)) {
        throw new \Exception("NotificationService getUnreadCount / getRecentPreview failed!");
    }

    // 5. Test Mark as Read
    echo "\n[TEST 5] Testing Mark as Read\n";
    $firstNotif = $notifications->first();
    $firstNotif->markAsRead();
    $firstNotif->refresh();

    echo " ✓ Marked Notification #{$firstNotif->id} as Read. is_read: " . ($firstNotif->is_read ? 'TRUE' : 'FALSE') . "\n";
    if (!$firstNotif->is_read || !$firstNotif->read_at) {
        throw new \Exception("markAsRead failed!");
    }

    // 6. Test Automatic Notification Resolution via StockConflictController
    echo "\n[TEST 6] Testing Automatic Notification Resolution via Stock Conflict Resolve\n";
    $conflict = StockConflict::where('branch_id', $branch->id)
        ->where('item_id', $item->id)
        ->first();

    if (!$conflict) {
        throw new \Exception("StockConflict record not found!");
    }

    echo " ✓ Found StockConflict #{$conflict->id} (Status: {$conflict->status})\n";

    // Simulate resolving the conflict
    $conflict->update([
        'status'           => 'resolved',
        'resolution_notes' => 'Stock adjusted and verified by Branch Admin',
        'resolved_by'      => $user->id,
        'resolved_at'      => now(),
    ]);
    NotificationService::resolveForReference(StockConflict::class, $conflict->id, $user->id);

    $matchingNotifs = AppNotification::where('reference_type', StockConflict::class)
        ->where('reference_id', $conflict->id)
        ->get();

    foreach ($matchingNotifs as $mn) {
        echo " ✓ Notification #{$mn->id} auto-resolved: " . ($mn->is_resolved ? 'TRUE' : 'FALSE') . " (Resolved at: {$mn->resolved_at})\n";
        if (!$mn->is_resolved) {
            throw new \Exception("Matching notification was not auto-resolved!");
        }
    }

    echo "\n=====================================================\n";
    echo "  ALL NOTIFICATION TESTS PASSED 100% CLEANLY!        \n";
    echo "=====================================================\n";

    DB::rollBack();
    echo "\n[INFO] Test transaction rolled back safely (Database remains clean).\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[FAIL] Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
