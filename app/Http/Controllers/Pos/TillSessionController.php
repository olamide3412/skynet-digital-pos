<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosLog;
use App\Models\TillCashMovement;
use App\Models\TillSession;
use App\Models\User;
use App\Services\RoleService;
use App\Services\TillSessionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TillSessionController extends Controller
{
    /**
     * Get the active till session for the authenticated user at this branch.
     */
    public function active(Request $request)
    {
        $branch = current_branch();
        $user = auth()->user();

        $session = TillSessionService::getActiveSession($user->id, $branch?->id);

        return response()->json([
            'has_active_session' => !empty($session),
            'session'            => $session ? array_merge($session->toArray(), [
                'summary' => $session->calculateSummary(),
            ]) : null,
        ]);
    }

    /**
     * Open a new till session.
     */
    public function open(Request $request)
    {
        $branch = current_branch();
        $user = auth()->user();

        $validated = $request->validate([
            'opening_amount'     => 'required|numeric|min:0',
            'opening_note'       => 'nullable|string|max:500',
            'offline_session_id' => 'nullable|string|max:64',
        ]);

        try {
            $session = TillSessionService::openSession(
                $branch,
                $user,
                (float) $validated['opening_amount'],
                $validated['opening_note'] ?? null,
                $validated['offline_session_id'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Till opened successfully.',
            'session' => array_merge($session->toArray(), [
                'summary' => $session->calculateSummary(),
            ]),
        ]);
    }

    /**
     * Close the user's active till session.
     */
    public function close(Request $request)
    {
        $branch = current_branch();
        $user = auth()->user();

        $validated = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'closing_note'   => 'nullable|string|max:500',
        ]);

        $session = TillSessionService::getActiveSession($user->id, $branch?->id);

        if (!$session) {
            return response()->json([
                'error' => 'No active open till session found to close.',
            ], 422);
        }

        $closedSession = TillSessionService::closeSession(
            $session,
            (float) $validated['closing_amount'],
            $user,
            $validated['closing_note'] ?? null
        );

        return response()->json([
            'message' => 'Till session closed successfully.',
            'session' => array_merge($closedSession->toArray(), [
                'summary' => $closedSession->calculateSummary(),
            ]),
        ]);
    }

    /**
     * Record a mid-shift cash movement (Add / Remove Cash).
     * Gated by permission: canManageTillCashMovements or Branch Admin.
     */
    public function storeMovement(Request $request)
    {
        $user = auth()->user();

        if (!RoleService::canManageTillCashMovements() && !$user->isBranchAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'error' => 'Unauthorized. You do not have permission to manage till cash movements.',
            ], 403);
        }

        $validated = $request->validate([
            'till_session_id' => 'required|exists:till_sessions,id',
            'type'            => 'required|in:add,remove',
            'amount'          => 'required|numeric|min:0.01',
            'reason'          => 'nullable|string|max:255',
        ]);

        $session = TillSession::findOrFail($validated['till_session_id']);

        $movement = TillSessionService::recordCashMovement(
            $session,
            $validated['type'],
            (float) $validated['amount'],
            $validated['reason'] ?? null,
            $user
        );

        return response()->json([
            'message'  => 'Cash movement recorded successfully.',
            'movement' => $movement->load('performer:id,name'),
            'summary'  => $session->calculateSummary(),
        ]);
    }

    /**
     * Branch Admin Oversight: List all till sessions for this branch (§7).
     */
    public function index(Request $request)
    {
        $branch = current_branch();
        $user = auth()->user();

        $canSeeAll = RoleService::canViewAllReports() || $user->isBranchAdmin() || $user->isSuperAdmin();

        $query = TillSession::where('branch_id', $branch->id)
            ->with(['cashier:id,name,username', 'closedBy:id,name', 'cashMovements.performer:id,name'])
            ->when(!$canSeeAll, fn($q) => $q->where('cashier_id', $user->id))
            ->when($request->filled('cashier_id'), fn($q) => $q->where('cashier_id', $request->cashier_id))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('start_date'), fn($q) => $q->whereDate('opened_at', '>=', $request->start_date))
            ->when($request->filled('end_date'), fn($q) => $q->whereDate('opened_at', '<=', $request->end_date))
            ->orderByDesc('opened_at');

        if ($request->wantsJson()) {
            $sessions = $query->paginate(20);
            return response()->json($sessions);
        }

        $sessions = $query->paginate(20)->through(function ($s) {
            return array_merge($s->toArray(), [
                'summary' => $s->calculateSummary(),
            ]);
        });

        $staffUsers = $canSeeAll
            ? User::where('branch_id', $branch->id)->get(['id', 'name', 'username'])
            : [];

        return Inertia::render('Reports/TillSessions', [
            'sessions'   => $sessions,
            'staffUsers' => $staffUsers,
            'canSeeAll'  => $canSeeAll,
            'filters'    => $request->only(['cashier_id', 'status', 'start_date', 'end_date']),
        ]);
    }

    /**
     * Branch Admin Oversight: Force-close a till session on cashier's behalf (§7).
     */
    public function forceClose(Request $request, $branchParam, TillSession $session)
    {
        $branch = current_branch();
        $user = auth()->user();

        if ($session->branch_id !== $branch->id) {
            return response()->json(['error' => 'Branch mismatch.'], 403);
        }

        if (!RoleService::canManageTillCashMovements() && !$user->isBranchAdmin() && !$user->isSuperAdmin()) {
            return response()->json([
                'error' => 'Unauthorized. Only Branch Admins or Managers can force-close till sessions.',
            ], 403);
        }

        $validated = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'closing_note'   => 'nullable|string|max:500',
        ]);

        $closed = TillSessionService::closeSession(
            $session,
            (float) $validated['closing_amount'],
            $user,
            ($validated['closing_note'] ?? '') . " [Force-closed by {$user->name}]"
        );

        return response()->json([
            'message' => 'Till session force-closed successfully.',
            'session' => array_merge($closed->toArray(), [
                'summary' => $closed->calculateSummary(),
            ]),
        ]);
    }

    /**
     * Get till slip print payload (§5).
     */
    public function slip($branchParam, TillSession $session)
    {
        $branch = current_branch();
        if ($session->branch_id !== $branch->id) {
            abort(403);
        }

        $summary = $session->calculateSummary();
        $session->load(['cashier:id,name', 'closedBy:id,name', 'cashMovements.performer:id,name']);

        return response()->json([
            'branch'   => [
                'name'    => $branch->name,
                'address' => $branch->address,
                'phone'   => $branch->phone,
            ],
            'session'  => $session,
            'summary'  => $summary,
        ]);
    }
}
