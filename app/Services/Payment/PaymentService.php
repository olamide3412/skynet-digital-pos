<?php

namespace App\Services\Payment;

use App\Models\AiCreditPack;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\PricingPlan;
use App\Models\User;
use App\Modules\AI\Services\AiCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Initiate checkout session for purchasing an AI credit pack.
     *
     * @return array ['checkout_url' => string, 'reference' => string, 'topup' => AiCreditTopup]
     */
    public static function initiateAiCreditPackCheckout(
        Organization $organization,
        AiCreditPack $pack,
        string $gatewayName,
        ?Branch $branch,
        ?User $user,
        string $callbackUrl
    ): array {
        $gateway = PaymentConfigService::getGateway($gatewayName);
        if (!$gateway || !$gateway->isEnabled() || !$gateway->isConfigured()) {
            throw new \InvalidArgumentException("Payment gateway '{$gatewayName}' is currently unavailable or disabled.");
        }

        $reference = 'aic_' . $organization->id . '_' . uniqid() . '_' . time();

        // 1. Create pending record in unified ledger
        $topup = AiCreditService::createPendingPurchase(
            $organization,
            $pack,
            $gateway->getIdentifier(),
            $reference,
            $branch,
            $user
        );

        // 2. Initiate hosted checkout on the selected gateway
        try {
            $checkoutResult = $gateway->initiateCheckout([
                'reference'    => $reference,
                'amount'       => (float) $pack->price,
                'currency'     => $pack->currency ?: 'NGN',
                'email'        => $user?->email ?: $organization->email ?: 'admin@example.com',
                'name'         => $user?->name ?: $organization->name ?: 'Organization Admin',
                'title'        => "AI Credits: {$pack->name}",
                'description'  => "Purchase {$pack->credits} AI Credits for {$organization->name}",
                'callback_url' => $callbackUrl,
                'metadata'     => [
                    'payment_type'    => 'ai_credit_topup',
                    'topup_id'        => $topup->id,
                    'organization_id' => $organization->id,
                    'pack_id'         => $pack->id,
                    'branch_id'       => $branch?->id,
                ],
            ]);

            return [
                'checkout_url' => $checkoutResult['checkout_url'],
                'reference'    => $reference,
                'topup'        => $topup,
            ];
        } catch (\Throwable $e) {
            // Mark topup as failed if API call aborted
            $topup->update([
                'status' => 'failed',
                'notes'  => $topup->notes . ' [Checkout init failed: ' . $e->getMessage() . ']',
            ]);
            throw $e;
        }
    }

    /**
     * Initiate hosted checkout session for Subscription Plan Renewal or Plan Upgrade.
     * Reusable payment pipeline supporting Flutterwave and Paystack.
     *
     * @return array ['checkout_url' => string, 'reference' => string]
     */
    public static function initiatePlanSubscriptionCheckout(
        Organization $organization,
        PricingPlan $plan,
        string $gatewayName,
        float $amount,
        string $type, // 'renewal' or 'upgrade'
        ?User $user,
        string $callbackUrl
    ): array {
        $gateway = PaymentConfigService::getGateway($gatewayName);
        if (!$gateway || !$gateway->isEnabled() || !$gateway->isConfigured()) {
            throw new \InvalidArgumentException("Payment gateway '{$gatewayName}' is currently unavailable or disabled.");
        }

        $reference = 'sub_' . $organization->id . '_' . $plan->id . '_' . uniqid() . '_' . time();

        $checkoutResult = $gateway->initiateCheckout([
            'reference'    => $reference,
            'amount'       => $amount,
            'currency'     => 'NGN',
            'email'        => $user?->email ?: $organization->email ?: 'admin@example.com',
            'name'         => $user?->name ?: $organization->name ?: 'Organization Admin',
            'title'        => 'Plan ' . ucfirst($type) . ": {$plan->name}",
            'description'  => "Subscription {$type} to {$plan->name} for {$organization->name}",
            'callback_url' => $callbackUrl,
            'metadata'     => [
                'payment_type'        => 'plan_subscription',
                'subscription_action' => $type,
                'organization_id'     => $organization->id,
                'plan_id'             => $plan->id,
                'user_id'             => $user?->id,
            ],
        ]);

        return [
            'checkout_url' => $checkoutResult['checkout_url'],
            'reference'    => $reference,
        ];
    }

    /**
     * Process an incoming webhook from any supported gateway.
     * Handles signature validation, normalized payload parsing, and idempotency.
     */
    public static function processWebhook(string $gatewayName, Request $request): array
    {
        $gateway = PaymentConfigService::getGateway($gatewayName);
        if (!$gateway) {
            Log::warning("Received webhook for unrecognized gateway: {$gatewayName}");
            return ['success' => false, 'error' => 'unknown_gateway', 'status_code' => 400];
        }

        // 1. Validate signature
        if (!$gateway->verifyWebhookSignature($request)) {
            Log::warning("Invalid webhook signature received from {$gatewayName}", [
                'headers' => $request->headers->all(),
                'ip'      => $request->ip(),
            ]);
            return ['success' => false, 'error' => 'invalid_signature', 'status_code' => 401];
        }

        // 2. Parse payload
        $parsed = $gateway->parseWebhookPayload($request);
        if (!$parsed || empty($parsed['reference'])) {
            Log::info("Webhook from {$gatewayName} contained no actionable reference");
            return ['success' => true, 'message' => 'no_reference_found', 'status_code' => 200];
        }

        $reference = $parsed['reference'];
        $status    = $parsed['status'];

        Log::info("Processing verified webhook from {$gatewayName}", [
            'reference' => $reference,
            'status'    => $status,
            'amount'    => $parsed['amount'],
            'tx_id'     => $parsed['transaction_id'],
        ]);

        // 3. Dispatch by reference type or metadata
        // A) AI Credit Top-up (starts with aic_ or exists in ai_credit_topups table)
        if (str_starts_with($reference, 'aic_') || ($parsed['metadata']['payment_type'] ?? '') === 'ai_credit_topup') {
            if ($status === 'successful') {
                $existing = \App\Models\AiCreditTopup::where('gateway_reference', $reference)->first();
                if ($existing && $existing->status === 'successful') {
                    Log::info("Webhook received duplicate event for already processed AI credit purchase {$reference}");
                    return [
                        'success'     => true,
                        'action'      => 'ai_credit_already_confirmed',
                        'reference'   => $reference,
                        'topup_id'    => $existing->id,
                        'status_code' => 200,
                    ];
                }

                $confirmed = AiCreditService::confirmPurchase(
                    $reference,
                    $parsed['transaction_id'],
                    $parsed['metadata']
                );
                return [
                    'success'     => true,
                    'action'      => 'ai_credit_confirmed',
                    'reference'   => $reference,
                    'topup_id'    => $confirmed?->id,
                    'status_code' => 200,
                ];
            } elseif ($status === 'failed') {
                $existing = \App\Models\AiCreditTopup::where('gateway_reference', $reference)->first();
                if ($existing && $existing->status === 'successful') {
                    Log::warning("Ignored failed webhook for AI credit purchase {$reference} because it was already confirmed successful.");
                    return [
                        'success'     => true,
                        'action'      => 'already_successful_ignored_failure',
                        'reference'   => $reference,
                        'status_code' => 200,
                    ];
                }

                AiCreditService::failPurchase($reference, 'Payment declined or cancelled at gateway.');
                return [
                    'success'     => true,
                    'action'      => 'ai_credit_failed',
                    'reference'   => $reference,
                    'status_code' => 200,
                ];
            }
        }

        // B) Plan Renewal / Subscription Upgrades
        if (str_starts_with($reference, 'sub_') || ($parsed['metadata']['payment_type'] ?? '') === 'plan_subscription') {
            if ($status === 'successful') {
                $existing = \App\Models\SubscriptionPayment::where('gateway_reference', $reference)->first();
                if ($existing && $existing->status === 'successful') {
                    Log::info("Webhook received duplicate event for already processed subscription payment {$reference}");
                    return [
                        'success'     => true,
                        'action'      => 'subscription_already_confirmed',
                        'reference'   => $reference,
                        'payment_id'  => $existing->id,
                        'status_code' => 200,
                    ];
                }

                $confirmed = \App\Services\Subscription\SubscriptionBillingService::confirmPayment(
                    $reference,
                    $parsed['transaction_id'],
                    $parsed['metadata'] ?? []
                );
                return [
                    'success'     => true,
                    'action'      => 'subscription_confirmed',
                    'reference'   => $reference,
                    'payment_id'  => $confirmed?->id,
                    'status_code' => 200,
                ];
            } elseif ($status === 'failed') {
                $existing = \App\Models\SubscriptionPayment::where('gateway_reference', $reference)->first();
                if ($existing && $existing->status === 'successful') {
                    Log::warning("Ignored failed webhook for subscription payment {$reference} because it was already confirmed successful.");
                    return [
                        'success'     => true,
                        'action'      => 'already_successful_ignored_failure',
                        'reference'   => $reference,
                        'status_code' => 200,
                    ];
                }

                \App\Services\Subscription\SubscriptionBillingService::failPayment($reference, 'Payment declined or cancelled at gateway.');
                return [
                    'success'     => true,
                    'action'      => 'subscription_failed',
                    'reference'   => $reference,
                    'status_code' => 200,
                ];
            }
        }

        return [
            'success'     => true,
            'message'     => 'event_processed',
            'status_code' => 200,
        ];
    }
}
