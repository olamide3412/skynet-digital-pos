<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlanCapabilityController extends Controller
{
    public function index()
    {
        $plans = PricingPlan::with('capabilities')
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();

        foreach ($plans as $plan) {
            if (!$plan->capabilities) {
                $plan->capabilities()->create([
                    'max_branches'                       => 1,
                    'max_users_per_branch'               => 5,
                    'ai_credits_monthly'                 => 25,
                    'ai_credit_pool_type'                => 'per_branch',
                    'feature_ecommerce_integration'      => false,
                    'feature_multi_branch'               => false,
                    'feature_debt_customer_accounts'     => true,
                    'feature_discounts'                  => true,
                    'feature_batch_expiry_tracking'      => false,
                    'feature_imei_tracking'              => false,
                    'feature_barcode_label_printing'     => false,
                    'feature_inventory_import'           => false,
                    'feature_advanced_staff_permissions' => false,
                    'feature_ai_chat_assistant'          => false,
                    'feature_ai_reorder_prediction'      => false,
                    'feature_broadcast_notifications'    => true,
                ]);
                $plan->load('capabilities');
            }
        }

        return Inertia::render('SuperAdmin/PlanCapabilities/Index', [
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, PricingPlan $plan)
    {
        $validated = $request->validate([
            'ai_credits_monthly'                 => 'nullable|integer|min:0',
            'is_ai_unmetered'                    => 'nullable|boolean',
            'ai_credit_pool_type'                => 'required|in:per_branch,account_wide',
            'max_branches'                       => 'nullable|integer|min:1',
            'is_branches_unlimited'              => 'nullable|boolean',
            'max_users_per_branch'               => 'nullable|integer|min:1',
            'is_users_unlimited'                 => 'nullable|boolean',
            'feature_ecommerce_integration'      => 'boolean',
            'feature_multi_branch'               => 'boolean',
            'feature_debt_customer_accounts'     => 'boolean',
            'feature_discounts'                  => 'boolean',
            'feature_batch_expiry_tracking'      => 'boolean',
            'feature_imei_tracking'              => 'boolean',
            'feature_barcode_label_printing'     => 'boolean',
            'feature_inventory_import'           => 'boolean',
            'feature_advanced_staff_permissions' => 'boolean',
            'feature_ai_chat_assistant'          => 'boolean',
            'feature_ai_reorder_prediction'      => 'boolean',
            'feature_broadcast_notifications'    => 'boolean',
            'feature_accounting'                 => 'boolean',
        ]);

        $data = [
            'ai_credits_monthly'                 => !empty($validated['is_ai_unmetered']) ? null : ($validated['ai_credits_monthly'] ?? 0),
            'ai_credit_pool_type'                => $validated['ai_credit_pool_type'],
            'max_branches'                       => !empty($validated['is_branches_unlimited']) ? null : ($validated['max_branches'] ?? 1),
            'max_users_per_branch'               => !empty($validated['is_users_unlimited']) ? null : ($validated['max_users_per_branch'] ?? 1),
            'feature_ecommerce_integration'      => (bool) ($validated['feature_ecommerce_integration'] ?? false),
            'feature_multi_branch'               => (bool) ($validated['feature_multi_branch'] ?? false),
            'feature_debt_customer_accounts'     => (bool) ($validated['feature_debt_customer_accounts'] ?? false),
            'feature_discounts'                  => (bool) ($validated['feature_discounts'] ?? false),
            'feature_batch_expiry_tracking'      => (bool) ($validated['feature_batch_expiry_tracking'] ?? false),
            'feature_imei_tracking'              => (bool) ($validated['feature_imei_tracking'] ?? false),
            'feature_barcode_label_printing'     => (bool) ($validated['feature_barcode_label_printing'] ?? false),
            'feature_inventory_import'           => (bool) ($validated['feature_inventory_import'] ?? false),
            'feature_advanced_staff_permissions' => (bool) ($validated['feature_advanced_staff_permissions'] ?? false),
            'feature_ai_chat_assistant'          => (bool) ($validated['feature_ai_chat_assistant'] ?? false),
            'feature_ai_reorder_prediction'      => (bool) ($validated['feature_ai_reorder_prediction'] ?? false),
            'feature_broadcast_notifications'    => (bool) ($validated['feature_broadcast_notifications'] ?? false),
            'feature_accounting'                 => (bool) ($validated['feature_accounting'] ?? false),
        ];

        $plan->capabilities()->updateOrCreate(
            ['pricing_plan_id' => $plan->id],
            $data
        );

        ActivityLogger::log(
            'plan_capabilities_updated',
            "Updated engine capabilities and limits for plan {$plan->name}"
        );

        return back()->with('success', "Capabilities for '{$plan->name}' saved successfully.");
    }
}
