<?php

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = [
        'active_provider',
        'encrypted_api_keys',
        'provider_models',
        'temperature',
        'max_tokens',
        'is_system_enabled',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected function casts(): array
    {
        return [
            'provider_models'   => 'array',
            'temperature'       => 'decimal:2',
            'max_tokens'        => 'integer',
            'is_system_enabled' => 'boolean',
            'last_tested_at'    => 'datetime',
        ];
    }

    /**
     * Singleton instance resolver.
     */
    public static function current(): static
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'active_provider'    => config('ai.default_provider', 'gemini'),
                'encrypted_api_keys' => json_encode([]),
                'provider_models'    => [
                    'gemini'   => 'gemini-1.5-flash',
                    'openai'   => 'gpt-4o-mini',
                    'deepseek' => 'deepseek-chat',
                    'kimi'     => 'moonshot-v1-8k',
                    'claude'   => 'claude-3-5-sonnet-20241022',
                ],
                'temperature'        => 0.7,
                'max_tokens'         => 2048,
                'is_system_enabled'  => true,
            ]
        );
    }

    /**
     * Set encrypted API key for a provider.
     */
    public function setApiKey(string $provider, ?string $apiKey): void
    {
        $keys = $this->getRawDecryptedKeys();

        if (empty($apiKey)) {
            unset($keys[$provider]);
        } else {
            $keys[$provider] = $apiKey;
        }

        $encrypted = !empty($keys) ? Crypt::encryptString(json_encode($keys)) : null;
        $this->update(['encrypted_api_keys' => $encrypted]);
    }

    /**
     * Get decrypted API key for a provider.
     */
    public function getApiKey(string $provider): ?string
    {
        $keys = $this->getRawDecryptedKeys();
        return $keys[$provider] ?? null;
    }

    /**
     * Check if a valid API key exists for a provider.
     */
    public function hasApiKey(string $provider): bool
    {
        return !empty($this->getApiKey($provider));
    }

    /**
     * Internal: retrieve and decrypt all provider keys array.
     */
    protected function getRawDecryptedKeys(): array
    {
        if (empty($this->encrypted_api_keys)) {
            return [];
        }

        try {
            $decrypted = Crypt::decryptString($this->encrypted_api_keys);
            return json_decode($decrypted, true) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Get configured or fallback model name for a provider.
     */
    public function getModelForProvider(string $provider): string
    {
        $customModels = $this->provider_models ?: [];
        if (!empty($customModels[$provider])) {
            return $customModels[$provider];
        }

        $configDefault = config("ai.providers.{$provider}.default_model");
        return $configDefault ?: 'default';
    }

    /**
     * Set model choice for a provider.
     */
    public function setModelForProvider(string $provider, string $model): void
    {
        $models = $this->provider_models ?: [];
        $models[$provider] = $model;
        $this->update(['provider_models' => $models]);
    }
}
