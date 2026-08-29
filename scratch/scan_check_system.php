<?php

require 'c:/Projects/Laravel/skynet_digital_pos/vendor/autoload.php';
$app = require_once 'c:/Projects/Laravel/skynet_digital_pos/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\PosSettings;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Item;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

echo "=====================================================\n";
echo "       SKYNET DIGITAL POS — COMPREHENSIVE SCAN CHECK\n";
echo "=====================================================\n\n";

$errors = 0;

// 1. Database & Schema Check
echo "1. [SCHEMA CHECK]\n";
$criticalColumns = [
    'pos_settings' => ['is_auto_fullscreen_enabled', 'is_offline_enabled', 'is_turnstile_enabled', 'is_price_editable', 'is_qty_deduction'],
    'branches' => ['name', 'slug', 'is_active', 'is_ai_enabled'],
    'items' => ['item_name', 'barcode_number', 'buy_price', 'price', 'reorder_point', 'front_store_qty', 'back_store_qty'],
];

foreach ($criticalColumns as $table => $cols) {
    foreach ($cols as $col) {
        if (!Schema::hasColumn($table, $col)) {
            echo "   ❌ Missing column: {$table}.{$col}\n";
            $errors++;
        } else {
            echo "   ✓ Column verified: {$table}.{$col}\n";
        }
    }
}

// 2. Models & Settings Integrity
echo "\n2. [BRANCHES & POS SETTINGS INTEGRITY]\n";
$branches = Branch::all();
echo "   Total branches found: " . $branches->count() . "\n";
foreach ($branches as $branch) {
    $settings = $branch->getSettings();
    if (!$settings) {
        echo "   ❌ Failed to load PosSettings for branch: {$branch->name}\n";
        $errors++;
    } else {
        echo "   ✓ Branch: {$branch->name} (Auto-Fullscreen: " . ($settings->is_auto_fullscreen_enabled ? 'ON' : 'OFF') . ", Offline: " . ($settings->is_offline_enabled ? 'ON' : 'OFF') . ")\n";
    }
}

// 3. System Branding
echo "\n3. [SYSTEM BRANDING CHECK]\n";
$companyName = SystemSetting::get('company_name', 'Skynet Digital');
$appTagline  = SystemSetting::get('app_tagline', 'Point of Sale');
echo "   ✓ Company Name: {$companyName}\n";
echo "   ✓ App Tagline: {$appTagline}\n";

// 4. Routes Verification
echo "\n4. [CRITICAL ROUTES CHECK]\n";
$requiredRoutes = [
    'pos.index',
    'pos.login',
    'pos.logout',
    'superadmin.branches.index',
    'superadmin.branches.toggle-auto-fullscreen',
    'superadmin.branches.settings.update',
];

foreach ($requiredRoutes as $r) {
    if (!Route::has($r)) {
        echo "   ❌ Route missing: {$r}\n";
        $errors++;
    } else {
        echo "   ✓ Route registered: {$r}\n";
    }
}

echo "\n=====================================================\n";
if ($errors === 0) {
    echo "       ✅ SCAN PASSED WITH 0 ERRORS! EVERYTHING IS OK.\n";
} else {
    echo "       ❌ SCAN DETECTED {$errors} ISSUES.\n";
}
echo "=====================================================\n";
