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
        $branches = Branch::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'address', 'phone', 'email', 'logo_path', 'is_ai_enabled'])
            ->map(function ($branch) {
                return [
                    'id'            => $branch->id,
                    'name'          => $branch->name,
                    'slug'          => $branch->slug,
                    'address'       => $branch->address,
                    'phone'         => $branch->phone,
                    'email'         => $branch->email,
                    'logo_url'      => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
                    'is_ai_enabled' => (bool) $branch->is_ai_enabled,
                ];
            });

        $defaultBranch = $branches->first();

        // High-level aggregate metrics for social proof
        $branchesCount = Branch::where('is_active', true)->count();
        $itemsCount    = Item::count();
        $salesCount    = Sale::count();

        $supportPhone  = SystemSetting::get('support_phone', '+234 803 207 2831');
        $cleanPhone    = preg_replace('/[^0-9]/', '', $supportPhone);
        $companyName   = SystemSetting::get('company_name', 'Skynet Digital POS');
        $appTagline    = SystemSetting::get('app_tagline', 'AI-Powered Cloud & Offline Point of Sale');

        return Inertia::render('Home', [
            'default_branch' => $defaultBranch,
            'branches'       => $branches,
            'stats'          => [
                'branches_count'   => $branchesCount,
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
