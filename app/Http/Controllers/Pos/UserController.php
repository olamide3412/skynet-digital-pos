<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q) => $q
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('username', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => Role::all(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        if (!RoleService::canManageUsers()) abort(403, 'Insufficient permissions.');
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users',
            'email'    => 'nullable|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
            'status'   => 'required|in:Active,Inactive',
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'username' => $data['username'],
                'email'    => $data['email'],
                'password' => Hash::make($data['password']),
                'type'     => 'admin', // or maybe 'pos', assuming they can login to pos
                'status'   => $data['status'] === 'Active' ? 1 : 0,
            ]);

            $user->roles()->sync($data['role_ids']);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        if (!RoleService::canManageUsers()) abort(403, 'Insufficient permissions.');
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username,' . $user->id,
            'email'    => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', Rules\Password::defaults()],
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
            'status'   => 'required|in:Active,Inactive',
        ]);

        DB::transaction(function () use ($data, $user) {
            $user->update([
                'name'     => $data['name'],
                'username' => $data['username'],
                'email'    => $data['email'],
                'status'   => $data['status'] === 'Active' ? 1 : 0,
            ]);

            if (!empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            $user->roles()->sync($data['role_ids']);
        });

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if (!RoleService::canManageUsers()) abort(403, 'Insufficient permissions.');
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete yourself.']);
        }
        $user->delete();
        return back()->with('success', 'User removed.');
    }
}
