<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\PricingIncludedItem;
use App\Models\PricingPlan;
use App\Models\PricingSetting;
use App\Models\PricingValueProp;
use Inertia\Inertia;

class HomeController extends Controller
{
    private function getCommonData(): array
    {
        $defaultBranch = Branch::where('is_active', true)
            ->orderBy('id')
            ->first(['id', 'name', 'slug', 'address', 'phone', 'email', 'logo_path', 'is_ai_enabled']);

        $defaultBranchData = $defaultBranch ? [
            'id'            => $defaultBranch->id,
            'name'          => $defaultBranch->name,
            'slug'          => $defaultBranch->slug,
            'address'       => $defaultBranch->address,
            'phone'         => $defaultBranch->phone,
            'email'         => $defaultBranch->email,
            'logo_url'      => $defaultBranch->logo_path ? asset('storage/' . $defaultBranch->logo_path) : null,
            'is_ai_enabled' => (bool) $defaultBranch->is_ai_enabled,
        ] : null;

        // High-level aggregate metrics for social proof
        $itemsCount    = Item::count();
        $salesCount    = Sale::count();

        $supportPhone  = SystemSetting::get('support_phone', '+234 803 207 2831');
        $cleanPhone    = preg_replace('/[^0-9]/', '', $supportPhone);
        $companyName   = SystemSetting::get('company_name', 'Skynet Digital POS');
        $appTagline    = SystemSetting::get('app_tagline', 'AI-Powered Cloud & Offline Point of Sale');

        $pricingSettings = PricingSetting::current();
        $pricingData = null;

        if ($pricingSettings && $pricingSettings->show_pricing_section) {
            $pricingPlans = PricingPlan::where('is_active', true)
                ->with(['features' => function ($q) {
                    $q->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            $includedItems = PricingIncludedItem::orderBy('sort_order')->get();
            $valueProps = PricingValueProp::orderBy('sort_order')->get();

            $pricingData = [
                'enabled'        => true,
                'settings'       => $pricingSettings,
                'plans'          => $pricingPlans,
                'included_items' => $includedItems,
                'value_props'    => $valueProps,
            ];
        }

        return [
            'default_branch' => $defaultBranchData,
            'stats'          => [
                'items_count'      => $itemsCount,
                'sales_count'      => $salesCount,
            ],
            'contact_info'   => [
                'phone'          => $supportPhone,
                'clean_phone'    => $cleanPhone,
                'email'          => SystemSetting::get('support_email', 'support@skynetdigitalltd.com'),
                'whatsapp'       => $cleanPhone,
                'company_name'       => $companyName,
                'company_short_name' => SystemSetting::get('company_short_name', 'Skynet'),
                'app_tagline'        => $appTagline,
                'address'            => SystemSetting::get('company_address', SystemSetting::get('business_address', 'Delta State, Nigeria')),
            ],
            'pricing'        => $pricingData,
        ];
    }

    public function index()
    {
        return Inertia::render('Home', $this->getCommonData());
    }

    public function features()
    {
        return Inertia::render('Features', $this->getCommonData());
    }

    public function industries()
    {
        return Inertia::render('Industries', $this->getCommonData());
    }

    public function pricing()
    {
        $data = $this->getCommonData();
        if (!$data['pricing'] || !$data['pricing']['enabled']) {
            return redirect()->route('home');
        }
        return Inertia::render('Pricing', $data);
    }

    public function terms()
    {
        return Inertia::render('Terms', $this->getCommonData());
    }

    public function privacy()
    {
        return Inertia::render('Privacy', $this->getCommonData());
    }

    public function refundPolicy()
    {
        return Inertia::render('RefundPolicy', $this->getCommonData());
    }

    public function security()
    {
        return Inertia::render('Security', $this->getCommonData());
    }
}
