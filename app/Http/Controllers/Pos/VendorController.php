<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $vendors = Vendor::where(fn($q) => $q->where('branch_id', $branch?->id)->orWhereNull('branch_id'))
            ->withCount('purchaseOrders')
            ->when($request->search, fn ($q) => $q
                ->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('company_name', 'like', '%' . $request->search . '%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'company_name' => 'nullable|string|max:100',
            'phone'        => 'required|string|max:20',
            'email'        => 'nullable|email|max:100',
            'address'      => 'nullable|string|max:255',
            'status'       => 'required|in:Active,Inactive',
        ]);

        $data['branch_id'] = current_branch()?->id;

        Vendor::create($data);

        return back()->with('success', 'Vendor added successfully.');
    }

    public function update(Request $request, $branchParam, Vendor $vendor)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'company_name' => 'nullable|string|max:100',
            'phone'        => 'required|string|max:20',
            'email'        => 'nullable|email|max:100',
            'address'      => 'nullable|string|max:255',
            'status'       => 'required|in:Active,Inactive',
        ]);

        $vendor->update($data);

        return back()->with('success', 'Vendor updated successfully.');
    }

    public function destroy($branchParam, Vendor $vendor)
    {
        if ($vendor->purchaseOrders()->exists()) {
            return back()->withErrors(['vendor' => 'Cannot delete a vendor with existing purchase orders.']);
        }
        $vendor->delete();

        return back()->with('success', 'Vendor removed.');
    }
}
