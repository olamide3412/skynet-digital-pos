<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * All permissions available in the system.
     * Branch Admins can mix/match these when creating custom roles.
     */
    private const AVAILABLE_PERMISSIONS = [
        'canAccessPos', 'canViewSales', 'canDeleteSale', 'canProcessReturn',
        'canApplyDiscount', 'canEditPrice', 'canViewBuyPrice', 'canViewEndOfDay',
        'canViewReports', 'canViewProfitLoss', 'canManageDebt', 'canGiveDebt',
        'canAdjustStock', 'canTransferStock', 'canManagePurchases', 'canManageItems',
        'canManageBarcodes', 'canManageCustomers', 'canManageUsers', 'canEditSettings',
    ];

    public function index()
    {
        $branch = current_branch();
        setPermissionsTeamId($branch->id);

        $roles = Role::where('team_id', $branch->id)
            ->withCount('users')
            ->with('permissions:id,name')
            ->get();

        return Inertia::render('Roles/Index', [
            'roles'               => $roles,
            'availablePermissions'=> self::AVAILABLE_PERMISSIONS,
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'name'        => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', self::AVAILABLE_PERMISSIONS),
        ]);

        setPermissionsTeamId($branch->id);

        // Ensure role name is unique within this branch
        if (Role::where('team_id', $branch->id)->where('name', $data['name'])->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists in this branch.']);
        }

        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => 'web',
            'team_id'    => $branch->id,
        ]);

        if (!empty($data['permissions'])) {
            $permissions = Permission::whereIn('name', $data['permissions'])->get();
            $role->givePermissionTo($permissions);
        }

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, $branchParam, Role $role)
    {
        $branch = current_branch();
        $this->authorizeBranchRole($role, $branch);

        $data = $request->validate([
            'name'        => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', self::AVAILABLE_PERMISSIONS),
        ]);

        // Check uniqueness excluding current role
        if (Role::where('team_id', $branch->id)
                ->where('name', $data['name'])
                ->where('id', '!=', $role->id)
                ->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists in this branch.']);
        }

        $role->update(['name' => $data['name']]);

        setPermissionsTeamId($branch->id);
        $permissions = Permission::whereIn('name', $data['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);

        return back()->with('success', 'Role updated.');
    }

    public function destroy($branchParam, Role $role)
    {
        $branch = current_branch();
        $this->authorizeBranchRole($role, $branch);

        // Prevent deleting core system roles
        if (in_array(strtolower($role->name), ['branch-admin', 'cashier'])) {
            return back()->withErrors(['role' => 'Cannot delete a core system role.']);
        }

        if ($role->users()->count() > 0) {
            return back()->withErrors(['role' => 'Cannot delete a role that is assigned to users.']);
        }

        $role->delete();
        return back()->with('success', 'Role deleted.');
    }

    protected function authorizeBranchRole(Role $role, $branch): void
    {
        if ($role->team_id !== $branch?->id) {
            abort(403, 'This role does not belong to your branch.');
        }
    }
}
