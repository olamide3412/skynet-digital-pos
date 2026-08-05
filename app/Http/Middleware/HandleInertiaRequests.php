<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use App\Models\Cart;
use App\Models\Compare;
use App\Models\StoreSetting;
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
        $user   = Auth::guard('web')->check() ? Auth::guard('web')->user() : null;
        $branch = current_branch();

        return [
            ...parent::share($request),

            // ── Auth ──────────────────────────────────────────────────────────
            'auth' => [
                'user'     => fn() => $user,
                'customer' => fn() => $this->getAuthUser('customer')(),
                'check'    => fn() => $this->isAuthenticated()(),
                'type'     => fn() => $this->getAuthType(),
            ],

            // ── Current Branch (null in Super Admin context) ───────────────────
            'current_branch' => fn() => $branch ? [
                'id'       => $branch->id,
                'name'     => $branch->name,
                'slug'     => $branch->slug,
                'logo_url' => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
                'phone'    => $branch->phone,
                'email'    => $branch->email,
                'address'  => $branch->address,
            ] : null,

            // ── Flash messages ─────────────────────────────────────────────────
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error'   => fn() => $request->session()->get('error'),
                'message' => fn() => $request->session()->get('message'),
            ],

            // ── Support info ───────────────────────────────────────────────────
            'support' => [
                'phone'           => '+2348032072831',
                'phone_whatsapp'  => '2348032072831',
                'phone_formatted' => '+234 803 207 2831',
                'email'           => 'support@skynetdigitalhub.com.ng',
                'location'        => 'Delta State, Nigeria',
            ],

            'csrf_token'       => csrf_token(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),

            // ── POS permissions (Spatie-backed, branch-scoped) ─────────────────
            'pos_permissions' => fn() => ($user && !$user->isSuperAdmin())
                ? RoleService::allPermissions()
                : [],

            // ── E-commerce (storefront) ────────────────────────────────────────
            'cart_count'    => fn() => $this->getCartCount($request),
            'compare_count' => fn() => $this->getCompareCount($request),
            'store_settings' => fn() => class_exists(StoreSetting::class)
                ? StoreSetting::allAsArray()
                : [],
            'categories' => fn() => \App\Models\Category::whereNull('parent_id')
                ->whereNull('branch_id') // e-commerce categories only (no branch_id)
                ->where('visible_in_menu', true)
                ->with([
                    'children' => fn($q) => $q
                        ->where('visible_in_menu', true)
                        ->orderBy('menu_position', 'asc'),
                ])
                ->orderBy('menu_position', 'asc')
                ->get(),
        ];
    }

    protected function getAuthUser($guard)
    {
        try {
            return fn() => Auth::guard($guard)->check() ? Auth::guard($guard)->user() : null;
        } catch (\InvalidArgumentException) {
            return fn() => null;
        }
    }

    protected function isAuthenticated()
    {
        try {
            return fn() => Auth::guard('web')->check() || Auth::guard('customer')->check();
        } catch (\InvalidArgumentException) {
            return fn() => Auth::guard('web')->check();
        }
    }

    protected function getAuthType(): ?string
    {
        try {
            if (Auth::guard('web')->check())     return 'staff';
            if (Auth::guard('customer')->check()) return 'customer';
        } catch (\InvalidArgumentException) {
            if (Auth::guard('web')->check()) return 'staff';
        }
        return null;
    }

    protected function getCartCount(Request $request): int
    {
        try {
            if (Auth::guard('customer')->check()) {
                $cart = Cart::where('customer_id', Auth::guard('customer')->id())->first();
            } else {
                $cart = Cart::where('session_id', $request->session()->getId())->first();
            }
            return $cart ? $cart->items()->sum('quantity') : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    protected function getCompareCount(Request $request): int
    {
        try {
            if (Auth::guard('customer')->check()) {
                $compare = Compare::where('customer_id', Auth::guard('customer')->id())->first();
            } else {
                $compare = Compare::where('session_id', $request->session()->getId())->first();
            }
            return $compare ? $compare->items()->count() : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
