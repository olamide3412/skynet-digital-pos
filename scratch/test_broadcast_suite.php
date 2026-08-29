<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminBroadcast;
use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BroadcastService;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

echo "=====================================================\n";
echo "  SUPER ADMIN BROADCAST & EMAIL DELIVERY TEST SUITE  \n";
echo "=====================================================\n\n";

$superAdmin = User::whereHas('roles', fn($q) => $q->where('name', 'super-admin'))->first() ?? User::first();
$branches = Branch::where('is_active', true)->get();
$totalUsers = User::count();

echo "Super Admin: {$superAdmin->name} (Email: {$superAdmin->email})\n";
echo "Active Branches: {$branches->count()} | Total System Users: {$totalUsers}\n\n";

DB::beginTransaction();
try {
    // ── TEST 1: Mail Configuration & Password Encryption ─────────────────────
    echo "[TEST 1] Testing MailConfigService Settings Encryption & Decryption\n";
    $testSmtpPass = 'super-secret-smtp-key-' . rand(1000, 9999);
    MailConfigService::saveSettings([
        'mail_enabled'      => true,
        'mail_host'         => 'smtp.mailtrap.io',
        'mail_port'         => 2525,
        'mail_username'     => 'test_user_broadcast',
        'mail_password'     => $testSmtpPass,
        'mail_encryption'   => 'tls',
        'mail_from_address' => 'alerts@skynetdigitalltd.com',
        'mail_from_name'    => 'Skynet Headquarters',
    ]);

    $savedSettings = MailConfigService::getSettings();
    echo " ✓ Mail enabled: " . ($savedSettings['mail_enabled'] ? 'YES' : 'NO') . "\n";
    echo " ✓ Has password: " . ($savedSettings['has_password'] ? 'YES' : 'NO') . "\n";
    echo " ✓ Host: {$savedSettings['mail_host']}:{$savedSettings['mail_port']}\n";

    $decryptedPass = MailConfigService::getDecryptedPassword();
    if ($decryptedPass !== $testSmtpPass) {
        throw new \Exception("Decrypted password does not match original password! Got: {$decryptedPass}");
    }
    echo " ✓ Decrypted password matches stored value securely.\n";

    // Test dynamic config application
    $applied = MailConfigService::applyDynamicConfig();
    echo " ✓ Dynamic SMTP config applied: " . ($applied ? 'YES' : 'NO') . "\n";
    echo "   Config mail.mailers.smtp.host = " . Config::get('mail.mailers.smtp.host') . "\n";
    echo "   Config mail.from.address = " . Config::get('mail.from.address') . "\n";

    if (Config::get('mail.mailers.smtp.host') !== 'smtp.mailtrap.io') {
        throw new \Exception("Dynamic mail config was not applied to Laravel Config repository!");
    }

    // ── TEST 2: System-wide Broadcast (In-App Only) ─────────────────────────
    echo "\n[TEST 2] Testing System-wide Broadcast (All Users in All Branches)\n";
    $payload1 = [
        'title'       => 'Urgent Policy Update — All Stores',
        'message'     => "Please note that all store end-of-day counts must be completed before 9:00 PM tonight.\nThank you for your cooperation.",
        'target_type' => 'all',
        'target_data' => [],
        'channels'    => ['in_app'],
    ];

    $broadcast1 = BroadcastService::sendBroadcast($payload1, $superAdmin);
    echo " ✓ Broadcast #{$broadcast1->id} created with status: {$broadcast1->status}\n";
    echo " ✓ In-App Notifications generated: {$broadcast1->in_app_count} (Expected: {$totalUsers})\n";

    if ($broadcast1->in_app_count !== $totalUsers) {
        throw new \Exception("Broadcast in-app count ({$broadcast1->in_app_count}) does not match total system users ({$totalUsers})!");
    }

    $sampleNotif = AppNotification::where('reference_type', AdminBroadcast::class)
        ->where('reference_id', $broadcast1->id)
        ->first();

    if (!$sampleNotif || $sampleNotif->type !== 'admin_broadcast') {
        throw new \Exception("AppNotification record not created with type 'admin_broadcast'!");
    }
    echo " ✓ Verified sample AppNotification: [ID: {$sampleNotif->id}] Title: '{$sampleNotif->title}'\n";

    // ── TEST 3: Specific Single Branch Broadcast ─────────────────────────────
    echo "\n[TEST 3] Testing Specific Branch Broadcast\n";
    $targetBranch = $branches->first();
    $targetBranchUsersCount = User::where('branch_id', $targetBranch->id)->count();

    $payload2 = [
        'title'       => "Branch Specific Notice for {$targetBranch->name}",
        'message'     => "Notice regarding local network maintenance for this branch tomorrow.",
        'target_type' => 'branch',
        'target_data' => ['branch_id' => $targetBranch->id],
        'channels'    => ['in_app'],
    ];

    $broadcast2 = BroadcastService::sendBroadcast($payload2, $superAdmin);
    echo " ✓ Branch Broadcast #{$broadcast2->id} dispatched to {$targetBranch->name}\n";
    echo " ✓ In-App Delivered: {$broadcast2->in_app_count} (Expected: {$targetBranchUsersCount})\n";

    if ($broadcast2->in_app_count !== $targetBranchUsersCount) {
        throw new \Exception("Branch targeted broadcast count mismatch!");
    }

    // Verify no notifications created for other branches
    $otherBranchNotifs = AppNotification::where('reference_type', AdminBroadcast::class)
        ->where('reference_id', $broadcast2->id)
        ->where('branch_id', '!=', $targetBranch->id)
        ->count();

    if ($otherBranchNotifs > 0) {
        throw new \Exception("Broadcast leaked into non-targeted branches!");
    }
    echo " ✓ Branch isolation verified: 0 notifications leaked to other branches.\n";

    // ── TEST 4: Role-based Broadcast (Branch Admins Only) ───────────────────
    echo "\n[TEST 4] Testing Role-based Broadcast (Branch Admins Only)\n";
    $branchAdminUsersCount = User::whereHas('roles', fn($q) => $q->where('name', 'branch-admin'))->count();

    $payload3 = [
        'title'       => 'Quarterly Management Review Notice',
        'message'     => 'All Branch Admins must submit inventory summaries by Friday.',
        'target_type' => 'role',
        'target_data' => ['roles' => ['branch-admin']],
        'channels'    => ['in_app'],
    ];

    $broadcast3 = BroadcastService::sendBroadcast($payload3, $superAdmin);
    echo " ✓ Role Broadcast #{$broadcast3->id} dispatched\n";
    echo " ✓ In-App Delivered: {$broadcast3->in_app_count} (Expected: {$branchAdminUsersCount})\n";

    if ($broadcast3->in_app_count !== $branchAdminUsersCount) {
        throw new \Exception("Role targeted broadcast count mismatch!");
    }

    // ── TEST 5: Multi-Channel Broadcast with Email Simulation ────────────────
    echo "\n[TEST 5] Testing Multi-Channel Broadcast (In-App + Email)\n";
    // Fake mail to test delivery count and queue
    Mail::fake();

    $payload4 = [
        'title'       => 'Holiday Operational Hours Announcement',
        'message'     => 'Stores will operate on holiday schedule from December 24 to January 2.',
        'target_type' => 'all',
        'target_data' => [],
        'channels'    => ['in_app', 'email'],
    ];

    $broadcast4 = BroadcastService::sendBroadcast($payload4, $superAdmin);
    echo " ✓ Multi-channel Broadcast #{$broadcast4->id} dispatched\n";
    echo " ✓ In-App: {$broadcast4->in_app_count}\n";
    echo " ✓ Emails Sent: {$broadcast4->email_sent_count}\n";
    echo " ✓ No Email (Skipped): {$broadcast4->no_email_count}\n";
    echo " ✓ Failed Emails: {$broadcast4->email_failed_count}\n";

    Mail::assertSent(\App\Mail\BroadcastNotificationMail::class);
    echo " ✓ Mailer assertSent passed: BroadcastNotificationMail was sent to recipients!\n";

    echo "\n=====================================================\n";
    echo "  ALL BROADCAST & EMAIL TESTS PASSED 100% CLEANLY!   \n";
    echo "=====================================================\n";

    DB::rollBack();
    echo "\n[INFO] Test transaction rolled back safely (Database remains clean).\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n[FAIL] Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
