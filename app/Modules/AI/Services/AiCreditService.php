<?php

namespace App\Modules\AI\Services;

use App\Models\AiCreditUsage;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\User;
use Carbon\Carbon;

class AiCreditService
{
    /**
     * Get credit balance and pool summary for an organization and optional branch.
     */
    public static function getSummary(Organization $organization, ?Branch $branch = null): array
    {
        $subscription = $organization->subscription()->with('pricingPlan.capabilities')->first();
        $plan = $subscription?->pricingPlan;
        $capabilities = $plan?->capabilities;

        $poolType = $capabilities?->ai_credit_pool_type ?? 'per_branch';
        $monthlyLimit = $capabilities?->ai_credits_monthly;

        $windowStart = Carbon::now()->startOfMonth();
        $windowEnd   = Carbon::now()->endOfMonth();

        $query = AiCreditUsage::where('organization_id', $organization->id)
            ->whereBetween('used_at', [$windowStart, $windowEnd]);

        if ($poolType === 'per_branch' && $branch) {
            $query->where('branch_id', $branch->id);
        }

        $used = (int) $query->sum('credits_used');

        $isUnmetered = is_null($monthlyLimit);
        $remaining   = $isUnmetered ? null : max(0, $monthlyLimit - $used);
        $isExhausted = !$isUnmetered && $remaining <= 0;

        return [
            'plan_name'     => $plan?->name ?? 'Default (Unset)',
            'pool_type'     => $poolType,
            'monthly_quota' => $monthlyLimit,
            'used'          => $used,
            'remaining'     => $remaining,
            'is_unmetered'  => $isUnmetered,
            'is_exhausted'  => $isExhausted,
            'resets_at'     => $windowEnd->copy()->addDay()->startOfDay()->format('M j, Y'),
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
     * Deduct AI credit(s) and record immutable usage event in ai_credit_usage table.
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

        AiCreditUsage::create([
            'organization_id' => $organization->id,
            'branch_id'       => $branch?->id,
            'user_id'         => $user?->id,
            'used_at'         => now(),
            'feature'         => $feature,
            'credits_used'    => $cost,
            'metadata'        => $metadata,
        ]);

        return true;
    }
}
