<?php

require 'c:/Projects/Laravel/skynet_digital_pos/vendor/autoload.php';
$app = require_once 'c:/Projects/Laravel/skynet_digital_pos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\PosSettings;
use Illuminate\Support\Facades\Schema;

echo "=== STARTING AUTO-FULLSCREEN BRANCH SETTING VERIFICATION ===\n\n";

// 1. Check Schema
echo "1. Checking pos_settings table schema for is_auto_fullscreen_enabled column...\n";
if (!Schema::hasColumn('pos_settings', 'is_auto_fullscreen_enabled')) {
    echo "❌ ERROR: Column 'is_auto_fullscreen_enabled' does not exist in 'pos_settings' table!\n";
    exit(1);
}
echo "✓ Schema verified: 'is_auto_fullscreen_enabled' exists in 'pos_settings'.\n\n";

// 2. Check Branch & Model
$branch = Branch::first();
if (!$branch) {
    echo "❌ ERROR: No branch found in DB.\n";
    exit(1);
}
echo "2. Testing with Branch: {$branch->name} (id: {$branch->id})\n";

$settings = $branch->getSettings();
echo "Current is_auto_fullscreen_enabled: " . var_export($settings->is_auto_fullscreen_enabled, true) . "\n";

// Test enabling
echo "Setting is_auto_fullscreen_enabled = true...\n";
$settings->update(['is_auto_fullscreen_enabled' => true]);
$settings->refresh();

if ($settings->is_auto_fullscreen_enabled !== true) {
    echo "❌ ERROR: Failed to save is_auto_fullscreen_enabled = true!\n";
    exit(1);
}
echo "✓ Saved and verified: is_auto_fullscreen_enabled is true.\n";

// Test disabling
echo "Setting is_auto_fullscreen_enabled = false...\n";
$settings->update(['is_auto_fullscreen_enabled' => false]);
$settings->refresh();

if ($settings->is_auto_fullscreen_enabled !== false) {
    echo "❌ ERROR: Failed to save is_auto_fullscreen_enabled = false!\n";
    exit(1);
}
echo "✓ Saved and verified: is_auto_fullscreen_enabled is false.\n";

echo "\n=== ALL AUTO-FULLSCREEN SETTING TESTS PASSED SUCCESSFULLY! ===\n";
