<?php

namespace App\Modules\AI\Services;

use App\Models\Branch;
use App\Modules\AI\Models\AiSetting;
use App\Modules\AI\Services\Providers\ClaudeProvider;
use App\Modules\AI\Services\Providers\DeepSeekProvider;
use App\Modules\AI\Services\Providers\GeminiProvider;
use App\Modules\AI\Services\Providers\KimiProvider;
use App\Modules\AI\Services\Providers\OpenAiProvider;

class AiManager
{
    /**
     * Resolve the currently active provider instance.
     */
    public function getActiveProvider(): ?AiProviderInterface
    {
        $setting = AiSetting::current();

        if (!$setting->is_system_enabled) {
            return null;
        }

        $providerName = $setting->active_provider ?: 'gemini';
        $apiKey = $setting->getApiKey($providerName);

        if (empty($apiKey)) {
            return null;
        }

        $model = $setting->getModelForProvider($providerName);

        return $this->getProvider($providerName, $apiKey, $model);
    }

    /**
     * Factory: Build a concrete provider instance.
     */
    public function getProvider(string $providerName, string $apiKey, ?string $model = null): AiProviderInterface
    {
        $timeout = (int) config('ai.defaults.timeout', 30);

        return match (strtolower(trim($providerName))) {
            'openai'   => new OpenAiProvider($apiKey, $model, $timeout),
            'deepseek' => new DeepSeekProvider($apiKey, $model, $timeout),
            'kimi'     => new KimiProvider($apiKey, $model, $timeout),
            'claude'   => new ClaudeProvider($apiKey, $model, $timeout),
            default    => new GeminiProvider($apiKey, $model, $timeout),
        };
    }

    /**
     * Check if AI is configured system-wide with a valid API key.
     */
    public function isConfigured(): bool
    {
        $setting = AiSetting::current();
        if (!$setting->is_system_enabled) {
            return false;
        }

        $provider = $setting->active_provider ?: 'gemini';
        return $setting->hasApiKey($provider);
    }

    /**
     * Check if AI features are active and permitted for a specific branch.
     */
    public function isBranchEnabled(?Branch $branch): bool
    {
        $setting = AiSetting::current();
        if (!$setting->is_system_enabled) {
            return false;
        }

        if (!$branch) {
            return true; // Super Admin context
        }

        return (bool) ($branch->is_ai_enabled ?? true);
    }

    /**
     * Test connection to a provider with specified or stored credentials.
     */
    public function testProvider(string $providerName, ?string $apiKey = null, ?string $model = null): array
    {
        $setting = AiSetting::current();
        $effectiveKey = $apiKey ?: $setting->getApiKey($providerName);

        if (empty($effectiveKey)) {
            return [
                'success'    => false,
                'message'    => "No API key provided for {$providerName}.",
                'latency_ms' => 0,
                'model'      => $model ?: 'default',
            ];
        }

        $effectiveModel = $model ?: $setting->getModelForProvider($providerName);
        $provider = $this->getProvider($providerName, $effectiveKey, $effectiveModel);

        $result = $provider->testConnection();

        // Update settings test diagnostic status
        if ($providerName === $setting->active_provider) {
            $setting->update([
                'last_tested_at'    => now(),
                'last_test_status'  => $result['success'] ? 'success' : 'failed',
                'last_test_message' => $result['message'],
            ]);
        }

        return $result;
    }

    /**
     * Shorthand text generation using the active provider.
     */
    public function generateText(string $prompt, array $options = []): string
    {
        $provider = $this->getActiveProvider();
        if (!$provider) {
            throw new \RuntimeException("No active AI provider is configured.");
        }

        return $provider->generateText($prompt, $options);
    }

    /**
     * Shorthand chat conversation using the active provider.
     */
    public function chat(array $messages, array $options = []): string
    {
        $provider = $this->getActiveProvider();
        if (!$provider) {
            throw new \RuntimeException("No active AI provider is configured.");
        }

        return $provider->chat($messages, $options);
    }
}
