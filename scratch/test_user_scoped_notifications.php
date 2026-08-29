<?php

require 'c:/Projects/Laravel/skynet_digital_pos/vendor/autoload.php';
$app = require_once 'c:/Projects/Laravel/skynet_digital_pos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\User;
use App\Models\AppNotification;
use App\Services\BroadcastService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

echo "=== STARTING NOTIFICATION USER SCOPING TEST ===\n\n";

$branch = Branch::first();
if (!$branch) {
    echo "No branch found.\n";
    exit(1);
}

// Get or ensure 2 users exist in this branch
$users = User::where('branch_id', $branch->id)->take(2)->get();
if ($users->count() < 2) {
    // create a temporary user for test
    $user2 = User::create([
        'name' => 'Test User 2',
        'email' => 'testuser2_' . rand(1000, 9999) . '@example.com',
        'password' => bcrypt('password'),
        'branch_id' => $branch->id,
        'role' => 'cashier',
    ]);
    $users = User::where('branch_id', $branch->id)->take(2)->get();
}

$userA = $users[0];
$userB = $users[1];

echo "Branch: {$branch->name} (ID: {$branch->id})\n";
echo "User A: {$userA->name} (ID: {$userA->id})\n";
echo "User B: {$userB->name} (ID: {$userB->id})\n\n";

// 1. Dispatch a SuperAdmin Broadcast targeted to this branch
$testTitle = "Unique Broadcast " . rand(100000, 999999);
$testMessage = "Notice for team members at " . now()->toDateTimeString();

echo "1. Sending Broadcast from SuperAdmin targeted to Branch {$branch->id}...\n";
$broadcast = BroadcastService::sendBroadcast([
    'title' => $testTitle,
    'message' => $testMessage,
    'target_type' => 'branches',
    'target_data' => [$branch->id],
    'channels' => ['in_app'],
]);

$totalInsertedForBroadcast = AppNotification::where('reference_id', $broadcast->id)
    ->where('reference_type', \App\Models\AdminBroadcast::class)
    ->count();

echo "Total notification rows created in DB across all branch users: {$totalInsertedForBroadcast}\n";

// 2. Test User A preview
$previewA = NotificationService::getRecentPreview($branch->id, 10, $userA->id);
$matchedA = array_filter($previewA, fn($n) => $n['title'] === $testTitle);
echo "Count of '{$testTitle}' visible in User A's preview: " . count($matchedA) . "\n";

if (count($matchedA) !== 1) {
    echo "❌ ERROR: User A saw " . count($matchedA) . " duplicate notifications instead of exactly 1!\n";
    exit(1);
}
echo "✓ User A sees exactly 1 notification.\n";

// 3. Test User B preview
$previewB = NotificationService::getRecentPreview($branch->id, 10, $userB->id);
$matchedB = array_filter($previewB, fn($n) => $n['title'] === $testTitle);
echo "Count of '{$testTitle}' visible in User B's preview: " . count($matchedB) . "\n";

if (count($matchedB) !== 1) {
    echo "❌ ERROR: User B saw " . count($matchedB) . " duplicate notifications instead of exactly 1!\n";
    exit(1);
}
echo "✓ User B sees exactly 1 notification.\n";

// 4. Test Branch-wide notification (user_id is NULL)
echo "\n4. Testing Branch-wide system alert (user_id = null)...\n";
$systemAlertTitle = "System Alert " . rand(100000, 999999);
AppNotification::create([
    'branch_id' => $branch->id,
    'user_id' => null,
    'type' => 'system_alert',
    'title' => $systemAlertTitle,
    'message' => 'System maintenance notice',
    'is_read' => false,
    'is_resolved' => false,
]);

$previewA2 = NotificationService::getRecentPreview($branch->id, 10, $userA->id);
$hasSysA = count(array_filter($previewA2, fn($n) => $n['title'] === $systemAlertTitle)) === 1;

$previewB2 = NotificationService::getRecentPreview($branch->id, 10, $userB->id);
$hasSysB = count(array_filter($previewB2, fn($n) => $n['title'] === $systemAlertTitle)) === 1;

if (!$hasSysA || !$hasSysB) {
    echo "❌ ERROR: Branch-wide notification not visible to both users!\n";
    exit(1);
}
echo "✓ Branch-wide notification (user_id = null) is visible to both User A and User B.\n";

// 5. Test Mark All as Read for User A only
NotificationService::markAllRead($branch->id, $userA->id);

$unreadA = NotificationService::getUnreadCount($branch->id, $userA->id);
$unreadB = NotificationService::getUnreadCount($branch->id, $userB->id);

echo "\nAfter User A marks all as read:\n";
echo "User A unread count: {$unreadA}\n";
echo "User B unread count: {$unreadB}\n";

if ($unreadA !== 0) {
    echo "❌ ERROR: User A still has unread notifications!\n";
    exit(1);
}
if ($unreadB === 0) {
    echo "❌ ERROR: User A's mark read also cleared User B's unread notifications!\n";
    exit(1);
}

echo "✓ User A marked their notifications read without affecting User B.\n";

echo "\n=== ALL NOTIFICATION SCOPING TESTS PASSED! ===\n";
