<?php

namespace App\Modules\AI\Services;

use App\Models\AiCreditPack;
use App\Models\AiCreditTopup;
use App\Models\AiCreditUsage;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiCreditService
{
    /**
     * Get credit balance, plan quota, active top-ups, and pool summary.
     * Note: Plan quota is strictly monthly and expires at the end of each month (does not carry over).
     * Top-ups carry over across months unless an explicit expires_at timestamp is set.
     */
    public static function getSummary(Organization $organization, ?Branch $branch = null): array
    {
        $subscription = $organization->subscription()->with('pricingPlan.capabilities')->first();
        $plan = $subscription?->pricingPlan;
        $capabilities = $plan?->capabilities;

        $poolType = $capabilities?->ai_credit_pool_type ?? 'per_branch';
        $monthlyLimit = $capabilities?->ai_credits_monthly; // null = unmetered (Enterprise)

        // Current calendar month window (Plan quota strictly applies within this window, resets at month end)
        $windowStart = Carbon::now()->startOfMonth();
        $windowEnd   = Carbon::now()->endOfMonth();

        // 1. Current Month's Usage
        $usageQuery = AiCreditUsage::where('organization_id', $organization->id)
            ->whereBetween('used_at', [$windowStart, $windowEnd]);

        if ($poolType === 'per_branch' && $branch) {
            $usageQuery->where('branch_id', $branch->id);
        }

        $usedThisMonth = (int) $usageQuery->sum('credits_used');

        $isUnmetered = is_null($monthlyLimit);

        // 2. Active Top-Up Credits (carries over month-to-month unless an expires_at is set)
        $topupQuery = AiCreditTopup::where('organization_id', $organization->id)->active();

        if ($poolType === 'per_branch' && $branch) {
            $topupQuery->where(function ($q) use ($branch) {
                $q->where('branch_id', $branch->id)
                  ->orWhereNull('branch_id');
            });
        }

        $topupRemaining = (int) $topupQuery->sum('credits_remaining');

        if ($isUnmetered) {
            $planRemaining = null;
            $remaining     = null;
            $isExhausted   = false;
        } else {
            // Plan quota is use-it-or-lose-it for this calendar month:
            $planRemaining = max(0, $monthlyLimit - $usedThisMonth);

            // Total available = Plan quota remaining this month + unspent top-up credits:
            $remaining = $planRemaining + $topupRemaining;
            $isExhausted = $remaining <= 0;
        }

        return [
            'plan_name'          => $plan?->name ?? 'Default (Unset)',
            'pool_type'          => $poolType,
            'monthly_quota'      => $monthlyLimit,
            'used'               => $usedThisMonth,
            'plan_remaining'     => $planRemaining,
            'topup_remaining'    => $topupRemaining,
            'total_allowance'    => $isUnmetered ? null : ($monthlyLimit + $topupRemaining),
            'remaining'          => $remaining,
            'is_unmetered'       => $isUnmetered,
            'is_exhausted'       => $isExhausted,
            'resets_at'          => $windowEnd->copy()->addDay()->startOfDay()->format('M j, Y'),
        ];
    }

    /**
     * Check if the organization/branch has enough AI credits to execute an operation.
     */
    public static function hasCreditsRemaining(Organization $organization, ?Branch $branch = null, int $cost = 1): bool
    {
        $summary = self::getSummary($organization, $branch);

        if ($summary['is_unmetered']) {
            return true;
        }

        return ($summary['remaining'] ?? 0) >= $cost;
    }

    /**
     * Deduct AI credit(s).
     * Consumes from the current month's Plan Quota first (since it expires at month end).
     * If the Plan Quota is exhausted (or insufficient for $cost), the remainder is deducted
     * from active Top-Up packs (depleting credits_remaining), ordered by earliest expiration.
     */
    public static function consumeCredit(
        Organization $organization,
        ?Branch $branch,
        ?User $user,
        string $feature = 'chat_query',
        int $cost = 1,
        array $metadata = []
    ): bool {
        if (!self::hasCreditsRemaining($organization, $branch, $cost)) {
            return false;
        }

        $summary = self::getSummary($organization, $branch);

        return DB::transaction(function () use ($organization, $branch, $user, $feature, $cost, $metadata, $summary) {
            $deductedFromTopup = 0;
            $deductedFromPlan  = 0;

            if ($summary['is_unmetered']) {
                $deductedFromPlan = $cost;
            } else {
                $planRemaining = $summary['plan_remaining'] ?? 0;

                if ($planRemaining >= $cost) {
                    // Fully covered by monthly plan quota
                    $deductedFromPlan = $cost;
                } else {
                    // Plan quota partially or fully exhausted:
                    $deductedFromPlan = max(0, $planRemaining);
                    $shortfall = $cost - $deductedFromPlan;

                    // Deduct shortfall from top-up packs
                    $subscription = $organization->subscription()->with('pricingPlan.capabilities')->first();
                    $poolType = $subscription?->pricingPlan?->capabilities?->ai_credit_pool_type ?? 'per_branch';

                    $topupQuery = AiCreditTopup::where('organization_id', $organization->id)
                        ->active()
                        ->lockForUpdate();

                    if ($poolType === 'per_branch' && $branch) {
                        // Prioritize branch-specific topups first, then org-wide fallback topups
                        $topupQuery->where(function ($q) use ($branch) {
                            $q->where('branch_id', $branch->id)
                              ->orWhereNull('branch_id');
                        })
                        ->orderByRaw('CASE WHEN branch_id IS NOT NULL THEN 0 ELSE 1 END')
                        ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('expires_at')
                        ->orderBy('id');
                    } else {
                        $topupQuery->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                            ->orderBy('expires_at')
                            ->orderBy('id');
                    }

                    $topups = $topupQuery->get();
                    $needed = $shortfall;

                    foreach ($topups as $topup) {
                        if ($needed <= 0) {
                            break;
                        }

                        $take = min($needed, $topup->credits_remaining);
                        $topup->decrement('credits_remaining', $take);
                        $needed -= $take;
                        $deductedFromTopup += $take;
                    }
                }
            }

            // Record immutable usage log
            $finalMetadata = array_merge($metadata, [
                'deducted_from_plan'  => $deductedFromPlan,
                'deducted_from_topup' => $deductedFromTopup,
            ]);

            AiCreditUsage::create([
                'organization_id' => $organization->id,
                'branch_id'       => $branch?->id,
                'user_id'         => $user?->id,
                'used_at'         => now(),
                'feature'         => $feature,
                'credits_used'    => $cost,
                'metadata'        => $finalMetadata,
            ]);

            return true;
        });
    }

    /**
     * Helper to grant a new top-up to an organization or branch.
     */
    public static function grantTopup(
        Organization $organization,
        int $credits,
        ?Branch $branch = null,
        ?User $grantedBy = null,
        ?Carbon $expiresAt = null,
        string $type = 'manual_grant',
        ?string $notes = null
    ): AiCreditTopup {
        return AiCreditTopup::create([
            'organization_id'   => $organization->id,
            'branch_id'         => $branch?->id,
            'credits'           => $credits,
            'credits_remaining' => $credits,
            'type'              => $type,
            'notes'             => $notes,
            'granted_by'        => $grantedBy?->id,
            'expires_at'        => $expiresAt,
            'status'            => 'successful',
        ]);
    }

    /**
     * Create a pending purchase topup record.
     */
    public static function createPendingPurchase(
        Organization $organization,
        AiCreditPack $pack,
        string $gateway,
        string $gatewayReference,
        ?Branch $branch = null,
        ?User $user = null
    ): AiCreditTopup {
        $subscription = $organization->subscription()->with('pricingPlan.capabilities')->first();
        $poolType = $subscription?->pricingPlan?->capabilities?->ai_credit_pool_type ?? 'per_branch';

        // For Starter / account-wide plans, force branch_id to null (shared org pool)
        $targetBranchId = ($poolType === 'account_wide' || empty($branch)) ? null : $branch->id;

        return AiCreditTopup::create([
            'organization_id'   => $organization->id,
            'branch_id'         => $targetBranchId,
            'credit_pack_id'    => $pack->id,
            'credits'           => $pack->credits,
            'credits_remaining' => $pack->credits,
            'amount_paid'       => $pack->price,
            'currency'          => $pack->currency,
            'gateway'           => $gateway,
            'gateway_reference' => $gatewayReference,
            'status'            => 'pending',
            'type'              => 'addon_purchase',
            'notes'             => "Purchased {$pack->name} via " . ucfirst($gateway),
            'granted_by'        => $user?->id,
            'expires_at'        => null, // Purchased credits never expire at month-end
        ]);
    }

    /**
     * Confirm a topup purchase idempotently.
     */
    public static function confirmPurchase(
        string $gatewayReference,
        ?string $transactionId = null,
        ?array $metadata = null
    ): ?AiCreditTopup {
        return DB::transaction(function () use ($gatewayReference, $transactionId, $metadata) {
            $topup = AiCreditTopup::where('gateway_reference', $gatewayReference)
                ->lockForUpdate()
                ->first();

            if (!$topup) {
                return null;
            }

            // Mandatory Idempotency: if already successful, do not double-credit
            if ($topup->status === 'successful') {
                return $topup;
            }

            $topup->update([
                'status' => 'successful',
                'notes'  => $topup->notes . ($transactionId ? " (TxID: {$transactionId})" : ''),
            ]);

            \App\Services\ActivityLogger::credit(
                "Confirmed AI credit pack purchase of {$topup->credits} credits via {$topup->gateway} (Ref: {$gatewayReference})",
                $topup->branch_id,
                ['organization_id' => $topup->organization_id, 'topup_id' => $topup->id]
            );

            return $topup;
        });
    }

    /**
     * Mark a topup purchase as failed.
     */
    public static function failPurchase(string $gatewayReference, ?string $reason = null): ?AiCreditTopup
    {
        return DB::transaction(function () use ($gatewayReference, $reason) {
            $topup = AiCreditTopup::where('gateway_reference', $gatewayReference)
                ->lockForUpdate()
                ->first();

            if (!$topup) {
                return null;
            }

            if ($topup->status === 'successful') {
                return $topup; // Cannot fail an already completed transaction
            }

            $topup->update([
                'status' => 'failed',
                'notes'  => $topup->notes . ($reason ? " [Failed: {$reason}]" : ' [Failed]'),
            ]);

            return $topup;
        });
    }
}
