<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\PricingPlan;
use App\Models\SubscriptionPayment;
use App\Services\Payment\PaymentConfigService;
use App\Services\Payment\PaymentService;
use App\Services\Subscription\SubscriptionBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrgSubscriptionController extends Controller
{
    /**
     * Display current subscription status, feature limits, available plans, and billing history.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $org = $user->organization;

        if (!$org) {
            abort(403, 'User does not belong to an active organization.');
        }

        $subscription = $org->subscription()->with(['pricingPlan.capabilities', 'pricingPlan.features'])->first();

        $subscriptionData = null;
        if ($subscription) {
            $subscriptionData = [
                'id'                 => $subscription->id,
                'pricing_plan_id'    => $subscription->pricing_plan_id,
                'plan_name'          => $subscription->pricingPlan?->name ?? 'None',
                'plan_tagline'       => $subscription->pricingPlan?->tagline,
                'first_year_price'   => $subscription->pricingPlan?->first_year_price,
                'renewal_price'      => $subscription->pricingPlan?->renewal_price,
                'started_at'         => $subscription->started_at?->format('d M Y'),
                'renewal_date'       => $subscription->renewal_date?->format('d M Y'),
                'renewal_date_raw'   => $subscription->renewal_date?->toDateString(),
                'days_until_renewal' => $subscription->daysUntilRenewal(),
                'status'             => $subscription->status,
                'computed_status'    => $subscription->computedStatus(),
                'is_expiring_soon'   => $subscription->isExpiringSoon(),
                'is_past_due'        => $subscription->isPastDue(),
                'renewal_amount'     => SubscriptionBillingService::parsePrice($subscription->pricingPlan?->renewal_price ?: $subscription->pricingPlan?->first_year_price),
            ];
        }

        // Feature Limits & Usage Stats
        $branchesCount = $org->branches()->count();
        $usersCount = $org->users()->count();
        $capabilities = $subscription?->pricingPlan?->capabilities;

        $usageStats = [
            'branches_used'        => $branchesCount,
            'max_branches'         => $capabilities?->max_branches,
            'users_total'          => $usersCount,
            'max_users_per_branch' => $capabilities?->max_users_per_branch,
            'ai_credits_monthly'   => $capabilities?->ai_credits_monthly ?? 0,
            'ai_credit_pool_type'  => $capabilities?->ai_credit_pool_type ?? 'organization',
            'feature_ecommerce'    => (bool) ($capabilities?->feature_ecommerce_integration ?? false),
            'feature_accounting'   => (bool) ($capabilities?->feature_accounting ?? false),
            'feature_multi_branch' => (bool) ($capabilities?->feature_multi_branch ?? false),
            'feature_ai_chat'      => (bool) ($capabilities?->feature_ai_chat ?? false),
        ];

        // All Active Plans with formatted pricing and parsed amounts
        $pricingPlans = PricingPlan::with(['capabilities', 'features'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($p) use ($subscription) {
                return [
                    'id'               => $p->id,
                    'name'             => $p->name,
                    'tagline'          => $p->tagline,
                    'first_year_price' => $p->first_year_price,
                    'renewal_price'    => $p->renewal_price,
                    'branches_limit'   => $p->branches_limit,
                    'users_limit'      => $p->users_limit,
                    'ai_credits'       => $p->ai_credits,
                    'support_level'    => $p->support_level,
                    'is_highlighted'   => (bool) $p->is_highlighted,
                    'sort_order'       => $p->sort_order,
                    'is_current'       => $subscription && $subscription->pricing_plan_id === $p->id,
                    'is_upgrade'       => $subscription ? ($p->sort_order > ($subscription->pricingPlan?->sort_order ?? 0)) : true,
                    'amount_first_year'=> SubscriptionBillingService::parsePrice($p->first_year_price),
                    'amount_renewal'   => SubscriptionBillingService::parsePrice($p->renewal_price ?: $p->first_year_price),
                    'features'         => $p->features->map(fn($f) => [
                        'id'   => $f->id,
                        'text' => $f->feature_text,
                    ]),
                    'capabilities'     => $p->capabilities ? [
                        'max_branches'                  => $p->capabilities->max_branches,
                        'max_users_per_branch'          => $p->capabilities->max_users_per_branch,
                        'ai_credits_monthly'            => $p->capabilities->ai_credits_monthly,
                        'feature_ecommerce_integration' => (bool) $p->capabilities->feature_ecommerce_integration,
                        'feature_accounting'            => (bool) $p->capabilities->feature_accounting,
                        'feature_multi_branch'          => (bool) $p->capabilities->feature_multi_branch,
                        'feature_ai_chat'               => (bool) $p->capabilities->feature_ai_chat,
                    ] : null,
                ];
            });

        // Payment History Ledger
        $payments = $org->subscriptionPayments()
            ->with(['pricingPlan:id,name', 'user:id,name'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function ($pm) {
                return [
                    'id'                => $pm->id,
                    'plan_name'         => $pm->pricingPlan?->name ?? 'Plan',
                    'type'              => $pm->type,
                    'amount_paid'       => $pm->amount_paid,
                    'currency'          => $pm->currency,
                    'gateway'           => $pm->gateway,
                    'gateway_reference' => $pm->gateway_reference,
                    'status'            => $pm->status,
                    'period_months'     => $pm->period_months,
                    'created_at'        => $pm->created_at?->format('d M Y, H:i'),
                    'user_name'         => $pm->user?->name ?? 'Admin',
                ];
            });

        // Available payment gateways
        $gateways = PaymentConfigService::getAvailableGateways();

        return Inertia::render('Org/Subscription/Index', [
            'organization'     => [
                'id'     => $org->id,
                'name'   => $org->name,
                'status' => $org->status,
            ],
            'subscription'     => $subscriptionData,
            'usageStats'       => $usageStats,
            'pricingPlans'     => $pricingPlans,
            'payments'         => $payments,
            'gateways'         => $gateways,
        ]);
    }

    /**
     * Initiate hosted payment checkout for plan renewal or upgrade.
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'pricing_plan_id' => 'nullable|exists:pricing_plans,id',
            'type'            => 'required|in:renewal,upgrade',
            'gateway'         => 'required|string',
        ]);

        $user = Auth::user();
        $org = $user->organization;

        if (!$org) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => 'Active organization required to manage subscriptions.'], 403);
            }
            return back()->with('error', 'Active organization required to manage subscriptions.');
        }

        $planId = $validated['pricing_plan_id'] ?? $org->subscription?->pricing_plan_id;
        if (!$planId) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => 'Pricing plan must be specified.'], 422);
            }
            return back()->with('error', 'Pricing plan must be specified.');
        }

        $plan = PricingPlan::findOrFail($planId);

        // Check gateway availability
        $gateway = PaymentConfigService::getGateway($validated['gateway']);
        if (!$gateway || !$gateway->isEnabled() || !$gateway->isConfigured()) {
            $msg = "Payment gateway '{$validated['gateway']}' is currently unavailable or disabled.";
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        // Calculate amount
        try {
            $amount = SubscriptionBillingService::calculatePrice($org, $plan, $validated['type']);
        } catch (\Throwable $e) {
            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        $reference = 'sub_' . $org->id . '_' . $plan->id . '_' . $validated['type'] . '_' . time() . '_' . substr(uniqid(), 0, 6);

        // 1. Create pending payment record in database
        $payment = SubscriptionBillingService::createPendingPayment(
            org: $org,
            plan: $plan,
            type: $validated['type'],
            amount: $amount,
            gateway: $validated['gateway'],
            reference: $reference,
            user: $user
        );

        $callbackUrl = route('org.subscription.callback', ['reference' => $reference]);

        // 2. Initiate hosted checkout on the selected gateway
        try {
            $checkout = PaymentService::initiatePlanSubscriptionCheckout(
                organization: $org,
                plan: $plan,
                gatewayName: $validated['gateway'],
                amount: $amount,
                type: $validated['type'],
                user: $user,
                callbackUrl: $callbackUrl
            );

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'success'      => true,
                    'checkout_url' => $checkout['checkout_url'],
                    'reference'    => $reference,
                ]);
            }

            return Inertia::location($checkout['checkout_url']);
        } catch (\Throwable $e) {
            $payment->update([
                'status' => 'failed',
                'notes'  => $payment->notes . ' [Checkout init failed: ' . $e->getMessage() . ']',
            ]);

            if ($request->wantsJson() && !$request->header('X-Inertia')) {
                return response()->json(['error' => 'Failed to initiate payment gateway: ' . $e->getMessage()], 500);
            }

            return back()->with('error', 'Failed to initiate payment gateway: ' . $e->getMessage());
        }
    }

    /**
     * Handle return redirect from payment gateway.
     * Verifies payment server-side with idempotency (prevents race conditions with webhook).
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') 
            ?? $request->query('tx_ref') 
            ?? $request->query('trxref');

        if ($reference && str_starts_with($reference, 'sub_')) {
            $payment = SubscriptionPayment::where('gateway_reference', $reference)->first();

            if ($payment && $payment->status === 'successful') {
                return redirect()->route('org.subscription.index')
                    ->with('success', "Payment confirmed! Your subscription {$payment->type} to {$payment->pricingPlan?->name} has been activated.");
            }

            if ($payment && $payment->status === 'pending') {
                $gateway = PaymentConfigService::getGateway($payment->gateway);
                if ($gateway) {
                    try {
                        $verifyResult = $gateway->verifyTransaction($reference);
                        if ($verifyResult['status'] === 'successful') {
                            SubscriptionBillingService::confirmPayment(
                                $reference,
                                $verifyResult['transaction_id'] ?? null,
                                $verifyResult['raw'] ?? []
                            );
                            return redirect()->route('org.subscription.index')
                                ->with('success', "Payment successful! Your subscription {$payment->type} to {$payment->pricingPlan?->name} is now active.");
                        } elseif ($verifyResult['status'] === 'failed') {
                            SubscriptionBillingService::failPayment($reference, 'Payment declined by gateway.');
                            return redirect()->route('org.subscription.index')
                                ->with('error', 'Payment was declined or cancelled at the gateway.');
                        }
                    } catch (\Throwable $e) {
                        // Gateway webhook will catch up in the background
                    }
                }
            }
        }

        return redirect()->route('org.subscription.index')
            ->with('info', 'Subscription payment submitted. It will reflect on your account as soon as confirmed by the gateway.');
    }
}
