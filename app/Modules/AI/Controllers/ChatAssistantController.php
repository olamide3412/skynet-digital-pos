<?php

namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\AI\Models\AiConversationLog;
use App\Modules\AI\Services\AiCreditService;
use App\Modules\AI\Services\AiManager;
use App\Modules\AI\Services\ChatQueryTranslationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatAssistantController extends Controller
{
    public function index(Request $request, $branchParam = null)
    {
        $user = auth()->user();
        if ($user && $user->isCashier() && !$user->isSuperAdmin() && !$user->isBranchAdmin()) {
            abort(403, 'Cashiers do not have access to the AI Chat Assistant.');
        }

        if (!$user->is_super_admin) {
            $branch = current_branch() ?: ($user->branch_id ? Branch::find($user->branch_id) : null);
        } else {
            $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);
            if (!$branch && $request->has('branch_id')) {
                $branch = Branch::find($request->get('branch_id'));
            }
        }
        $defaultSessionId = 'sess_user_' . $user->id . ($branch ? '_b' . $branch->id : '');
        $sessionId = $request->get('session_id') ?: $defaultSessionId;

        $logs = AiConversationLog::where('user_id', $user->id)
            ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
            ->when($request->filled('session_id'), fn($q) => $q->where('session_id', $request->get('session_id')))
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($l) => [
                'id'                 => $l->id,
                'role'               => $l->role,
                'message'            => $l->message,
                'detected_intent'    => $l->detected_intent,
                'structured_payload' => $l->structured_payload,
                'provider_used'      => $l->provider_used,
                'model_used'         => $l->model_used,
                'is_ai_generated'    => !in_array($l->provider_used, ['template_fallback', 'local_database_summary', 'local_rules', null, '']),
                'execution_time_ms'  => $l->execution_time_ms,
                'created_at'         => $l->created_at?->format('h:i A'),
            ]);

        $aiManager = app(AiManager::class);
        $isAiConfigured = $aiManager->isBranchEnabled($branch);
        $activeProvider = $aiManager->getActiveProvider();

        $branches = $user->is_super_admin
            ? Branch::select(['id', 'name', 'slug'])->orderBy('name')->get()
            : [];

        return Inertia::render('AI/ChatAssistant', [
            'branch'         => $branch ? ['id' => $branch->id, 'name' => $branch->name, 'slug' => $branch->slug] : null,
            'branches'       => $branches,
            'sessionId'      => $sessionId,
            'initialLogs'    => $logs,
            'isAiConfigured' => $isAiConfigured,
            'creditSummary'  => $branch ? $branch->getAiCreditSummary() : null,
            'activeProvider' => $activeProvider ? [
                'id'    => $activeProvider->getIdentifier(),
                'name'  => $activeProvider->getName(),
                'model' => $activeProvider->getModel(),
            ] : null,
        ]);
    }

    /**
     * Ask a natural language business question.
     */
    public function ask(Request $request, $branchParam = null)
    {
        $data = $request->validate([
            'question'   => 'required|string|max:1000',
            'session_id' => 'nullable|string|max:100',
            'branch_id'  => 'nullable|integer',
        ]);

        $user = auth()->user();
        if ($user && $user->isCashier() && !$user->isSuperAdmin() && !$user->isBranchAdmin()) {
            abort(403, 'Cashiers do not have access to the AI Chat Assistant.');
        }

        if (!$user->is_super_admin) {
            $branch = current_branch() ?: ($user->branch_id ? Branch::find($user->branch_id) : null);
        } else {
            $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);
            if (!$branch && !empty($data['branch_id'])) {
                $branch = Branch::find($data['branch_id']);
            }
        }

        // Credit check: enforce monthly quota per organization's plan
        $org = $branch?->organization ?? $user->organization;
        if ($org && !$user->is_super_admin) {
            if (!AiCreditService::hasCreditsRemaining($org, $branch, 1)) {
                $summary = AiCreditService::getSummary($org, $branch);
                return response()->json([
                    'intent'          => 'credits_exhausted',
                    'response'        => "Monthly AI credit limit reached for your plan ({$summary['used']}/{$summary['monthly_quota']} used). Credits reset on {$summary['resets_at']}. Contact your administrator to upgrade your plan.",
                    'structured_data' => [
                        'error'     => 'credits_exhausted',
                        'quota'     => $summary['monthly_quota'],
                        'used'      => $summary['used'],
                        'resets_at' => $summary['resets_at'],
                    ],
                    'credit_summary'  => $summary,
                ]);
            }
        }

        // Save user message to log
        $defaultSessionId = 'sess_user_' . $user->id . ($branch ? '_b' . $branch->id : '');
        $sessionId = (!empty($data['session_id'])) ? $data['session_id'] : $defaultSessionId;
        AiConversationLog::create([
            'branch_id'   => $branch?->id,
            'user_id'     => $user->id,
            'session_id'  => $sessionId,
            'role'        => 'user',
            'message'     => $data['question'],
        ]);

        $result = ChatQueryTranslationService::process($data['question'], $branch, $user, $sessionId);

        // Deduct AI credit upon successful query execution
        if ($org && !$user->is_super_admin) {
            AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 1, [
                'session_id' => $sessionId,
                'intent'     => $result['intent'] ?? 'unknown',
            ]);
        }

        $result['credit_summary'] = $org ? AiCreditService::getSummary($org, $branch) : null;

        return response()->json($result);
    }

    /**
     * Clear chat session conversation.
     */
    public function clear(Request $request, $branchParam = null)
    {
        $sessionId = $request->get('session_id');
        $user = auth()->user();

        if (!$user->is_super_admin) {
            $branch = current_branch() ?: ($user->branch_id ? Branch::find($user->branch_id) : null);
        } else {
            $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);
            if (!$branch && $request->has('branch_id')) {
                $branch = Branch::find($request->get('branch_id'));
            }
        }

        $query = AiConversationLog::where('user_id', $user->id);
        if ($sessionId) {
            $query->where(function($q) use ($sessionId, $branch) {
                $q->where('session_id', $sessionId)
                  ->when($branch, fn($b) => $b->orWhere('branch_id', $branch->id));
            });
        } elseif ($branch) {
            $query->where('branch_id', $branch->id);
        }
        $query->delete();

        return back()->with('success', 'Conversation cleared.');
    }
}
