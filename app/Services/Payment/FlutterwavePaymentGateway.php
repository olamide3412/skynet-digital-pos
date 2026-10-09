<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwavePaymentGateway implements PaymentGatewayInterface
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.flutterwave.base_url', 'https://api.flutterwave.com/v3');
    }

    public function getIdentifier(): string
    {
        return 'flutterwave';
    }

    public function getName(): string
    {
        return 'Flutterwave';
    }

    public function isEnabled(): bool
    {
        $settings = PaymentConfigService::getSettings();
        return (bool) ($settings['flutterwave_enabled'] ?? false);
    }

    public function isConfigured(): bool
    {
        return !empty(PaymentConfigService::getSecretKey('flutterwave'));
    }

    public function initiateCheckout(array $params): array
    {
        $secretKey = PaymentConfigService::getSecretKey('flutterwave');
        if (empty($secretKey)) {
            throw new \RuntimeException('Flutterwave secret key is not configured.');
        }

        $reference = $params['reference'] ?? ('flw_' . uniqid() . '_' . time());
        $payload = [
            'tx_ref'       => $reference,
            'amount'       => (float) $params['amount'],
            'currency'     => $params['currency'] ?? 'NGN',
            'redirect_url' => $params['callback_url'],
            'customer'     => [
                'email' => $params['email'] ?? 'customer@example.com',
                'name'  => $params['name']  ?? 'Valued Customer',
            ],
            'customizations' => [
                'title'       => $params['title'] ?? config('app.name', 'Skynet POS'),
                'description' => $params['description'] ?? 'Payment',
            ],
            'meta'         => $params['metadata'] ?? [],
        ];

        $response = Http::withToken($secretKey)
            ->timeout(20)
            ->post("{$this->baseUrl}/payments", $payload);

        if (!$response->successful()) {
            Log::error('Flutterwave checkout initiation failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            $msg = $response->json('message') ?: 'Unable to initiate Flutterwave payment.';
            throw new \RuntimeException($msg);
        }

        $data = $response->json('data');
        $checkoutUrl = $data['link'] ?? null;

        if (empty($checkoutUrl)) {
            throw new \RuntimeException('Flutterwave did not return a valid checkout link.');
        }

        return [
            'checkout_url' => $checkoutUrl,
            'reference'    => $reference,
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = $request->header('verif-hash');
        $configuredHash = PaymentConfigService::getFlutterwaveSecretHash();

        if (empty($signature) || empty($configuredHash)) {
            // Also accept matching against secret key directly if no custom hash was configured
            $secretKey = PaymentConfigService::getSecretKey('flutterwave');
            return !empty($signature) && !empty($secretKey) && hash_equals($secretKey, $signature);
        }

        return hash_equals($configuredHash, $signature);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $payload = $request->all();
        $data = $payload['data'] ?? [];

        $reference = $data['tx_ref'] ?? $payload['txRef'] ?? null;
        if (empty($reference)) {
            return null;
        }

        $rawStatus = strtolower((string) ($data['status'] ?? $payload['status'] ?? ''));
        $status = match ($rawStatus) {
            'successful', 'completed' => 'successful',
            'failed', 'cancelled'     => 'failed',
            default                   => 'pending',
        };

        return [
            'reference'      => (string) $reference,
            'status'         => $status,
            'amount'         => (float) ($data['amount'] ?? 0),
            'currency'       => (string) ($data['currency'] ?? 'NGN'),
            'transaction_id' => isset($data['id']) ? (string) $data['id'] : null,
            'metadata'       => $data['meta'] ?? [],
            'raw'            => $payload,
        ];
    }

    public function verifyTransaction(string $reference): array
    {
        $secretKey = PaymentConfigService::getSecretKey('flutterwave');
        if (empty($secretKey)) {
            throw new \RuntimeException('Flutterwave secret key is missing.');
        }

        $response = Http::withToken($secretKey)
            ->timeout(15)
            ->get("{$this->baseUrl}/transactions/verify_by_reference", ['tx_ref' => $reference]);

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
            'successful' => 'successful',
            'failed'     => 'failed',
            default      => 'pending',
        };

        return [
            'reference'      => (string) ($data['tx_ref'] ?? $reference),
            'status'         => $status,
            'amount'         => (float) ($data['amount'] ?? 0),
            'currency'       => (string) ($data['currency'] ?? 'NGN'),
            'transaction_id' => isset($data['id']) ? (string) $data['id'] : null,
            'raw'            => $data,
        ];
    }

    public function testConnection(): array
    {
        $secretKey = PaymentConfigService::getSecretKey('flutterwave');
        if (empty($secretKey)) {
            return ['success' => false, 'message' => 'Flutterwave secret key is missing.'];
        }

        try {
            $response = Http::withToken($secretKey)
                ->timeout(10)
                ->get("{$this->baseUrl}/balances");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connected successfully to Flutterwave API.',
                ];
            }

            return [
                'success' => false,
                'message' => $response->json('message') ?: 'Failed to authenticate with Flutterwave.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ];
        }
    }
}
