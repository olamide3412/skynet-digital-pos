<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        setPermissionsTeamId($branch->id);

        $users = User::where('branch_id', $branch->id)
            ->with(['roles', 'permissions'])
            ->when($request->search, fn($q) => $q
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('username', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Transform users to include permission array
        $users->through(fn (User $u) => [
            'id'          => $u->id,
            'name'        => $u->name,
            'username'    => $u->username,
            'email'       => $u->email,
            'is_active'   => $u->is_active,
            'roles'       => $u->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
            'permissions' => $u->getAllPermissions()->pluck('name')->values(),
        ]);

        // Branch-scoped roles
        $roles = Role::where('team_id', $branch->id)
            ->orWhereNull('team_id')
            ->get(['id', 'name']);

        return Inertia::render('Users/Index', [
            'users'           => $users,
            'roles'           => $roles,
            'all_permissions' => collect(\App\Http\Controllers\SuperAdmin\BranchUserController::ALL_PERMISSIONS)
                ->reject(fn ($label, $name) => $name === 'canResetPassword')
                ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
                ->values(),
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'username'    => 'required|string|max:50|unique:users,username',
            'email'       => 'nullable|email|max:255|unique:users,email',
            'password'    => ['required', Rules\Password::defaults()],
            'role'        => 'required|string',
            'permissions' => 'nullable|array',
            'is_active'   => 'boolean',
        ]);

        $user = User::create([
            'name'           => $data['name'],
            'full_name'      => $data['name'],
            'username'       => $data['username'],
            'email'          => $data['email'] ?? null,
            'password'       => Hash::make($data['password']),
            'is_active'      => $data['is_active'] ?? true,
            'is_super_admin' => false,
            'branch_id'      => $branch->id,
        ]);

        setPermissionsTeamId($branch->id);
        $user->assignRole($data['role']);

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, $branchParam, User $user)
    {
        $branch = current_branch();
        $this->authorizeBranch($user, $branch);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'username'    => 'required|string|max:50|unique:users,username,' . $user->id,
            'email'       => 'nullable|email|max:255|unique:users,email,' . $user->id,
            'password'    => ['nullable', Rules\Password::defaults()],
            'role'        => 'required|string',
            'permissions' => 'nullable|array',
            'is_active'   => 'boolean',
        ]);

        $user->update([
            'name'      => $data['name'],
            'full_name' => $data['name'],
            'username'  => $data['username'],
            'email'     => $data['email'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        if (!empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        setPermissionsTeamId($branch->id);
        $user->syncRoles([$data['role']]);

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return back()->with('success', 'User updated.');
    }

    public function destroy($branchParam, User $user)
    {
        return back()->withErrors(['user' => 'Only Super Admins can delete user accounts. You can disable the user account to revoke access.']);
    }

    public function toggle($branchParam, User $user)
    {
        $branch = current_branch();
        $this->authorizeBranch($user, $branch);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot disable your own account.']);
        }

        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'enabled' : 'disabled';
        return redirect()->route('pos.users.index')->with('success', "User account {$status}.");
    }

    public function resetPassword(Request $request, $branchParam, User $user)
    {
        $branch = current_branch();
        $this->authorizeBranch($user, $branch);

        if (!\App\Services\RoleService::canResetPassword()) {
            return back()->withErrors(['user' => 'You do not have permission granted by Super Admin to reset staff passwords.']);
        }

        $data = $request->validate([
            'password' => ['required', Rules\Password::defaults(), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        return redirect()->route('pos.users.index')->with('success', 'Password reset successfully.');
    }

    protected function authorizeBranch(User $user, $branch): void
    {
        if ($user->branch_id !== $branch?->id) {
            abort(403, 'This user does not belong to your branch.');
        }
    }
}
