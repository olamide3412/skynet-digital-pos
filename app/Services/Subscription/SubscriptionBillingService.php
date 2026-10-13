<?php

namespace App\Services\Subscription;

use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionBillingService
{
    /**
     * Parse human-readable price strings like "₦450,000", "300,000 / year" into float.
     */
    public static function parsePrice(string|float|int|null $price): float
    {
        if ($price === null || $price === '') {
            return 0.0;
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        // Remove currency symbols, commas, spaces, and per-year suffix
        $cleaned = preg_replace('/[^\d.]/', '', preg_replace('/\s*\/\s*.*$/', '', (string) $price));

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /**
     * Calculate price for renewal or upgrade.
     */
    public static function calculatePrice(
        Organization $org,
        PricingPlan|string $planOrType,
        PricingPlan|string|int|null $typeOrPlanId = null
    ): array|float {
        if ($planOrType instanceof PricingPlan) {
            $plan = $planOrType;
            $type = (string) $typeOrPlanId;
            $rawPrice = ($type === 'renewal')
                ? ($plan->renewal_price ?: $plan->first_year_price)
                : $plan->first_year_price;

            $amount = self::parsePrice($rawPrice);
            if ($amount <= 0) {
                throw new \InvalidArgumentException("Unable to determine valid billing price for plan {$plan->name}.");
            }
            return $amount;
        }

        // Called as calculatePrice($org, $type, $targetPlanId)
        $type = (string) $planOrType;
        if ($type === 'renewal') {
            $plan = $org->subscription?->pricingPlan;
            if (!$plan) {
                throw new \InvalidArgumentException("No active subscription plan found to renew.");
            }
            $rawPrice = $plan->renewal_price ?: $plan->first_year_price;
        } else {
            $targetId = is_numeric($typeOrPlanId) ? (int) $typeOrPlanId : null;
            $plan = $targetId ? PricingPlan::findOrFail($targetId) : null;
            if (!$plan) {
                throw new \InvalidArgumentException("Target upgrade plan not found.");
            }
            $rawPrice = $plan->first_year_price;
        }

        $amount = self::parsePrice($rawPrice);
        return [
            'amount' => $amount,
            'plan'   => $plan,
            'type'   => $type,
        ];
    }

    /**
     * Create a pending subscription payment record before redirecting to gateway.
     */
    public static function createPendingPayment(
        Organization $org,
        PricingPlan $plan,
        string $type,
        float $amount,
        string $gateway,
        string $reference,
        User|int|null $user = null,
        int $periodMonths = 12,
        string $currency = 'NGN',
        ?int $userId = null
    ): SubscriptionPayment {
        $actualUserId = $userId ?? ($user instanceof User ? $user->id : (is_numeric($user) ? (int) $user : null));

        return SubscriptionPayment::create([
            'organization_id'   => $org->id,
            'pricing_plan_id'   => $plan->id,
            'user_id'           => $actualUserId,
            'type'              => $type,
            'amount_paid'       => $amount,
            'currency'          => $currency,
            'gateway'           => $gateway,
            'gateway_reference' => $reference,
            'status'            => 'pending',
            'period_months'     => $periodMonths,
            'notes'             => "Online {$type} to {$plan->name} via " . ucfirst($gateway),
        ]);
    }

    /**
     * Confirm subscription payment idempotently using row-level database locking.
     */
    public static function confirmPayment(
        string $reference,
        ?string $transactionId = null,
        array $metadata = []
    ): ?SubscriptionPayment {
        return DB::transaction(function () use ($reference, $transactionId, $metadata) {
            $payment = SubscriptionPayment::where('gateway_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::warning("Subscription payment confirmation failed: reference {$reference} not found.");
                return null;
            }

            // Mandatory Idempotency: If already successful, avoid duplicate plan extension
            if ($payment->status === 'successful') {
                Log::info("Subscription payment {$payment->gateway_reference} is already confirmed. Skipping duplicate processing.");
                return $payment;
            }

            // Ensure transaction ID has not already been claimed by another transaction
            if ($transactionId) {
                $duplicateTx = SubscriptionPayment::where('gateway_transaction_id', $transactionId)
                    ->where('id', '!=', $payment->id)
                    ->where('status', 'successful')
                    ->exists();

                if ($duplicateTx) {
                    Log::warning("Subscription payment confirmation aborted: Gateway transaction ID {$transactionId} has already been claimed by another record.");
                    return $payment;
                }
            }

            $org = $payment->organization;
            $plan = $payment->pricingPlan;
            $currentSub = $org->subscription;

            // Calculate dates
            if ($payment->type === 'renewal') {
                // If subscription is still active and renewal_date is in the future, extend from renewal_date
                $baseDate = ($currentSub && $currentSub->renewal_date && $currentSub->daysUntilRenewal() >= 0)
                    ? Carbon::parse($currentSub->renewal_date)
                    : Carbon::today();
                $newRenewalDate = $baseDate->copy()->addMonths($payment->period_months);
                $startedAt = $currentSub?->started_at ?? Carbon::today();
            } else {
                // Upgrade starts fresh today
                $startedAt = Carbon::today();
                $newRenewalDate = Carbon::today()->addMonths($payment->period_months);
            }

            // 1. Update or create active subscription record
            Subscription::updateOrCreate(
                ['organization_id' => $org->id],
                [
                    'pricing_plan_id' => $plan->id,
                    'started_at'      => $startedAt,
                    'renewal_date'    => $newRenewalDate,
                    'status'          => 'active',
                    'changed_by'      => $payment->user_id,
                    'notes'           => "Online {$payment->type} via " . ucfirst($payment->gateway) . " (Ref: {$reference})",
                ]
            );

            // 2. Record in SubscriptionHistory audit ledger
            SubscriptionHistory::create([
                'organization_id' => $org->id,
                'pricing_plan_id' => $plan->id,
                'started_at'      => $startedAt,
                'renewal_date'    => $newRenewalDate,
                'status'          => 'active',
                'changed_by'      => $payment->user_id,
                'notes'           => "Payment confirmed Ref: {$reference}",
            ]);

            // 3. Mark payment as successful
            $payment->update([
                'status'                 => 'successful',
                'gateway_transaction_id' => $transactionId,
                'metadata'               => array_merge($payment->metadata ?? [], $metadata),
            ]);

            // 4. Log to OrganizationActivityLog
            OrganizationActivityLog::create([
                'organization_id' => $org->id,
                'actor_user_id'   => $payment->user_id,
                'action'          => 'subscription_' . $payment->type . '_completed',
                'details'         => [
                    'plan_id'          => $plan->id,
                    'plan_name'        => $plan->name,
                    'amount_paid'      => $payment->amount_paid,
                    'gateway'          => $payment->gateway,
                    'gateway_ref'      => $reference,
                    'transaction_id'   => $transactionId,
                    'new_renewal_date' => $newRenewalDate->toDateString(),
                ],
                'created_at'      => now(),
            ]);

            Log::info("Confirmed subscription {$payment->type} for Org #{$org->id} ({$org->name}) on plan {$plan->name}");

            // Auto-activate accounting for all branches if the plan has accounting
            $orgFresh = $org->fresh(['branches']);
            if ($orgFresh && $orgFresh->hasFeature('accounting')) {
                foreach ($orgFresh->branches as $branch) {
                    \App\Modules\Accounting\Services\AccountSetupService::activateBranch($branch);
                }
            }

            return $payment;
        });
    }

    /**
     * Mark a subscription payment as failed.
     */
    public static function failPayment(string $reference, ?string $reason = null): ?SubscriptionPayment
    {
        return DB::transaction(function () use ($reference, $reason) {
            $payment = SubscriptionPayment::where('gateway_reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$payment || $payment->status === 'successful') {
                return $payment;
            }

            $payment->update([
                'status' => 'failed',
                'notes'  => $payment->notes . ($reason ? " [Failure: {$reason}]" : ''),
            ]);

            return $payment;
        });
    }
}
