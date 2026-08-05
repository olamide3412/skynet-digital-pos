<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Show branch-specific login page.
     * Branch is already resolved by ResolveBranchMiddleware.
     */
    public function showLogin(Request $request)
    {
        $branch = current_branch();

        // If already logged in and belongs to this branch, redirect to POS
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($user->branch_id === $branch?->id || $user->isSuperAdmin()) {
                return redirect()->route('pos.index', ['branch' => $branch?->slug]);
            }
        }

        return inertia('Auth/Login', [
            'branch' => $branch ? [
                'name'    => $branch->name,
                'slug'    => $branch->slug,
                'logo_url'=> $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
            ] : null,
        ]);
    }

    public function login(Request $request)
    {
        $branch = current_branch();

        $attributes = $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required'],
        ]);

        $login = $attributes['login'];
        $key   = 'branch-login:' . ($branch?->slug ?? 'none') . ':' . Str::lower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // Try email first, then username
        $credentials = ['email' => $login, 'password' => $attributes['password']];
        $attempted   = Auth::guard('web')->attempt($credentials, false);

        if (!$attempted) {
            $credentials = ['username' => $login, 'password' => $attributes['password']];
            $attempted   = Auth::guard('web')->attempt($credentials, false);
        }

        if ($attempted) {
            $user = Auth::guard('web')->user();

            // Super Admin should not log in via branch login
            if ($user->isSuperAdmin()) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'login' => 'Super Admins must log in via the Super Admin panel.',
                ]);
            }

            // Must belong to this branch
            if ($branch && $user->branch_id !== $branch->id) {
                Auth::guard('web')->logout();
                RateLimiter::hit($key, 7200);
                throw ValidationException::withMessages([
                    'login' => 'You do not have access to this branch.',
                ]);
            }

            // Account must be active
            if (!$user->is_active) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'login' => 'Your account has been disabled.',
                ]);
            }

            log_new("Branch login: {$user->name} @ {$branch?->name}");
            RateLimiter::clear($key);
            $request->session()->regenerate();

            return redirect()->route('pos.index', ['branch' => $branch?->slug]);
        }

        RateLimiter::hit($key, 7200);
        throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
    }

    public function destroy(Request $request)
    {
        // Load user and branch BEFORE logout clears auth
        $user       = Auth::guard('web')->user();
        $branchSlug = current_branch()?->slug
                      ?? ($user?->branch_id ? optional($user->branch)->slug : null);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($branchSlug) {
            return redirect()->route('pos.login', ['branch' => $branchSlug]);
        }

        return redirect()->route('superadmin.login');
    }

    protected function formatLockoutTime($seconds): string
    {
        if ($seconds >= 3600) {
            $hours   = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') .
                   ($minutes > 0 ? " and {$minutes} minute" . ($minutes > 1 ? 's' : '') : '');
        } elseif ($seconds >= 60) {
            $minutes          = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') .
                   ($remainingSeconds > 0 ? " and {$remainingSeconds} second" . ($remainingSeconds > 1 ? 's' : '') : '');
        }
        return $seconds . ' second' . ($seconds > 1 ? 's' : '');
    }
}
