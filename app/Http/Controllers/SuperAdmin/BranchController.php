<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['users', 'items', 'sales'])->latest()->get();
        return Inertia::render('SuperAdmin/Branches/Index', ['branches' => $branches]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Branches/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'slug'    => 'required|string|max:100|unique:branches|regex:/^[a-z0-9\-]+$/',
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:50',
            'email'   => 'nullable|email|max:255',
            // Branch Admin account details
            'admin_name'     => 'required|string|max:255',
            'admin_username' => 'required|string|max:50|unique:users,username',
            'admin_email'    => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        DB::transaction(function () use ($data) {
            // Create branch
            $branch = Branch::create([
                'name'      => $data['name'],
                'slug'      => $data['slug'],
                'address'   => $data['address'],
                'phone'     => $data['phone'],
                'email'     => $data['email'],
                'is_active' => true,
            ]);

            // Auto-create default Branch Admin user
            $admin = User::create([
                'name'           => $data['admin_name'],
                'full_name'      => $data['admin_name'],
                'username'       => $data['admin_username'],
                'email'          => $data['admin_email'],
                'password'       => Hash::make($data['admin_password']),
                'is_active'      => true,
                'is_super_admin' => false,
                'branch_id'      => $branch->id,
            ]);

            // Set Spatie team context, then assign branch-admin role
            setPermissionsTeamId($branch->id);
            $admin->assignRole('branch-admin');

            // Create branch POS settings with defaults
            $branch->getSettings();
        });

        return redirect()->route('superadmin.branches.index')
            ->with('success', 'Branch created with default admin account.');
    }

    public function edit(Branch $branch)
    {
        return Inertia::render('SuperAdmin/Branches/Edit', [
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'slug'    => 'required|string|max:100|unique:branches,slug,' . $branch->id . '|regex:/^[a-z0-9\-]+$/',
            'address' => 'nullable|string|max:500',
            'phone'   => 'nullable|string|max:50',
            'email'   => 'nullable|email|max:255',
        ]);

        $branch->update($data);

        return back()->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch)
    {
        // Safety check: don't delete a branch with live sales
        if ($branch->sales()->exists()) {
            return back()->withErrors(['branch' => 'Cannot delete a branch with existing sales records.']);
        }

        $branch->delete();
        return redirect()->route('superadmin.branches.index')
            ->with('success', 'Branch deleted.');
    }

    /**
     * Toggle branch active/inactive status.
     */
    public function toggle(Branch $branch)
    {
        $branch->update(['is_active' => !$branch->is_active]);
        $status = $branch->is_active ? 'enabled' : 'disabled';
        return back()->with('success', "Branch {$status} successfully.");
    }
}
