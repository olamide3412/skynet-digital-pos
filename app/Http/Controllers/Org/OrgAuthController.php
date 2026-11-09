<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Turnstile;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrgAuthController extends Controller
{
    /**
     * Show Organization Admin login page.
     */
    public function showLogin()
    {
        if (!config('organizations.enabled')) {
            abort(404, 'Organization portal is disabled.');
        }

        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if ($user->isOrganizationAdmin() || $user->isSuperAdmin()) {
                return redirect()->route('org.dashboard');
            }
        }

        return Inertia::render('Org/Auth/Login', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
        ]);
    }

    /**
     * Authenticate Organization Admin.
     */
    public function login(Request $request)
    {
        if (!config('organizations.enabled')) {
            abort(404, 'Organization portal is disabled.');
        }

        $data = $request->validate([
            'login'                 => ['required', 'string'],
            'password'              => ['required'],
            'remember'              => ['nullable', 'boolean'],
            'cf_turnstile_response' => [new Turnstile()],
        ]);

        $login = $data['login'];
        $remember = $request->boolean('remember');
        $key   = 'org-login:' . Str::lower($login) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'login' => "Too many attempts. Try again in {$seconds} seconds.",
            ]);
        }

        $field       = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $credentials = [$field => $login, 'password' => $data['password']];

        $attempted = Auth::guard('web')->attempt($credentials, $remember);

        if (!$attempted && $field === 'email') {
            $credentials = ['username' => $login, 'password' => $data['password']];
            $attempted   = Auth::guard('web')->attempt($credentials, $remember);
        }

        if ($attempted) {
            $user = Auth::guard('web')->user();

            if (!$user->is_active) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'login' => 'Your account has been disabled. Please contact support.',
                ]);
            }

            if (!$user->isOrganizationAdmin() && !$user->isSuperAdmin()) {
                Auth::guard('web')->logout();
                RateLimiter::hit($key, 7200);
                throw ValidationException::withMessages([
                    'login' => 'Access denied. This login portal is for Organization Administrators only.',
                ]);
            }

            if ($user->isOrganizationAdmin() && !$user->isSuperAdmin()) {
                if (!$user->organization_id || !$user->organization) {
                    Auth::guard('web')->logout();
                    throw ValidationException::withMessages([
                        'login' => 'No client organization is assigned to your account.',
                    ]);
                }

                if ($user->organization->status === 'suspended') {
                    Auth::guard('web')->logout();
                    throw ValidationException::withMessages([
                        'login' => 'Your organization account is currently suspended. Please contact platform support.',
                    ]);
                }
            }

            RateLimiter::clear($key);
            $request->session()->regenerate();
            ActivityLogger::auth("Organization Admin {$user->name} logged into Organization Portal", null, null, $user->id);

            return redirect()->route('org.dashboard');
        }

        RateLimiter::hit($key, 7200);
        throw ValidationException::withMessages([
            'login' => 'Invalid credentials.',
        ]);
    }

    /**
     * Log out of the Organization Portal.
     */
    public function destroy(Request $request)
    {
        $user = Auth::guard('web')->user();
        if ($user) {
            ActivityLogger::auth("Organization Admin {$user->name} logged out of Organization Portal", null, null, $user->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('org.login');
    }
}
