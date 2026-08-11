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

        $field       = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $login, 'password' => $data['password']];

        $attempted = Auth::guard('web')->attempt($credentials, false);

        if (!$attempted && $field === 'email') {
            $credentials = ['username' => $login, 'password' => $data['password']];
            $attempted   = Auth::guard('web')->attempt($credentials, false);
        }

        if ($attempted) {
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
            \App\Services\ActivityLogger::auth("Super Admin {$user->name} signed into SuperAdmin portal", null, null, $user->id);
            return redirect()->route('superadmin.dashboard');
        }

        RateLimiter::hit($key, 7200);
        throw ValidationException::withMessages(['login' => 'Invalid credentials.']);
    }

    public function destroy(Request $request)
    {
        $user = Auth::guard('web')->user();
        if ($user) {
            \App\Services\ActivityLogger::auth("Super Admin {$user->name} signed out of SuperAdmin portal", null, null, $user->id);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('superadmin.login');
    }
}
