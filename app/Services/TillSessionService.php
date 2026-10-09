<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\TillCashMovement;
use App\Models\TillSession;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TillSessionService
{
    /**
     * Get the active open till session for a specific user, optionally at a branch.
     */
    public static function getActiveSession(int $userId, ?int $branchId = null): ?TillSession
    {
        return TillSession::with(['cashier:id,name,username', 'cashMovements.performer:id,name'])
            ->where('cashier_id', $userId)
            ->where('status', 'open')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->latest('id')
            ->first();
    }

    /**
     * Open a new till session.
     * Enforces the rule that a cashier can only have ONE open till session at a time system-wide (§11.4).
     */
    public static function openSession(
        Branch $branch,
        User $user,
        float $openingAmount,
        ?string $openingNote = null,
        ?string $offlineSessionId = null
    ): TillSession {
        return DB::transaction(function () use ($branch, $user, $openingAmount, $openingNote, $offlineSessionId) {
            // Check if cashier already has an open session anywhere
            $existingOpen = TillSession::where('cashier_id', $user->id)
                ->where('status', 'open')
                ->first();

            if ($existingOpen) {
                throw new \InvalidArgumentException(
                    "You already have an open till session (Session #{$existingOpen->id}). Please close it before opening a new one."
                );
            }

            // Create new till session
            $session = TillSession::create([
                'branch_id'          => $branch->id,
                'cashier_id'         => $user->id,
                'opening_amount'     => max(0, round($openingAmount, 2)),
                'opened_at'          => now(),
                'status'             => 'open',
                'opening_note'       => $openingNote,
                'offline_session_id' => $offlineSessionId,
                'sync_status'        => 'synced',
            ]);

            // Audit log
            ActivityLogger::log(
                'till_session',
                "Cashier {$user->name} opened till session #{$session->id} with starting float ₦" . number_format($session->opening_amount, 2),
                $branch->id,
                [
                    'till_session_id' => $session->id,
                    'opening_amount'  => $session->opening_amount,
                    'cashier_id'      => $user->id,
                ],
                $user->id
            );

            return $session;
        });
    }

    /**
     * Record a mid-shift cash movement (Add / Remove) for an open till session (§4A).
     */
    public static function recordCashMovement(
        TillSession $session,
        string $type,
        float $amount,
        ?string $reason,
        User $performer
    ): TillCashMovement {
        if (!$session->isOpen()) {
            throw ValidationException::withMessages([
                'amount' => 'Cannot record cash movement on a closed till session.',
            ]);
        }

        $type = strtolower($type);
        if (!in_array($type, ['add', 'remove'])) {
            throw ValidationException::withMessages([
                'type' => "Invalid movement type. Must be 'add' or 'remove'.",
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Movement amount must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($session, $type, $amount, $reason, $performer) {
            $movement = TillCashMovement::create([
                'till_session_id' => $session->id,
                'type'            => $type,
                'amount'          => round($amount, 2),
                'reason'          => $reason,
                'performed_by'    => $performer->id,
            ]);

            $verb = $type === 'add' ? 'added to' : 'removed from';
            ActivityLogger::log(
                'till_cash_movement',
                "₦" . number_format($amount, 2) . " cash {$verb} till session #{$session->id} by {$performer->name}" . ($reason ? " (Reason: {$reason})" : ''),
                $session->branch_id,
                [
                    'till_session_id' => $session->id,
                    'movement_id'     => $movement->id,
                    'type'            => $type,
                    'amount'          => $amount,
                    'performed_by'    => $performer->id,
                    'reason'          => $reason,
                ],
                $performer->id
            );

            return $movement;
        });
    }

    /**
     * Close a till session and record cash reconciliation (§5).
     */
    public static function closeSession(
        TillSession $session,
        float $closingAmount,
        ?User $closedByUser = null,
        ?string $closingNote = null
    ): TillSession {
        if (!$session->isOpen()) {
            throw ValidationException::withMessages([
                'closing_amount' => 'This till session is already closed.',
            ]);
        }

        return DB::transaction(function () use ($session, $closingAmount, $closedByUser, $closingNote) {
            $summary = $session->calculateSummary();
            $expectedCash = $summary['expected_cash'];
            $actualCounted = max(0, round($closingAmount, 2));
            $variance = round($actualCounted - $expectedCash, 2);

            $closer = $closedByUser ?? auth()->user();

            $session->update([
                'closing_amount'  => $actualCounted,
                'expected_amount' => $expectedCash,
                'variance'        => $variance,
                'status'          => 'closed',
                'closed_at'       => now(),
                'closed_by'       => $closer?->id,
                'closing_note'    => $closingNote,
            ]);

            $varianceDesc = $variance > 0 
                ? "+₦" . number_format($variance, 2) . " (Over)" 
                : ($variance < 0 ? "-₦" . number_format(abs($variance), 2) . " (Short)" : "₦0.00 (Exact)");

            ActivityLogger::log(
                'till_session',
                "Till session #{$session->id} closed by {$closer?->name}. Counted: ₦" . number_format($actualCounted, 2) . ", Expected: ₦" . number_format($expectedCash, 2) . ", Variance: {$varianceDesc}",
                $session->branch_id,
                [
                    'till_session_id' => $session->id,
                    'closing_amount'  => $actualCounted,
                    'expected_amount' => $expectedCash,
                    'variance'        => $variance,
                    'closed_by'       => $closer?->id,
                ],
                $closer?->id
            );

            $closedSession = $session->fresh();

            // Double-entry accounting post (Rules 15 & 16: Till variance)
            \App\Modules\Accounting\Services\SalePostingService::postTillClose($closedSession);

            return $closedSession;
        });
    }
}
