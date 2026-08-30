<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SystemSetting;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
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

        return Inertia::render('Home', [
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
                'company_name'   => $companyName,
                'app_tagline'    => $appTagline,
            ],
        ]);
    }
}
