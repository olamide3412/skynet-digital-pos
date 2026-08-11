<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use App\Services\RoleService;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = Auth::guard('web')->check() ? Auth::guard('web')->user() : null;
        $branch = current_branch();

        return [
            ...parent::share($request),

            // ── Auth ──────────────────────────────────────────────────────────
            'auth' => [
                'user' => fn() => $user,
                'customer' => fn() => null,
                'check' => fn() => Auth::guard('web')->check(),
                'type' => fn() => Auth::guard('web')->check() ? 'staff' : null,
            ],

            // ── Current Branch (null in Super Admin context) ───────────────────
            'current_branch' => fn() => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'logo_url' => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'address' => $branch->address,
            ] : null,

            // ── Flash messages ─────────────────────────────────────────────────
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'message' => fn() => $request->session()->get('message'),
            ],

            // ── Dynamic Global System Configuration ───────────────────────────
            'system_config' => fn() => [
                'company_name'       => \App\Models\SystemSetting::get('company_name', 'Skynet POS'),
                'company_short_name' => \App\Models\SystemSetting::get('company_short_name', 'Skynet'),
                'app_tagline'        => \App\Models\SystemSetting::get('app_tagline', 'Digital POS & Inventory Terminal'),
                'currency_symbol'    => \App\Models\SystemSetting::get('currency_symbol', '₦'),
                'support_phone'      => \App\Models\SystemSetting::get('support_phone', '+234 803 207 2831'),
                'support_email'      => \App\Models\SystemSetting::get('support_email', 'support@skynetdigitalltd.com'),
                'company_logo_url'   => \App\Models\SystemSetting::get('company_logo_path')
                    ? asset('storage/' . \App\Models\SystemSetting::get('company_logo_path'))
                    : null,
            ],

            // ── Support info ───────────────────────────────────────────────────
            'support' => [
                'phone'           => \App\Models\SystemSetting::get('support_phone', '+234 803 207 2831'),
                'phone_whatsapp'   => '2348032072831',
                'phone_formatted'  => \App\Models\SystemSetting::get('support_phone', '+234 803 207 2831'),
                'email'            => \App\Models\SystemSetting::get('support_email', 'support@skynetdigitalltd.com'),
                'location'         => 'Delta State, Nigeria',
            ],

            'csrf_token' => csrf_token(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),

            // ── POS permissions (Spatie-backed, branch-scoped) ─────────────────
            'pos_permissions' => fn() => ($user && !$user->isSuperAdmin())
                ? RoleService::allPermissions()
                : [],

            'theme_color'          => fn() => \App\Models\SystemSetting::get('primary_color_theme', 'skynet'),
            'custom_primary_hex'   => fn() => \App\Models\SystemSetting::get('custom_primary_hex', '#7B00FF'),
            'custom_secondary_hex' => fn() => \App\Models\SystemSetting::get('custom_secondary_hex', '#FBA43D'),

            'cart_count'     => fn() => 0,
            'compare_count'  => fn() => 0,
            'store_settings' => fn() => [
                'company_name'  => \App\Models\SystemSetting::get('company_name', 'Skynet POS'),
                'company_email' => \App\Models\SystemSetting::get('support_email', 'support@skynetdigitalltd.com'),
                'company_phone' => \App\Models\SystemSetting::get('support_phone', '+234 803 207 2831'),
                'company_logo'  => \App\Models\SystemSetting::get('company_logo_path'),
            ],
            'categories'     => fn() => [],
        ];
    }
}
