<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SuperAdminAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('web')->check() && Auth::guard('web')->user()->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }
        return inertia('SuperAdmin/Auth/Login', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login'                 => ['required', 'string'],
            'password'              => ['required'],
            'cf_turnstile_response' => [new Turnstile()],
        ]);

        $login = $data['login'];
        $key   = 'superadmin-login:' . Str::lower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $credentials = ['email' => $login, 'password' => $data['password']];

        if (Auth::guard('web')->attempt($credentials, false)) {
            $user = Auth::guard('web')->user();

            if (!$user->isSuperAdmin()) {
                Auth::guard('web')->logout();
                RateLimiter::hit($key, 7200);
                throw ValidationException::withMessages([
                    'login' => 'Access denied. This login is for Super Admins only.',
                ]);
            }

            if (!$user->is_active) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'login' => 'Your account has been disabled.',
                ]);
            }

            RateLimiter::clear($key);
            $request->session()->regenerate();
            return redirect()->route('superadmin.dashboard');
        }

        RateLimiter::hit($key, 7200);
        throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('superadmin.login');
    }
}
