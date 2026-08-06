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

            // ── Support info ───────────────────────────────────────────────────
            'support' => [
                'phone' => '+2348032072831',
                'phone_whatsapp' => '2348032072831',
                'phone_formatted' => '+234 803 207 2831',
                'email' => 'support@skynetdigitalltd.com',
                'location' => 'Delta State, Nigeria',
            ],

            'csrf_token' => csrf_token(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),

            // ── POS permissions (Spatie-backed, branch-scoped) ─────────────────
            'pos_permissions' => fn() => ($user && !$user->isSuperAdmin())
                ? RoleService::allPermissions()
                : [],

            'cart_count' => fn() => 0,
            'compare_count' => fn() => 0,
            'store_settings' => fn() => [],
            'categories' => fn() => [],
        ];
    }
}
