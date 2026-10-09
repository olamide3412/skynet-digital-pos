<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackPaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.paystack.base_url', 'https://api.paystack.co');
    }

    public function getIdentifier(): string
    {
        return 'paystack';
    }

    public function getName(): string
    {
        return 'Paystack';
    }

    public function isEnabled(): bool
    {
        $settings = PaymentConfigService::getSettings();
        return (bool) ($settings['paystack_enabled'] ?? false);
    }

    public function isConfigured(): bool
    {
        return !empty(PaymentConfigService::getSecretKey('paystack'));
    }

    public function initiateCheckout(array $params): array
    {
        $secretKey = PaymentConfigService::getSecretKey('paystack');
        if (empty($secretKey)) {
            throw new \RuntimeException('Paystack secret key is not configured.');
        }

        $reference = $params['reference'] ?? ('ps_' . uniqid() . '_' . time());
        $payload = [
            'reference'    => $reference,
            'amount'       => (int) round(((float) $params['amount']) * 100), // amount in kobo
            'currency'     => $params['currency'] ?? 'NGN',
            'email'        => $params['email'] ?? 'customer@example.com',
            'callback_url' => $params['callback_url'],
            'metadata'     => array_merge($params['metadata'] ?? [], [
                'custom_fields' => [
                    [
                        'display_name'  => 'Description',
                        'variable_name' => 'description',
                        'value'         => $params['description'] ?? 'Payment',
                    ],
                ],
            ]),
        ];

        $response = Http::withToken($secretKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        if (!$response->successful()) {
            Log::error('Paystack checkout initiation failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            $msg = $response->json('message') ?: 'Unable to initiate Paystack payment.';
            throw new \RuntimeException($msg);
        }

        $data = $response->json('data');
        $checkoutUrl = $data['authorization_url'] ?? null;

        if (empty($checkoutUrl)) {
            throw new \RuntimeException('Paystack did not return a valid checkout link.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'reference'    => $reference,
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('x-paystack-signature');
        $secretKey = PaymentConfigService::getSecretKey('paystack');

        if (empty($signature) || empty($secretKey)) {
            return false;
        }

        $computedSignature = hash_hmac('sha512', $request->getContent(), $secretKey);
        return hash_equals($computedSignature, $signature);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $payload = $request->all();
        $event = $payload['event'] ?? '';
        $data  = $payload['data'] ?? [];

        $reference = $data['reference'] ?? null;
        if (empty($reference)) {
            return null;
        }

        $status = 'pending';
        if ($event === 'charge.success' || ($data['status'] ?? '') === 'success') {
            $status = 'successful';
        } elseif (str_contains($event, 'failed') || ($data['status'] ?? '') === 'failed') {
            $status = 'failed';
        }

        return [
            'reference'      => (string) $reference,
            'status'         => $status,
            'amount'         => (float) (($data['amount'] ?? 0) / 100), // convert kobo back to NGN
            'currency'       => (string) ($data['currency'] ?? 'NGN'),
            'transaction_id' => isset($data['id']) ? (string) $data['id'] : null,
            'metadata'       => $data['metadata'] ?? [],
            'raw'            => $payload,
        ];
    }

    public function verifyTransaction(string $reference): array
    {
        $secretKey = PaymentConfigService::getSecretKey('paystack');
        if (empty($secretKey)) {
            throw new \RuntimeException('Paystack secret key is missing.');
        }

        $response = Http::withToken($secretKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (!$response->successful()) {
            return [
                'reference'      => $reference,
                'status'         => 'failed',
                'amount'         => 0.0,
                'currency'       => 'NGN',
                'transaction_id' => null,
                'raw'            => $response->json() ?? [],
            ];
        }

        $data = $response->json('data') ?? [];
        $statusStr = strtolower($data['status'] ?? '');
        $status = match ($statusStr) {
            'success' => 'successful',
            'failed'  => 'failed',
            default   => 'pending',
        };

        return [
            'reference'      => (string) ($data['reference'] ?? $reference),
            'status'         => $status,
            'amount'         => (float) (($data['amount'] ?? 0) / 100),
            'currency'       => (string) ($data['currency'] ?? 'NGN'),
            'transaction_id' => isset($data['id']) ? (string) $data['id'] : null,
            'raw'            => $data,
        ];
    }

    public function testConnection(): array
    {
        $secretKey = PaymentConfigService::getSecretKey('paystack');
        if (empty($secretKey)) {
            return ['success' => false, 'message' => 'Paystack secret key is missing.'];
        }

        try {
            $response = Http::withToken($secretKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/transaction", ['perPage' => 1]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to Paystack API.',
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?: 'Failed to authenticate with Paystack.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}
