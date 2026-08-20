<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BranchRoleController extends Controller
{
    /** All POS permission names (must match RolePermissionSeeder::PERMISSIONS) */
    private const ALL_PERMISSIONS = [
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
        'canManageBarcodes'  => 'Manage & Print Barcodes',
        'canManageCustomers' => 'Manage Customers Directory',
        'canManageUsers'     => 'Manage Staff Accounts & Roles',
        'canEditSettings'    => 'Edit Branch Settings & POS Config',
        'canViewReorderPoints' => 'View Reorder Points & Stock Alerts',
    ];

    public function index(Branch $branch)
    {
        setPermissionsTeamId($branch->id);

        $roles = Role::where('team_id', $branch->id)
            ->with('permissions')
            ->get()
            ->map(fn (Role $role) => [
                'id'          => $role->id,
                'name'        => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ]);

        return Inertia::render('SuperAdmin/Branches/Roles', [
            'branch'      => $branch,
            'roles'       => $roles,
            'all_permissions' => collect(self::ALL_PERMISSIONS)
                ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
                ->values(),
        ]);
    }

    public function update(Request $request, Branch $branch, Role $role)
    {
        // Ensure role belongs to this branch
        if ($role->team_id !== $branch->id) {
            abort(403, 'Role does not belong to this branch.');
        }

        $data = $request->validate([
            'permissions'   => 'required|array',
            'permissions.*' => 'string|in:' . implode(',', array_keys(self::ALL_PERMISSIONS)),
        ]);

        setPermissionsTeamId($branch->id);

        // Sync permissions using global permission models
        $permissionModels = Permission::whereIn('name', $data['permissions'])
            ->where('guard_name', 'web')
            ->get();

        $role->syncPermissions($permissionModels);

        return redirect()
            ->route('superadmin.branches.roles.index', $branch->slug)
            ->with('success', "Permissions for \"{$role->name}\" updated.");
    }
}
