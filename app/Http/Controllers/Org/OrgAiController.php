<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Modules\AI\Models\AiConversationLog;
use App\Modules\AI\Services\AiCreditService;
use App\Modules\AI\Services\AiManager;
use App\Modules\AI\Services\ChatQueryTranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OrgAiController extends Controller
{
    /**
     * Resolve the active organization for the current user.
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = Auth::guard('web')->user();

        if ($user->isSuperAdmin()) {
            if ($request->has('org_id')) {
                return Organization::find($request->get('org_id')) ?: Organization::first();
            }
            return $user->organization ?: Organization::first();
        }

        return $user->organization;
    }

    /**
     * Render the Organization AI Assistant.
     */
    public function chat(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $branches = Branch::where('organization_id', $org->id)
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->get();

        $selectedBranch = null;
        if ($request->has('branch_id')) {
            $selectedBranch = $branches->firstWhere('id', (int)$request->get('branch_id'));
        }

        $defaultSessionId = 'sess_org_' . $user->id . '_o' . $org->id;
        $sessionId = $request->get('session_id') ?: $defaultSessionId;

        $branchIds = $branches->pluck('id');

        $logs = AiConversationLog::where('user_id', $user->id)
            ->where(function($q) use ($branchIds, $selectedBranch) {
                if ($selectedBranch) {
                    $q->where('branch_id', $selectedBranch->id);
                } else {
                    $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id');
                }
            })
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
        $activeProvider = $aiManager->getActiveProvider();

        return Inertia::render('Org/AI/ChatAssistant', [
            'organization'   => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
            'branches'       => $branches,
            'selectedBranch' => $selectedBranch,
            'sessionId'      => $sessionId,
            'initialLogs'    => $logs,
            'creditSummary'  => $org ? $org->getAiCreditSummary($selectedBranch) : null,
            'activeProvider' => $activeProvider ? [
                'id'    => $activeProvider->getIdentifier(),
                'name'  => $activeProvider->getName(),
                'model' => $activeProvider->getModel(),
            ] : null,
        ]);
    }

    /**
     * Ask a natural language question scoped strictly to the organization's branches.
     */
    public function ask(Request $request)
    {
        $data = $request->validate([
            'question'   => 'required|string|max:1000',
            'session_id' => 'nullable|string|max:100',
            'branch_id'  => 'nullable|integer',
        ]);

        $user = Auth::guard('web')->user();
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        // Validate branch belongs to this organization
        $branch = null;
        if (!empty($data['branch_id'])) {
            $branch = Branch::where('organization_id', $org->id)->find($data['branch_id']);
        }

        // Credit check: enforce monthly quota per organization's plan
        if (!$user->isSuperAdmin()) {
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
        $defaultSessionId = 'sess_org_' . $user->id . '_o' . $org->id;
        $sessionId = (!empty($data['session_id'])) ? $data['session_id'] : $defaultSessionId;
        AiConversationLog::create([
            'branch_id'  => $branch?->id,
            'user_id'    => $user->id,
            'session_id' => $sessionId,
            'role'       => 'user',
            'message'    => $data['question'],
        ]);

        // Process through translation service with strict scoping
        $result = ChatQueryTranslationService::process($data['question'], $branch, $user, $sessionId);

        // Deduct AI credit upon successful query execution
        if (!$user->isSuperAdmin()) {
            AiCreditService::consumeCredit($org, $branch, $user, 'chat_query', 1, [
                'session_id' => $sessionId,
                'intent'     => $result['intent'] ?? 'unknown',
            ]);
        }

        $result['credit_summary'] = AiCreditService::getSummary($org, $branch);

        return response()->json($result);
    }

    /**
     * Clear chat session logs.
     */
    public function clear(Request $request)
    {
        $user = Auth::guard('web')->user();
        $sessionId = $request->input('session_id');
        $org = $this->resolveOrganization($request);

        $query = AiConversationLog::where('user_id', $user->id);
        if ($sessionId) {
            $query->where('session_id', $sessionId);
        } elseif ($org) {
            $branchIds = Branch::where('organization_id', $org->id)->pluck('id');
            $query->where(function($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id');
            });
        }
        $query->delete();

        return response()->json(['success' => true]);
    }
}
