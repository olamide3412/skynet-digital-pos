<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\AiCreditPack;
use App\Models\AiCreditTopup;
use App\Models\Branch;
use App\Modules\AI\Services\AiCreditService;
use App\Services\Payment\PaymentConfigService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrgCreditController extends Controller
{
    /**
     * Display credit balance, monthly quota, active packs, and topup history.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $org = $user->organization;

        if (!$org) {
            abort(403, 'No organization associated with this account.');
        }

        $branches = $org->branches()->where('is_active', true)->orderBy('name')->get();
        $selectedBranchId = $request->query('branch_id');
        $selectedBranch = $selectedBranchId
            ? $branches->firstWhere('id', (int) $selectedBranchId)
            : $branches->first();

        // 1. Quota & Balance Summary
        $summary = AiCreditService::getSummary($org, $selectedBranch);

        // 2. Active credit packs for purchase
        $packs = AiCreditPack::active()->get();

        // 3. Available and enabled payment gateways
        $enabledGateways = array_map(function ($g) {
            return [
                'identifier' => $g->getIdentifier(),
                'name'       => $g->getName(),
            ];
        }, PaymentConfigService::getEnabledGateways());

        // 4. Organization top-up and purchase history
        $history = AiCreditTopup::where('organization_id', $org->id)
            ->with(['creditPack', 'branch'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Org/Credits/Index', [
            'organization'    => $org,
            'branches'        => $branches,
            'selectedBranch'  => $selectedBranch,
            'summary'         => $summary,
            'packs'           => $packs,
            'gateways'        => $enabledGateways,
            'history'         => $history,
            'paymentNotice'   => $request->query('payment_notice'),
        ]);
    }

    /**
     * Initiate credit pack purchase.
     */
    public function purchase(Request $request)
    {
        $user = Auth::user();
        $org = $user->organization;

        if (!$org) {
            abort(403, 'No organization associated with this account.');
        }

        $validated = $request->validate([
            'credit_pack_id' => 'required|exists:ai_credit_packs,id',
            'gateway'        => 'required|string|in:flutterwave,paystack',
            'branch_id'      => 'nullable|exists:branches,id',
        ]);

        $pack = AiCreditPack::where('id', $validated['credit_pack_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $branch = null;
        if (!empty($validated['branch_id'])) {
            $branch = Branch::where('id', $validated['branch_id'])
                ->where('organization_id', $org->id)
                ->first();
        }

        try {
            $callbackUrl = route('org.credits.callback');

            $checkout = PaymentService::initiateAiCreditPackCheckout(
                $org,
                $pack,
                $validated['gateway'],
                $branch,
                $user,
                $callbackUrl
            );

            // Redirect client to hosted payment checkout
            return Inertia::location($checkout['checkout_url']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Unable to initiate payment: ' . $e->getMessage());
        }
    }

    /**
     * Handle return redirect from hosted gateway.
     * Verifies the reference server-side with idempotency (prevents race conditions with webhook).
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') 
            ?? $request->query('tx_ref') 
            ?? $request->query('trxref');

        if ($reference && str_starts_with($reference, 'aic_')) {
            $topup = AiCreditTopup::where('gateway_reference', $reference)->first();

            if ($topup && $topup->status === 'successful') {
                return redirect()->route('org.credits.index')
                    ->with('success', "Payment confirmed! {$topup->credits} AI Credits have been credited to your balance.");
            }

            if ($topup && $topup->status === 'pending') {
                $gateway = PaymentConfigService::getGateway($topup->gateway);
                if ($gateway) {
                    try {
                        $verifyResult = $gateway->verifyTransaction($reference);
                        if ($verifyResult['status'] === 'successful') {
                            AiCreditService::confirmPurchase(
                                $reference,
                                $verifyResult['transaction_id'] ?? null,
                                $verifyResult['raw'] ?? []
                            );
                            return redirect()->route('org.credits.index')
                                ->with('success', "Payment successful! {$topup->credits} AI Credits have been credited to your balance.");
                        } elseif ($verifyResult['status'] === 'failed') {
                            AiCreditService::failPurchase($reference, 'Payment failed at gateway.');
                            return redirect()->route('org.credits.index')
                                ->with('error', 'Payment was declined or cancelled at the gateway.');
                        }
                    } catch (\Throwable $e) {
                        // Webhook fallback will process in the background
                    }
                }
            }
        }

        return redirect()->route('org.credits.index')
            ->with('info', 'Payment submitted. Your credits will reflect immediately once confirmed by the payment gateway.');
    }
}
