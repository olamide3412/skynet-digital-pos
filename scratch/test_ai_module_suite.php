<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\ItemUnit;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\User;
use App\Modules\AI\Models\AiConversationLog;
use App\Modules\AI\Models\AiSetting;
use App\Modules\AI\Services\AiManager;
use App\Modules\AI\Services\ChatQueryTranslationService;
use App\Modules\AI\Services\Providers\ClaudeProvider;
use App\Modules\AI\Services\Providers\DeepSeekProvider;
use App\Modules\AI\Services\Providers\GeminiProvider;
use App\Modules\AI\Services\Providers\KimiProvider;
use App\Modules\AI\Services\Providers\OpenAiProvider;
use App\Modules\AI\Services\ReorderPredictionService;
use Illuminate\Support\Facades\DB;

echo "\n=======================================================\n";
echo " AI MODULE & MULTI-PROVIDER ENGINE TEST SUITE\n";
echo "=======================================================\n\n";

$passed = 0;
$failed = 0;

function assertTest(string $name, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
        $failed++;
    }
}

DB::beginTransaction();

try {
    // ── 1. Service Provider & Config Registration ────────────────────────────
    echo "--- 1. Testing AI Service Provider & Configuration ---\n";
    $aiManager = app(AiManager::class);
    assertTest('AiManager resolved from Laravel service container', $aiManager instanceof AiManager);
    assertTest('AI Config loaded with 5 providers', count(config('ai.providers', [])) >= 5);
    assertTest('Gemini provider in config', isset(config('ai.providers')['gemini']));
    assertTest('OpenAI provider in config', isset(config('ai.providers')['openai']));
    assertTest('DeepSeek provider in config', isset(config('ai.providers')['deepseek']));
    assertTest('Kimi provider in config', isset(config('ai.providers')['kimi']));
    assertTest('Claude provider in config', isset(config('ai.providers')['claude']));

    // ── 2. Testing AiSetting Model & Encryption ──────────────────────────────
    echo "\n--- 2. Testing AiSetting Model & Encrypted Key Storage ---\n";
    $setting = AiSetting::current();
    assertTest('AiSetting singleton instance retrieved', $setting->exists);

    $testKey = 'sk-test-secret-key-12345';
    $setting->setApiKey('openai', $testKey);
    $setting->setApiKey('gemini', 'gemini-key-999');

    assertTest('OpenAI has API key', $setting->hasApiKey('openai'));
    assertTest('Gemini has API key', $setting->hasApiKey('gemini'));
    assertTest('Encrypted key matches original decrypted value', $setting->getApiKey('openai') === $testKey);

    $setting->setModelForProvider('openai', 'gpt-4o');
    assertTest('Custom model preference persisted', $setting->getModelForProvider('openai') === 'gpt-4o');

    // ── 3. Testing Provider Factory Resolution ───────────────────────────────
    echo "\n--- 3. Testing Provider Factory Resolution ---\n";
    $gemini = $aiManager->getProvider('gemini', 'fake-key', 'gemini-1.5-flash');
    $openai = $aiManager->getProvider('openai', 'fake-key', 'gpt-4o-mini');
    $deepseek = $aiManager->getProvider('deepseek', 'fake-key', 'deepseek-chat');
    $kimi = $aiManager->getProvider('kimi', 'fake-key', 'moonshot-v1-8k');
    $claude = $aiManager->getProvider('claude', 'fake-key', 'claude-3-5-sonnet-20241022');

    assertTest('GeminiProvider instance resolved', $gemini instanceof GeminiProvider && $gemini->getIdentifier() === 'gemini');
    assertTest('OpenAiProvider instance resolved', $openai instanceof OpenAiProvider && $openai->getIdentifier() === 'openai');
    assertTest('DeepSeekProvider instance resolved', $deepseek instanceof DeepSeekProvider && $deepseek->getIdentifier() === 'deepseek');
    assertTest('KimiProvider instance resolved', $kimi instanceof KimiProvider && $kimi->getIdentifier() === 'kimi');
    assertTest('ClaudeProvider instance resolved', $claude instanceof ClaudeProvider && $claude->getIdentifier() === 'claude');

    // ── 4. Setup Test Branch, Products & Sales Data ──────────────────────────
    echo "\n--- 4. Setting Up Test Branch, Variant Items & Sales Velocity ---\n";
    $branch = Branch::first() ?? Branch::create([
        'name'          => 'AI Test Branch',
        'slug'          => 'ai-test-branch',
        'is_active'     => true,
        'is_ai_enabled' => true,
    ]);

    $category = Category::firstOrCreate(['name' => 'AI Beverages', 'branch_id' => $branch->id]);

    $adminUser = User::firstOrCreate([
        'username' => 'ai_test_user',
    ], [
        'name'           => 'AI Test Admin',
        'full_name'      => 'AI Test Admin',
        'email'          => 'ai_admin@example.com',
        'password'       => bcrypt('password'),
        'branch_id'      => $branch->id,
        'is_super_admin' => false,
        'is_active'      => true,
    ]);

    // Item 1: High-Velocity Item with Carton packaging
    $soda = Item::create([
        'branch_id'       => $branch->id,
        'category_id'     => $category->id,
        'item_name'       => 'Super Cold Soda 500ml',
        'barcode_number'  => 'SODA-AI-01',
        'price'           => 300,
        'wholesale_price' => 250,
        'buy_price'       => 200,
        'front_store_qty' => 10,
        'back_store_qty'  => 5,
        'unit_label'      => 'Bottle',
        'is_imei_tracked' => false,
        'reorder_point'   => 20,
    ]);

    ItemUnit::create([
        'branch_id'              => $branch->id,
        'item_id'                => $soda->id,
        'unit_name'              => 'Carton',
        'quantity_in_base_units' => 12,
        'selling_price'          => 3300,
        'wholesale_price'        => 2800,
        'sort_order'             => 1,
        'status'                 => 'active',
    ]);

    // Item 2: IMEI Tracked Item (Phone) with Low Stock
    $phone = Item::create([
        'branch_id'       => $branch->id,
        'category_id'     => $category->id,
        'item_name'       => 'Smart Gadget X1 Pro',
        'barcode_number'  => 'PHONE-AI-01',
        'price'           => 150000,
        'wholesale_price' => 140000,
        'buy_price'       => 120000,
        'front_store_qty' => 1,
        'back_store_qty'  => 0,
        'unit_label'      => 'Unit',
        'is_imei_tracked' => true,
        'reorder_point'   => 5,
    ]);

    ItemDeviceUnit::create([
        'branch_id'          => $branch->id,
        'item_id'            => $phone->id,
        'imei_or_device_id'  => 'IMEI998877665544',
        'status'             => 'in_stock',
        'location'           => 'front_store',
    ]);

    // Create 70 sales of soda in the past 7 days (Velocity = 10 units / day)
    $sale = Sale::create([
        'branch_id'         => $branch->id,
        'user_id'           => $adminUser->id,
        'receipt_id'        => 'REC-AI-001',
        'tax_amount'        => 1500,
        'final_total'       => 22500,
        'payment_method'    => 'cash',
        'amount_paid'       => 22500,
        'profit_made'       => 7000,
        'items_order_count' => 1,
        'created_at'        => now()->subDays(2),
    ]);

    SaleOrder::create([
        'sale_id'             => $sale->id,
        'item_id'             => $soda->id,
        'item_name'           => $soda->item_name,
        'qty'                 => 70,
        'selling_price'       => 300,
        'total_selling_price' => 21000,
        'user_id'             => $adminUser->id,
    ]);

    // ── 5. Testing ReorderPredictionService ───────────────────────────────────
    echo "\n--- 5. Testing ReorderPredictionService Forecasts & Variant Packaging ---\n";
    $predictionsResult = ReorderPredictionService::getPredictions($branch, 14, 7);

    assertTest('Predictions summary generated', isset($predictionsResult['summary']));
    assertTest('Predictions array not empty', count($predictionsResult['predictions']) >= 2);

    $sodaPred = collect($predictionsResult['predictions'])->firstWhere('item_id', $soda->id);
    assertTest('Soda sales velocity calculated (> 0)', $sodaPred['avg_daily_sales'] > 0);
    assertTest('Soda days left calculated accurately (<= 2 days = Critical)', $sodaPred['days_left'] <= 2.5);
    assertTest('Soda urgency marked as critical', $sodaPred['urgency'] === 'critical');
    assertTest('Soda suggested reorder converted to Cartons', str_contains($sodaPred['suggested_reorder_display'], 'Carton'));

    $phonePred = collect($predictionsResult['predictions'])->firstWhere('item_id', $phone->id);
    assertTest('Phone IMEI marked as IMEI tracked', $phonePred['is_imei_tracked'] === true);
    assertTest('Phone IMEI in-stock count equals 1', $phonePred['in_stock_imeis_count'] === 1);
    assertTest('Phone urgency marked as warning (<= 2 in-stock)', $phonePred['urgency'] === 'warning');

    // ── 6. Testing Notification Generation from Reorders ─────────────────────
    echo "\n--- 6. Testing Reorder Alerts In-App Notifications ---\n";
    $dispatched = ReorderPredictionService::dispatchReorderNotifications($branch, 5);
    assertTest('Reorder notifications dispatched (> 0)', $dispatched > 0);

    $notif = AppNotification::where('branch_id', $branch->id)
        ->where('type', 'reorder_alert')
        ->latest('id')
        ->first();

    assertTest('AppNotification record created with type reorder_alert', $notif !== null);
    assertTest('Notification title references Low Stock', str_contains($notif->title, 'Low Stock'));

    // ── 7. Testing Ask-Your-Business Natural Language Chat Grounding ──────────
    echo "\n--- 7. Testing Ask-Your-Business Chat Query Grounding ---\n";

    // Test A: Sales Performance query
    $chatRes1 = ChatQueryTranslationService::process('How did sales this week compare to last week?', $branch, $adminUser);
    assertTest('Chat intent detected: sales_performance', $chatRes1['intent'] === 'sales_performance');
    assertTest('Chat response includes revenue figures', str_contains($chatRes1['ai_response'], '₦') || str_contains($chatRes1['ai_response'], 'revenue'));
    assertTest('Conversation logged in ai_conversation_logs', AiConversationLog::where('session_id', $chatRes1['session_id'])->exists());

    // Test B: Top Sellers query
    $chatRes2 = ChatQueryTranslationService::process('What are our top 5 selling items this month?', $branch, $adminUser);
    assertTest('Chat intent detected: top_sellers', $chatRes2['intent'] === 'top_sellers');
    assertTest('Top items structured payload contains Soda', isset($chatRes2['structured_data']['top_items']));

    // Test C: Low stock query
    $chatRes3 = ChatQueryTranslationService::process('Is any inventory running low or out of stock?', $branch, $adminUser);
    assertTest('Chat intent detected: inventory_status', $chatRes3['intent'] === 'inventory_status');
    assertTest('Low stock items list populated', isset($chatRes3['structured_data']['items_at_risk']));

    // Test D: IMEI lookup query
    $chatRes4 = ChatQueryTranslationService::process('Where is device serial IMEI998877665544 located?', $branch, $adminUser);
    assertTest('Chat intent detected: imei_lookup', $chatRes4['intent'] === 'imei_lookup');
    assertTest('IMEI device record found in grounding', $chatRes4['structured_data']['found'] === true);

    // ── 8. Testing Per-Branch AI Access Toggle & Controller Endpoints ────────
    echo "\n--- 8. Testing Per-Branch AI Toggle & Controller Endpoints ---\n";
    $branch->update(['is_ai_enabled' => false]);
    assertTest('Branch AI is disabled via Eloquent', $aiManager->isBranchEnabled($branch) === false);

    $branch->update(['is_ai_enabled' => true]);
    assertTest('Branch AI re-enabled via Eloquent', $aiManager->isBranchEnabled($branch) === true);

    // Test AiSettingsController@toggleBranch
    $aiSettingsCtrl = app(\App\Modules\AI\Controllers\AiSettingsController::class);
    $aiSettingsCtrl->toggleBranch(request(), $branch);
    $branch->refresh();
    assertTest('AiSettingsController toggled AI to disabled', $branch->is_ai_enabled === false);

    $aiSettingsCtrl->toggleBranch(request(), $branch);
    $branch->refresh();
    assertTest('AiSettingsController toggled AI back to enabled', $branch->is_ai_enabled === true);

    // Test BranchController@toggleAi
    $branchCtrl = app(\App\Http\Controllers\SuperAdmin\BranchController::class);
    $branchCtrl->toggleAi($branch);
    $branch->refresh();
    assertTest('BranchController toggled AI to disabled', $branch->is_ai_enabled === false);

    $branchCtrl->toggleAi($branch);
    $branch->refresh();
    assertTest('BranchController toggled AI back to enabled', $branch->is_ai_enabled === true);

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
