<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class MailConfigService
{
    /**
     * Get the current mail configuration map with masked password.
     */
    public static function getSettings(): array
    {
        $encryptedPass = SystemSetting::get('mail_password');
        $hasPassword = !empty($encryptedPass);

        return [
            'mail_enabled'      => (bool) SystemSetting::get('mail_enabled', false),
            'mail_host'         => SystemSetting::get('mail_host', ''),
            'mail_port'         => (int) SystemSetting::get('mail_port', 587),
            'mail_username'     => SystemSetting::get('mail_username', ''),
            'mail_encryption'   => SystemSetting::get('mail_encryption', 'tls'),
            'mail_from_address' => SystemSetting::get('mail_from_address', ''),
            'mail_from_name'    => SystemSetting::get('mail_from_name', 'Skynet POS'),
            'has_password'      => $hasPassword,
        ];
    }

    /**
     * Save mail configuration with encrypted password.
     */
    public static function saveSettings(array $data): void
    {
        if (isset($data['mail_enabled'])) {
            SystemSetting::set('mail_enabled', $data['mail_enabled'] ? '1' : '0');
        }
        if (isset($data['mail_host'])) {
            SystemSetting::set('mail_host', trim($data['mail_host']));
        }
        if (isset($data['mail_port'])) {
            SystemSetting::set('mail_port', (string) ((int) $data['mail_port']));
        }
        if (isset($data['mail_username'])) {
            SystemSetting::set('mail_username', trim($data['mail_username']));
        }
        if (!empty($data['mail_password'])) {
            SystemSetting::set('mail_password', Crypt::encryptString(trim($data['mail_password'])));
        }
        if (isset($data['mail_encryption'])) {
            SystemSetting::set('mail_encryption', trim($data['mail_encryption']));
        }
        if (isset($data['mail_from_address'])) {
            SystemSetting::set('mail_from_address', trim($data['mail_from_address']));
        }
        if (isset($data['mail_from_name'])) {
            SystemSetting::set('mail_from_name', trim($data['mail_from_name']));
        }
    }

    /**
     * Check if email delivery is enabled and properly configured.
     */
    public static function isConfiguredAndEnabled(): bool
    {
        $enabled = (bool) SystemSetting::get('mail_enabled', false);
        if (!$enabled) return false;

        $host = SystemSetting::get('mail_host');
        $username = SystemSetting::get('mail_username');
        $password = SystemSetting::get('mail_password');

        return !empty($host) && !empty($username) && !empty($password);
    }

    /**
     * Get decrypted SMTP password.
     */
    public static function getDecryptedPassword(): ?string
    {
        $encrypted = SystemSetting::get('mail_password');
        if (empty($encrypted)) return null;

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::error('Failed to decrypt mail password: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Apply dynamic database SMTP settings to Laravel's Mailer config.
     */
    public static function applyDynamicConfig(): bool
    {
        $host = SystemSetting::get('mail_host');
        $port = (int) SystemSetting::get('mail_port', 587);
        $username = SystemSetting::get('mail_username');
        $password = static::getDecryptedPassword();
        $encryption = SystemSetting::get('mail_encryption', 'tls');
        $fromAddress = SystemSetting::get('mail_from_address', 'notifications@skynetpos.com');
        $fromName = SystemSetting::get('mail_from_name', 'Skynet POS System');

        if (empty($host) || empty($username) || empty($password)) {
            return false;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.encryption', $encryption === 'none' ? null : $encryption);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.timeout', 15);
        Config::set('mail.from.address', $fromAddress ?: $username);
        Config::set('mail.from.name', $fromName ?: 'Skynet POS System');

        // Purge existing cached mailer so new configuration takes immediate effect
        Mail::purge('smtp');

        return true;
    }

    /**
     * Send a test email to verify SMTP credentials and connectivity.
     */
    public static function sendTestEmail(string $recipientEmail): array
    {
        if (!static::applyDynamicConfig()) {
            return [
                'success' => false,
                'message' => 'Incomplete SMTP configuration. Please ensure Host, Port, Username, and Password are provided.',
            ];
        }

        try {
            $fromName = Config::get('mail.from.name');
            $fromAddress = Config::get('mail.from.address');

            Mail::raw("Hello!\n\nThis is a test notification from {$fromName}.\nYour SMTP outgoing mail configuration is working perfectly!\n\nSent at: " . now()->toRfc2822String(), function ($message) use ($recipientEmail, $fromName, $fromAddress) {
                $message->to($recipientEmail)
                    ->from($fromAddress, $fromName)
                    ->subject("{$fromName} — SMTP Configuration Test");
            });

            return [
                'success' => true,
                'message' => "Test email sent successfully to {$recipientEmail}!",
            ];
        } catch (\Throwable $e) {
            Log::error('SMTP Test email failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'SMTP Connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
