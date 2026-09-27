<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PricingIncludedItem;
use App\Models\PricingPlan;
use App\Models\PricingPlanFeature;
use App\Models\PricingSetting;
use App\Models\PricingValueProp;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PricingPlanController extends Controller
{
    public function index()
    {
        $plans = PricingPlan::with(['features' => function ($q) {
            $q->orderBy('sort_order');
        }])
            ->orderBy('sort_order')
            ->get();

        $settings = PricingSetting::current();
        $includedItems = PricingIncludedItem::orderBy('sort_order')->get();
        $valueProps = PricingValueProp::orderBy('sort_order')->get();

        return Inertia::render('SuperAdmin/PricingPlans/Index', [
            'plans'         => $plans,
            'settings'      => $settings,
            'includedItems' => $includedItems,
            'valueProps'    => $valueProps,
        ]);
    }

    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'tagline'          => 'nullable|string|max:255',
            'first_year_price' => 'required|string|max:100',
            'renewal_price'    => 'required|string|max:100',
            'branches_limit'   => 'required|string|max:100',
            'users_limit'      => 'required|string|max:100',
            'ai_credits'       => 'nullable|string|max:150',
            'support_level'    => 'nullable|string|max:150',
            'is_highlighted'   => 'boolean',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'boolean',
            'features'         => 'nullable|array',
            'features.*'       => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $features = array_values(array_filter($validated['features'] ?? [], fn($f) => filled(trim($f))));
            unset($validated['features']);

            if (empty($validated['sort_order'])) {
                $validated['sort_order'] = (PricingPlan::max('sort_order') ?? 0) + 1;
            }

            $plan = PricingPlan::create($validated);

            foreach ($features as $idx => $featText) {
                PricingPlanFeature::create([
                    'pricing_plan_id' => $plan->id,
                    'feature_text'    => trim($featText),
                    'sort_order'      => $idx + 1,
                ]);
            }

            ActivityLogger::log(
                'pricing_plan_created',
                "Created pricing plan {$plan->name}"
            );
        });

        return back()->with('success', 'Pricing plan created successfully.');
    }

    public function updatePlan(Request $request, PricingPlan $plan)
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:100',
            'tagline'          => 'nullable|string|max:255',
            'first_year_price' => 'required|string|max:100',
            'renewal_price'    => 'required|string|max:100',
            'branches_limit'   => 'required|string|max:100',
            'users_limit'      => 'required|string|max:100',
            'ai_credits'       => 'nullable|string|max:150',
            'support_level'    => 'nullable|string|max:150',
            'is_highlighted'   => 'boolean',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'boolean',
            'features'         => 'nullable|array',
            'features.*'       => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($plan, $validated) {
            $features = array_values(array_filter($validated['features'] ?? [], fn($f) => filled(trim($f))));
            unset($validated['features']);

            $plan->update($validated);

            // Replace features
            $plan->features()->delete();
            foreach ($features as $idx => $featText) {
                PricingPlanFeature::create([
                    'pricing_plan_id' => $plan->id,
                    'feature_text'    => trim($featText),
                    'sort_order'      => $idx + 1,
                ]);
            }

            ActivityLogger::log(
                'pricing_plan_updated',
                "Updated pricing plan {$plan->name}"
            );
        });

        return back()->with('success', 'Pricing plan updated successfully.');
    }

    public function destroyPlan(PricingPlan $plan)
    {
        $planName = $plan->name;
        $plan->delete();

        ActivityLogger::log(
            'pricing_plan_deleted',
            "Deleted pricing plan {$planName}"
        );

        return back()->with('success', "Plan '{$planName}' deleted successfully.");
    }

    public function togglePlan(PricingPlan $plan)
    {
        $plan->update(['is_active' => !$plan->is_active]);

        ActivityLogger::log(
            'pricing_plan_toggled',
            "Toggled plan {$plan->name} active status to " . ($plan->is_active ? 'Active' : 'Inactive')
        );

        return back()->with('success', "Plan '{$plan->name}' is now " . ($plan->is_active ? 'active' : 'hidden') . '.');
    }

    public function toggleHighlight(PricingPlan $plan)
    {
        $plan->update(['is_highlighted' => !$plan->is_highlighted]);

        ActivityLogger::log(
            'pricing_plan_highlight_toggled',
            "Toggled highlight status for {$plan->name}"
        );

        return back()->with('success', "Plan '{$plan->name}' highlight status updated.");
    }

    public function reorderPlans(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:pricing_plans,id',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['order'] as $index => $planId) {
                PricingPlan::where('id', $planId)->update(['sort_order' => $index + 1]);
            }
        });

        return back()->with('success', 'Plan display order updated successfully.');
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'show_pricing_section'     => 'required|boolean',
            'pricing_intro_heading'    => 'nullable|string|max:255',
            'pricing_intro_text'       => 'nullable|string',
            'whats_included_heading'   => 'nullable|string|max:255',
            'addons_heading'           => 'nullable|string|max:255',
            'addons_content'           => 'nullable|string',
            'commercial_notes_heading' => 'nullable|string|max:255',
            'commercial_notes_content' => 'nullable|string',
            'currency_symbol'          => 'nullable|string|max:10',
        ]);

        $settings = PricingSetting::current();
        $settings->update($validated);

        ActivityLogger::log(
            'pricing_settings_updated',
            'Updated homepage pricing settings & shared content blocks'
        );

        return back()->with('success', 'Pricing settings updated successfully.');
    }

    public function togglePricingSection(Request $request)
    {
        $settings = PricingSetting::current();
        $newVal = !$settings->show_pricing_section;
        $settings->update(['show_pricing_section' => $newVal]);

        ActivityLogger::log(
            'pricing_section_toggled',
            "Toggled homepage pricing section to " . ($newVal ? 'Visible' : 'Hidden')
        );

        return back()->with('success', 'Pricing section on homepage is now ' . ($newVal ? 'Visible' : 'Hidden') . '.');
    }

    public function updateIncludedItems(Request $request)
    {
        $validated = $request->validate([
            'items'   => 'nullable|array',
            'items.*' => 'nullable|string|max:255',
        ]);

        $items = array_values(array_filter($validated['items'] ?? [], fn($i) => filled(trim($i))));

        DB::transaction(function () use ($items) {
            PricingIncludedItem::truncate();
            foreach ($items as $idx => $itemText) {
                PricingIncludedItem::create([
                    'item_text'  => trim($itemText),
                    'sort_order' => $idx + 1,
                ]);
            }
        });

        return back()->with('success', "What Every Plan Includes items updated successfully.");
    }

    public function updateValueProps(Request $request)
    {
        $validated = $request->validate([
            'props'             => 'nullable|array',
            'props.*.title'     => 'required|string|max:100',
            'props.*.subtext'   => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            PricingValueProp::truncate();
            foreach ($validated['props'] ?? [] as $idx => $prop) {
                PricingValueProp::create([
                    'title'      => trim($prop['title']),
                    'subtext'    => trim($prop['subtext']),
                    'sort_order' => $idx + 1,
                ]);
            }
        });

        return back()->with('success', 'Value propositions updated successfully.');
    }
}
