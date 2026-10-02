<?php

namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\AI\Services\AiCreditService;
use App\Modules\AI\Services\AiManager;
use App\Modules\AI\Services\ReorderPredictionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReorderPredictionController extends Controller
{
    /**
     * Display Reorder Prediction Dashboard.
     */
    public function index(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);

        // If Super Admin view with branch filter query
        if (!$branch && $request->has('branch_id')) {
            $branch = Branch::find($request->get('branch_id'));
        }

        $velocityDays  = (int) $request->get('velocity_days', 14);
        $thresholdDays = (int) $request->get('threshold_days', 7);

        $predictionData = ReorderPredictionService::getPredictions($branch, $velocityDays, $thresholdDays);

        $aiManager = app(AiManager::class);
        $isAiConfigured = $aiManager->isBranchEnabled($branch);

        $branches = auth()->user()->is_super_admin
            ? Branch::select(['id', 'name', 'slug'])->orderBy('name')->get()
            : [];

        return Inertia::render('AI/ReorderPredictions', [
            'branch'         => $branch ? ['id' => $branch->id, 'name' => $branch->name, 'slug' => $branch->slug] : null,
            'branches'       => $branches,
            'summary'        => $predictionData['summary'],
            'predictions'    => $predictionData['predictions'],
            'aiInsight'      => null, // Loaded asynchronously on client
            'isAiConfigured' => $isAiConfigured,
            'creditSummary'  => $branch ? $branch->getAiCreditSummary() : null,
            'filters'        => [
                'velocity_days'  => $velocityDays,
                'threshold_days' => $thresholdDays,
                'urgency'        => $request->get('urgency', 'all'),
                'category'       => $request->get('category', 'all'),
                'search'         => $request->get('search', ''),
                'branch_id'      => $branch?->id,
            ],
        ]);
    }

    /**
     * Generate AI insight narrative asynchronously via API.
     */
    public function generateInsight(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);

        if (!$branch && $request->has('branch_id') && $request->get('branch_id')) {
            $branch = Branch::find($request->get('branch_id'));
        }

        $user = auth()->user();
        $org = $branch?->organization ?? $user?->organization;

        $velocityDays  = (int) $request->get('velocity_days', 14);
        $thresholdDays = (int) $request->get('threshold_days', 7);

        $predictionData = ReorderPredictionService::getPredictions($branch, $velocityDays, $thresholdDays);

        $aiManager = app(AiManager::class);
        $isAiConfigured = $aiManager->isBranchEnabled($branch);

        // Credit check: verify remaining AI credits before generating narrative
        if ($org && $user && !$user->is_super_admin) {
            if (!AiCreditService::hasCreditsRemaining($org, $branch, 1)) {
                $summary = AiCreditService::getSummary($org, $branch);
                return response()->json([
                    'insight'        => "Monthly AI credit limit reached ({$summary['used']}/{$summary['monthly_quota']} used). Credits reset on {$summary['resets_at']}. Contact administrator to upgrade.",
                    'is_configured'  => $isAiConfigured,
                    'is_exhausted'   => true,
                    'summary'        => $predictionData['summary'],
                    'credit_summary' => $summary,
                ]);
            }
        }

        $insight = ReorderPredictionService::generateAiInsight($predictionData, $branch);

        // Deduct AI credit on success
        if ($org && $user && !$user->is_super_admin && $insight) {
            AiCreditService::consumeCredit($org, $branch, $user, 'reorder_prediction', 1, [
                'branch_id' => $branch?->id,
            ]);
        }

        return response()->json([
            'insight'        => $insight,
            'is_configured'  => $isAiConfigured,
            'summary'        => $predictionData['summary'],
            'credit_summary' => $org ? AiCreditService::getSummary($org, $branch) : null,
        ]);
    }

    /**
     * Trigger in-app low stock / reorder notifications.
     */
    public function notify(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);
        if (!$branch) {
            return back()->withErrors(['error' => 'Branch not found.']);
        }

        $dispatched = ReorderPredictionService::dispatchReorderNotifications($branch, 10);

        return back()->with('success', "Dispatched {$dispatched} reorder alert notification(s) to staff.");
    }
}
