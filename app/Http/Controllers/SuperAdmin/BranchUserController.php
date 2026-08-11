<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class BranchUserController extends Controller
{
    public const ALL_PERMISSIONS = [
        'canAccessPos'       => 'Access POS Terminal',
        'canViewSales'       => 'View Sales History',
        'canDeleteSale'      => 'Delete / Void Sale',
        'canProcessReturn'   => 'Process Sale Returns',
        'canApplyDiscount'   => 'Apply Discounts',
        'canEditPrice'       => 'Edit Selling Price',
        'canViewBuyPrice'    => 'View Product Cost / Buy Price',
        'canViewEndOfDay'    => 'View End-of-Day Report',
        'canViewReports'     => 'View Reports Dashboard & Sales',
        'canViewProfitLoss'  => 'View Profit & Loss Report',
        'canManageDebt'      => 'View Customer Debt Ledger',
        'canGiveDebt'        => 'Give Debt / Sell on Credit',
        'canAdjustStock'     => 'Adjust Stock / Inventory Logs',
        'canTransferStock'   => 'Transfer Stock (Front/Back Store)',
        'canManagePurchases' => 'Manage Purchases & Vendors',
        'canManageItems'     => 'Manage Items, Categories & Grid',
        'canManageCustomers' => 'Manage Customers Directory',
        'canManageUsers'     => 'Manage Staff Accounts & Roles',
        'canEditSettings'    => 'Edit Branch Settings & POS Config',
        'canResetPassword'   => 'Reset Staff Passwords (Super Admin Granted)',
    ];

    public function index(Branch $branch)
    {
        // ⚠ Must set team ID BEFORE loading roles and permissions
        setPermissionsTeamId($branch->id);

        $users = User::where('branch_id', $branch->id)
            ->with(['roles', 'permissions'])
            ->latest()
            ->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'username'    => $u->username,
                'email'       => $u->email,
                'is_active'   => $u->is_active,
                'roles'       => $u->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
                'permissions' => $u->getAllPermissions()->pluck('name')->values(),
            ]);

        $roles = Role::where('team_id', $branch->id)->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Branches/Users', [
            'branch'          => $branch,
            'users'           => $users,
            'roles'           => $roles,
            'all_permissions' => collect(self::ALL_PERMISSIONS)->map(fn ($label, $name) => ['name' => $name, 'label' => $label])->values(),
        ]);
    }

    public function store(Request $request, Branch $branch)
    {
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

        \App\Services\ActivityLogger::userAction("Created staff user '{$user->name}' (@{$user->username}) in branch '{$branch->name}'", $branch->id);

        return redirect()
            ->route('superadmin.branches.users.index', $branch->slug)
            ->with('success', 'User added to branch.');
    }

    public function update(Request $request, Branch $branch, User $user)
    {
        $this->validateUserBelongsToBranch($user, $branch);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'username'    => 'required|string|max:50|unique:users,username,' . $user->id,
            'email'       => 'nullable|email|max:255|unique:users,email,' . $user->id,
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

        setPermissionsTeamId($branch->id);
        $user->syncRoles([$data['role']]);

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        \App\Services\ActivityLogger::userAction("Updated staff profile for '{$user->name}'", $branch->id);

        return redirect()
            ->route('superadmin.branches.users.index', $branch->slug)
            ->with('success', 'User updated.');
    }

    public function destroy(Branch $branch, User $user)
    {
        $this->validateUserBelongsToBranch($user, $branch);
        $userName = $user->name;
        $user->delete();
        \App\Services\ActivityLogger::userAction("Removed user '{$userName}' from branch '{$branch->name}'", $branch->id);
        return redirect()
            ->route('superadmin.branches.users.index', $branch->slug)
            ->with('success', 'User removed from branch.');
    }

    public function toggle(Branch $branch, User $user)
    {
        $this->validateUserBelongsToBranch($user, $branch);
        $user->update(['is_active' => !$user->is_active]);
        $status = $user->is_active ? 'enabled' : 'disabled';
        \App\Services\ActivityLogger::userAction("Toggled account status to '{$status}' for user '{$user->name}'", $branch->id);
        return redirect()
            ->route('superadmin.branches.users.index', $branch->slug)
            ->with('success', "User account {$status}.");
    }

    public function resetPassword(Request $request, Branch $branch, User $user)
    {
        $this->validateUserBelongsToBranch($user, $branch);

        $data = $request->validate([
            'password' => ['required', Rules\Password::defaults(), 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);
        \App\Services\ActivityLogger::userAction("Reset password for user '{$user->name}'", $branch->id);
        return redirect()
            ->route('superadmin.branches.users.index', $branch->slug)
            ->with('success', 'Password reset successfully.');
    }

    protected function validateUserBelongsToBranch(User $user, Branch $branch): void
    {
        if ($user->branch_id !== $branch->id) {
            abort(403, 'This user does not belong to the specified branch.');
        }
    }
}
