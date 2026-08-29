<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Branch;
use App\Models\User;
use App\Modules\AI\Controllers\ReorderPredictionController;
use Illuminate\Http\Request;

echo "\n=======================================================\n";
echo " AI REORDER PREDICTIONS ASYNC & MARKDOWN TEST\n";
echo "=======================================================\n\n";

function assertTest(string $title, bool $pass, string $detail = '') {
    if ($pass) {
        echo "  [PASS] {$title}\n";
    } else {
        echo "  [FAIL] {$title}" . ($detail ? " — {$detail}" : "") . "\n";
    }
}

$branch = Branch::first();
$adminUser = User::where('is_super_admin', true)->first() ?: User::first();
auth()->login($adminUser);

$controller = app(ReorderPredictionController::class);

// 1. Test fast initial page load (aiInsight is null on index)
$indexResponse = $controller->index(new Request(), $branch->slug);
$pageProps = $indexResponse->toResponse(request())->getOriginalContent()->getData()['page']['props'];

assertTest('Initial page index returns null aiInsight for fast load', $pageProps['aiInsight'] === null);
assertTest('Predictions array loaded', is_array($pageProps['predictions']));
assertTest('Summary stats present', isset($pageProps['summary']));

// 2. Test async generateInsight endpoint for Branch
$asyncReq = new Request([
    'velocity_days'  => 14,
    'threshold_days' => 7,
]);
$insightResponse = $controller->generateInsight($asyncReq, $branch->slug);
$insightData = $insightResponse->getData(true);

assertTest('Async generateInsight returns HTTP 200 JSON', $insightResponse->getStatusCode() === 200);
assertTest('Insight contains narrative text', !empty($insightData['insight']));
assertTest('Insight contains Markdown bolding/headings', str_contains($insightData['insight'], '**') || str_contains($insightData['insight'], '###') || str_contains($insightData['insight'], 'Summary'));

// 3. Test async generateInsight endpoint for Super Admin cross-branch (branchParam null)
$superReq = new Request([
    'velocity_days'  => 14,
    'threshold_days' => 7,
]);
$superInsightResponse = $controller->generateInsight($superReq, null);
$superInsightData = $superInsightResponse->getData(true);

assertTest('Superadmin cross-branch async insight generated', !empty($superInsightData['insight']));

echo "\n=======================================================\n";
echo " ALL ASYNC & REORDER PREDICTION TESTS COMPLETED!\n";
echo "=======================================================\n\n";
