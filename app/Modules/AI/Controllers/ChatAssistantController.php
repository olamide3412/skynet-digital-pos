<?php

namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\AI\Models\AiConversationLog;
use App\Modules\AI\Services\AiManager;
use App\Modules\AI\Services\ChatQueryTranslationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatAssistantController extends Controller
{
    /**
     * Ask-Your-Business AI Assistant Dashboard.
     */
    public function index(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);

        // If Super Admin has selected a specific branch
        if (!$branch && $request->has('branch_id')) {
            $branch = Branch::find($request->get('branch_id'));
        }

        $user = auth()->user();
        $sessionId = $request->get('session_id') ?: ('sess_' . $user->id . '_' . date('Ymd'));

        $logs = AiConversationLog::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get()
            ->map(fn($l) => [
                'id'                 => $l->id,
                'role'               => $l->role,
                'message'            => $l->message,
                'detected_intent'    => $l->detected_intent,
                'structured_payload' => $l->structured_payload,
                'provider_used'      => $l->provider_used,
                'created_at'         => $l->created_at->format('h:i A'),
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

        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->first() : null);
        if (!$branch && !empty($data['branch_id'])) {
            $branch = Branch::find($data['branch_id']);
        }

        $user = auth()->user();

        // Save user message to log
        $sessionId = $data['session_id'] ?: ('sess_' . $user->id . '_' . date('Ymd'));
        AiConversationLog::create([
            'branch_id'   => $branch?->id,
            'user_id'     => $user->id,
            'session_id'  => $sessionId,
            'role'        => 'user',
            'message'     => $data['question'],
        ]);

        $result = ChatQueryTranslationService::process($data['question'], $branch, $user, $sessionId);

        return response()->json($result);
    }

    /**
     * Clear chat session conversation.
     */
    public function clear(Request $request)
    {
        $sessionId = $request->get('session_id');
        $user = auth()->user();

        if ($sessionId) {
            AiConversationLog::where('user_id', $user->id)
                ->where('session_id', $sessionId)
                ->delete();
        }

        return back()->with('success', 'Conversation cleared.');
    }
}
