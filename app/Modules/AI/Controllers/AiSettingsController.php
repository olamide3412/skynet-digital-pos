<?php

namespace App\Modules\AI\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\AI\Models\AiSetting;
use App\Modules\AI\Services\AiManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AiSettingsController extends Controller
{
    /**
     * Super Admin AI Settings Dashboard.
     */
    public function index()
    {
        $setting = AiSetting::current();
        $aiManager = app(AiManager::class);

        $providersConfig = config('ai.providers', []);
        $formattedProviders = [];

        foreach ($providersConfig as $key => $config) {
            $formattedProviders[] = [
                'id'               => $key,
                'name'             => $config['name'],
                'default_model'    => $config['default_model'],
                'available_models' => $config['available_models'],
                'has_key'          => $setting->hasApiKey($key),
                'selected_model'   => $setting->getModelForProvider($key),
            ];
        }

        $branches = Branch::select(['id', 'name', 'slug', 'is_active', 'is_ai_enabled'])
            ->orderBy('name')
            ->get();

        return Inertia::render('AI/Settings', [
            'settings' => [
                'active_provider'   => $setting->active_provider,
                'is_system_enabled' => (bool) $setting->is_system_enabled,
                'temperature'       => (float) $setting->temperature,
                'max_tokens'        => (int) $setting->max_tokens,
                'last_tested_at'    => $setting->last_tested_at?->format('d M Y, h:i A'),
                'last_test_status'  => $setting->last_test_status,
                'last_test_message' => $setting->last_test_message,
            ],
            'providers' => $formattedProviders,
            'branches'  => $branches,
        ]);
    }

    /**
     * Update Super Admin AI configuration.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'active_provider'   => 'required|string|in:gemini,openai,deepseek,kimi,claude',
            'api_key'           => 'nullable|string',
            'provider_models'   => 'nullable|array',
            'temperature'       => 'required|numeric|min:0|max:1',
            'max_tokens'        => 'required|integer|min:100|max:8192',
            'is_system_enabled' => 'required|boolean',
        ]);

        $setting = AiSetting::current();

        if (!empty($data['api_key'])) {
            $setting->setApiKey($data['active_provider'], $data['api_key']);
        }

        $setting->update([
            'active_provider'   => $data['active_provider'],
            'provider_models'   => $data['provider_models'] ?? $setting->provider_models,
            'temperature'       => $data['temperature'],
            'max_tokens'        => $data['max_tokens'],
            'is_system_enabled' => $data['is_system_enabled'],
        ]);

        return back()->with('success', 'AI Provider settings updated successfully.');
    }

    /**
     * Diagnostic Test Connection endpoint.
     */
    public function testConnection(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|string|in:gemini,openai,deepseek,kimi,claude',
            'api_key'  => 'nullable|string',
            'model'    => 'nullable|string',
        ]);

        $aiManager = app(AiManager::class);
        $result = $aiManager->testProvider($data['provider'], $data['api_key'] ?? null, $data['model'] ?? null);

        return response()->json($result);
    }

    /**
     * Toggle AI features per branch.
     */
    public function toggleBranch(Request $request, Branch $branch)
    {
        $newState = !($branch->is_ai_enabled ?? true);
        $branch->is_ai_enabled = $newState;
        $branch->save();
        $status = $newState ? 'enabled' : 'disabled';

        return back()->with('success', "AI intelligence features {$status} for branch '{$branch->name}'.");
    }
}
