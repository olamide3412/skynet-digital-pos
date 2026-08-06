<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Display SuperAdmin Profile Page.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('SuperAdmin/Profile/Index', [
            'user' => [
                'id'         => $user->id,
                'name'       => $user->name,
                'full_name'  => $user->full_name,
                'username'   => $user->username,
                'email'      => $user->email,
                'is_active'  => $user->is_active,
                'created_at' => $user->created_at->format('d M Y, h:i A'),
            ],
        ]);
    }

    /**
     * Update Profile details (name, full_name, email, username).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'username'  => ['required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'email'     => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        return back()->with('success', 'SuperAdmin profile details updated successfully.');
    }

    /**
     * Update SuperAdmin password.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'The current password you provided does not match.']);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'SuperAdmin password updated successfully.');
    }
}
