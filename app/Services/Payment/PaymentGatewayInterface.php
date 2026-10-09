<?php

namespace App\Services\Payment;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Unique identifier for the gateway (e.g. 'flutterwave', 'paystack').
     */
    public function getIdentifier(): string;

    /**
     * Human-readable name.
     */
    public function getName(): string;

    /**
     * Whether this gateway is enabled by superadmin.
     */
    public function isEnabled(): bool;

    /**
     * Whether valid credentials (secret key, etc.) are present.
     */
    public function isConfigured(): bool;

    /**
     * Initiate a hosted checkout session.
     *
     * @param array $params [
     *     'reference'    => string,
     *     'amount'       => float, // standard unit, e.g. 2500.00
     *     'currency'     => string, // e.g. 'NGN'
     *     'email'        => string,
     *     'name'         => string,
     *     'title'        => string,
     *     'description'  => string,
     *     'callback_url' => string,
     *     'metadata'     => array,
     * ]
     * @return array ['checkout_url' => string, 'reference' => string]
     * @throws \RuntimeException on API failure
     */
    public function initiateCheckout(array $params): array;

    /**
     * Validate incoming webhook signature.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse webhook payload into normalized array.
     *
     * @return array|null [
     *     'reference'      => string,
     *     'status'         => 'successful'|'failed'|'pending',
     *     'amount'         => float,
     *     'currency'       => string,
     *     'transaction_id' => string|null,
     *     'metadata'       => array,
     *     'raw'            => array,
     * ]
     */
    public function parseWebhookPayload(Request $request): ?array;

    /**
     * Verify a transaction server-side directly against the gateway by reference.
     *
     * @return array [
     *     'reference'      => string,
     *     'status'         => 'successful'|'failed'|'pending',
     *     'amount'         => float,
     *     'currency'       => string,
     *     'transaction_id' => string|null,
     *     'raw'            => array,
     * ]
     */
    public function verifyTransaction(string $reference): array;

    /**
     * Test connection to gateway with active credentials.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;
}
