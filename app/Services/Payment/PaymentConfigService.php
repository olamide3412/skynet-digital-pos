<?php

namespace App\Services\Payment;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class PaymentConfigService
{
    /**
     * Get normalized settings for SuperAdmin UI.
     */
    public static function getSettings(): array
    {
        $flwSecretEnc = SystemSetting::get('payment_flw_secret_key');
        $flwHashEnc   = SystemSetting::get('payment_flw_secret_hash');
        $psSecretEnc  = SystemSetting::get('payment_ps_secret_key');

        $envFlwSecret = config('services.flutterwave.secret_key');
        $envPsSecret  = config('services.paystack.secret_key');

        $hasFlwSecret = !empty($flwSecretEnc) || !empty($envFlwSecret);
        $hasPsSecret  = !empty($psSecretEnc)  || !empty($envPsSecret);

        return [
            'is_sandbox' => (bool) SystemSetting::get('payment_is_sandbox', false),

            // Flutterwave
            'flutterwave_enabled'    => (bool) SystemSetting::get('payment_flw_enabled', $hasFlwSecret),
            'flutterwave_public_key' => SystemSetting::get('payment_flw_public_key', env('FLW_PUBLIC_KEY', '')),
            'has_flutterwave_secret' => $hasFlwSecret,
            'flutterwave_is_from_env'=> empty($flwSecretEnc) && !empty($envFlwSecret),
            'flutterwave_secret_hash'=> SystemSetting::get('payment_flw_secret_hash_plain', env('FLW_SECRET_HASH', '')),

            // Paystack
            'paystack_enabled'       => (bool) SystemSetting::get('payment_ps_enabled', $hasPsSecret),
            'paystack_public_key'    => SystemSetting::get('payment_ps_public_key', env('PAYSTACK_PUBLIC_KEY', '')),
            'has_paystack_secret'    => $hasPsSecret,
            'paystack_is_from_env'   => empty($psSecretEnc) && !empty($envPsSecret),
        ];
    }

    /**
     * Save payment configuration from SuperAdmin.
     */
    public static function saveSettings(array $data): void
    {
        if (array_key_exists('is_sandbox', $data)) {
            SystemSetting::set('payment_is_sandbox', $data['is_sandbox'] ? '1' : '0');
        }

        // Flutterwave
        if (array_key_exists('flutterwave_enabled', $data)) {
            SystemSetting::set('payment_flw_enabled', $data['flutterwave_enabled'] ? '1' : '0');
        }
        if (!empty($data['flutterwave_public_key'])) {
            SystemSetting::set('payment_flw_public_key', trim($data['flutterwave_public_key']));
        }
        if (!empty($data['flutterwave_secret_key'])) {
            SystemSetting::set('payment_flw_secret_key', Crypt::encryptString(trim($data['flutterwave_secret_key'])));
        }
        if (isset($data['flutterwave_secret_hash'])) {
            $hash = trim($data['flutterwave_secret_hash']);
            SystemSetting::set('payment_flw_secret_hash_plain', $hash);
            if (!empty($hash)) {
                SystemSetting::set('payment_flw_secret_hash', Crypt::encryptString($hash));
            }
        }

        // Paystack
        if (array_key_exists('paystack_enabled', $data)) {
            SystemSetting::set('payment_ps_enabled', $data['paystack_enabled'] ? '1' : '0');
        }
        if (!empty($data['paystack_public_key'])) {
            SystemSetting::set('payment_ps_public_key', trim($data['paystack_public_key']));
        }
        if (!empty($data['paystack_secret_key'])) {
            SystemSetting::set('payment_ps_secret_key', Crypt::encryptString(trim($data['paystack_secret_key'])));
        }
    }

    /**
     * Get decrypted secret key for a gateway, falling back to config/env.
     */
    public static function getSecretKey(string $gateway): ?string
    {
        if ($gateway === 'flutterwave') {
            $enc = SystemSetting::get('payment_flw_secret_key');
            if (!empty($enc)) {
                try {
                    return Crypt::decryptString($enc);
                } catch (\Throwable $e) {
                    Log::error("Failed to decrypt Flutterwave secret key: " . $e->getMessage());
                }
            }
            return config('services.flutterwave.secret_key');
        }

        if ($gateway === 'paystack') {
            $enc = SystemSetting::get('payment_ps_secret_key');
            if (!empty($enc)) {
                try {
                    return Crypt::decryptString($enc);
                } catch (\Throwable $e) {
                    Log::error("Failed to decrypt Paystack secret key: " . $e->getMessage());
                }
            }
            return config('services.paystack.secret_key');
        }

        return null;
    }

    /**
     * Get public key for a gateway.
     */
    public static function getPublicKey(string $gateway): ?string
    {
        if ($gateway === 'flutterwave') {
            return SystemSetting::get('payment_flw_public_key') ?: env('FLW_PUBLIC_KEY');
        }

        if ($gateway === 'paystack') {
            return SystemSetting::get('payment_ps_public_key') ?: env('PAYSTACK_PUBLIC_KEY');
        }

        return null;
    }

    /**
     * Get Flutterwave secret hash for webhook verification.
     */
    public static function getFlutterwaveSecretHash(): ?string
    {
        $enc = SystemSetting::get('payment_flw_secret_hash');
        if (!empty($enc)) {
            try {
                return Crypt::decryptString($enc);
            } catch (\Throwable $e) {
                // Ignore fallback to plain or env
            }
        }
        return SystemSetting::get('payment_flw_secret_hash_plain') ?: env('FLW_SECRET_HASH', 'skynet_flw_webhook_hash');
    }

    /**
     * Check if a gateway is enabled.
     */
    public static function isGatewayEnabled(string $gateway): bool
    {
        $settings = self::getSettings();
        if ($gateway === 'flutterwave') {
            return (bool) $settings['flutterwave_enabled'] && !empty(self::getSecretKey('flutterwave'));
        }
        if ($gateway === 'paystack') {
            return (bool) $settings['paystack_enabled'] && !empty(self::getSecretKey('paystack'));
        }
        return false;
    }

    /**
     * Get an instantiated gateway adapter.
     */
    public static function getGateway(string $gateway): ?PaymentGatewayInterface
    {
        return match (strtolower(trim($gateway))) {
            'flutterwave' => new FlutterwavePaymentGateway(),
            'paystack'    => new PaystackPaymentGateway(),
            default       => null,
        };
    }

    /**
     * Get list of enabled and configured gateways.
     *
     * @return PaymentGatewayInterface[]
     */
    public static function getEnabledGateways(): array
    {
        $gateways = [
            new FlutterwavePaymentGateway(),
            new PaystackPaymentGateway(),
        ];

        return array_values(array_filter($gateways, fn (PaymentGatewayInterface $g) => $g->isEnabled() && $g->isConfigured()));
    }

    /**
     * Get list of available gateways for UI consumption.
     */
    public static function getAvailableGateways(): array
    {
        return array_map(function (PaymentGatewayInterface $g) {
            return [
                'id'         => $g->getIdentifier(),
                'identifier' => $g->getIdentifier(),
                'name'       => $g->getName(),
            ];
        }, self::getEnabledGateways());
    }
}
