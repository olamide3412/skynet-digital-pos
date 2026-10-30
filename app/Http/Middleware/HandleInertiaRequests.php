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
        $segment1 = $request->segment(1);
        $reservedPrefixes = ['org', 'superadmin', 'admin', 'api', 'build', 'storage', 'images', 'vendor', 'livewire', 'login', 'logout', 'register', 'password'];
        $urlBranch = ($segment1 && !in_array($segment1, $reservedPrefixes, true))
            ? \App\Models\Branch::where('slug', $segment1)->first()
            : null;

        $activeBranch = current_branch() 
            ?? $urlBranch
            ?? ($user?->branch_id ? \App\Models\Branch::find($user->branch_id) : null);

        if ($activeBranch && !app()->bound('current_branch')) {
            app()->instance('current_branch', $activeBranch);
        }

        $defaultBranch = \App\Models\Branch::orderBy('id')->first();
        $branch = $activeBranch ?? $defaultBranch;

        $isBranchScope = ($urlBranch !== null) || (!in_array($segment1, $reservedPrefixes, true) && $activeBranch !== null);
        $themeBranch = $isBranchScope ? ($urlBranch ?? $activeBranch) : null;

        if ($branch) {
            \Illuminate\Support\Facades\URL::defaults(['branch' => $branch->slug]);
        }

        return [
            ...parent::share($request),
            'csrf_token' => fn() => csrf_token(),
            'branch_slug' => fn() => $branch?->slug,
            'current_branch_slug' => fn() => $branch?->slug,
            'ziggy' => fn() => [
                'defaults' => array_filter([
                    'branch' => $branch?->slug,
                ]),
            ],

            // ── Auth ──────────────────────────────────────────────────────────
            'auth' => [
                'user' => fn() => $user,
                'customer' => fn() => null,
                'check' => fn() => Auth::guard('web')->check(),
                'type' => fn() => Auth::guard('web')->check() ? 'staff' : null,
            ],

            // ── Current Branch (dynamically falls back to first branch in DB) ──
            'current_branch' => fn() => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'logo_url' => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'address' => $branch->address,
                'organization_id' => $branch->organization_id,
            ] : null,

            // ── Current Organization Subscription (Read-only for all branch/org panels) ──
            'current_subscription' => function () use ($user, $branch) {
                $org = null;
                if ($user?->organization_id) {
                    $org = $user->organization;
                } elseif ($branch?->organization_id) {
                    $org = $branch->organization;
                }

                if (!$org) {
                    return null;
                }

                $sub = $org->subscription()->with('pricingPlan')->first();
                if (!$sub) {
                    return null;
                }

                return [
                    'organization_id'    => $org->id,
                    'organization_name'  => $org->name,
                    'plan_name'          => $sub->pricingPlan?->name ?? 'None',
                    'plan_tagline'       => $sub->pricingPlan?->tagline,
                    'started_at'         => $sub->started_at?->format('d M Y'),
                    'renewal_date'       => $sub->renewal_date?->format('d M Y'),
                    'days_until_renewal' => $sub->daysUntilRenewal(),
                    'status'             => $sub->status,
                    'computed_status'    => $sub->computedStatus(),
                    'is_expiring_soon'   => $sub->isExpiringSoon(),
                    'is_past_due'        => $sub->isPastDue(),
                ];
            },

            // ── Current Plan Features & Limits (Granular capability flags) ────
            'plan_features' => function () use ($user, $branch) {
                $org = null;
                if ($branch?->organization_id) {
                    $org = $branch->organization;
                } elseif ($user?->organization_id) {
                    $org = $user->organization;
                }

                if (!$org) {
                    return [
                        'is_restricted'              => false,
                        'max_branches'               => null,
                        'max_users_per_branch'       => null,
                        'multi_branch'               => true,
                        'debt_customer_accounts'     => true,
                        'discounts'                  => true,
                        'batch_expiry_tracking'      => true,
                        'imei_tracking'              => true,
                        'barcode_label_printing'     => true,
                        'inventory_import'           => true,
                        'advanced_staff_permissions' => true,
                        'ai_chat_assistant'          => true,
                        'ai_reorder_prediction'      => true,
                        'broadcast_notifications'    => true,
                        'feature_accounting'         => true,
                        'accounting'                 => true,
                    ];
                }

                return $org->getFeatureSummary();
            },

            // ── Default Branch (first created branch in database) ─────────────
            'default_branch' => fn() => $defaultBranch ? [
                'id' => $defaultBranch->id,
                'name' => $defaultBranch->name,
                'slug' => $defaultBranch->slug,
                'logo_url' => $defaultBranch->logo_path ? asset('storage/' . $defaultBranch->logo_path) : null,
            ] : null,

            // ── Flash messages ─────────────────────────────────────────────────
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'message' => fn() => $request->session()->get('message'),
                'new_commerce_token' => fn() => $request->session()->get('new_commerce_token'),
            ],
            'new_commerce_token' => fn() => $request->session()->get('new_commerce_token'),

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
            'organizations_enabled' => (bool) config('organizations.enabled'),

            // ── POS permissions (Spatie-backed, branch-scoped) ─────────────────
            'pos_permissions' => fn() => $user
                ? RoleService::allPermissions()
                : [],

            'theme_color' => function () use ($themeBranch) {
                if ($themeBranch) {
                    $bTheme = $themeBranch->getSettings()->theme_color;
                    if (!empty($bTheme)) {
                        return $bTheme;
                    }
                }
                return \App\Models\SystemSetting::get('primary_color_theme', 'skynet');
            },
            'custom_primary_hex' => function () use ($themeBranch) {
                if ($themeBranch) {
                    $bHex = $themeBranch->getSettings()->custom_primary_hex;
                    if (!empty($bHex)) {
                        return $bHex;
                    }
                }
                return \App\Models\SystemSetting::get('custom_primary_hex', '#7B00FF');
            },
            'custom_secondary_hex' => function () use ($themeBranch) {
                if ($themeBranch) {
                    $bHex = $themeBranch->getSettings()->custom_secondary_hex;
                    if (!empty($bHex)) {
                        return $bHex;
                    }
                }
                return \App\Models\SystemSetting::get('custom_secondary_hex', '#FBA43D');
            },
            'dark_mode_accent_mode' => function () use ($themeBranch) {
                if ($themeBranch) {
                    $bMode = $themeBranch->getSettings()->dark_mode_accent_mode;
                    if (!empty($bMode)) {
                        return $bMode;
                    }
                }
                return \App\Models\SystemSetting::get('dark_mode_accent_mode', 'white');
            },
            'custom_dark_primary_hex' => function () use ($themeBranch) {
                if ($themeBranch) {
                    $bHex = $themeBranch->getSettings()->custom_dark_primary_hex;
                    if (!empty($bHex)) {
                        return $bHex;
                    }
                }
                return \App\Models\SystemSetting::get('custom_dark_primary_hex', '#FFFFFF');
            },

            // ── Branch Notifications (User Scoped) ─────────────────────────────
            'unread_notifications_count' => fn() => $branch ? \App\Services\NotificationService::getUnreadCount($branch->id, $user?->id) : 0,
            'unresolved_conflicts_count' => fn() => $branch ? \App\Models\AppNotification::forBranch($branch->id)->forUser($user?->id)->whereIn('type', ['stock_conflict', 'imei_conflict'])->where('is_resolved', false)->count() : 0,
            'recent_notifications'       => fn() => $branch ? \App\Services\NotificationService::getRecentPreview($branch->id, 6, $user?->id) : [],

            // ── POS Branch Settings ───────────────────────────────────────────
            'pos_settings' => fn() => $branch ? [
                'batch_tracking_enabled' => (bool) (\App\Models\PosSettings::forBranch($branch->id)->batch_tracking_enabled ?? false),
                'expiry_alert_days'      => (int) (\App\Models\PosSettings::forBranch($branch->id)->expiry_alert_days ?? 30),
                'laundry_enabled'        => (bool) $branch->laundryEnabled(),
                'pos_enabled'            => (bool) $branch->posEnabled(),
                'default_interface'      => (string) $branch->defaultInterface(),
                'require_till_opening'   => (bool) (\App\Models\PosSettings::forBranch($branch->id)->require_till_opening ?? false),
            ] : null,

            'active_till_session' => fn() => ($user && $branch) ? (function() use ($user, $branch) {
                $session = \App\Services\TillSessionService::getActiveSession($user->id, $branch->id);
                if ($session) {
                    $session->load('cashMovements.performer:id,name');
                    return array_merge($session->toArray(), [
                        'summary' => $session->calculateSummary(),
                    ]);
                }
                return null;
            })() : null,

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
