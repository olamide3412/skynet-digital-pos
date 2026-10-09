<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentWebhookController extends Controller
{
    /**
     * Handle incoming Flutterwave webhook.
     */
    public function handleFlutterwave(Request $request): JsonResponse
    {
        $result = PaymentService::processWebhook('flutterwave', $request);

        return response()->json($result, $result['status_code'] ?? 200);
    }

    /**
     * Handle incoming Paystack webhook.
     */
    public function handlePaystack(Request $request): JsonResponse
    {
        $result = PaymentService::processWebhook('paystack', $request);

        return response()->json($result, $result['status_code'] ?? 200);
    }
}
