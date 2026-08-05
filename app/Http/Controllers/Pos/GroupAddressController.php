<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\GroupAddress;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GroupAddressController extends Controller
{
    public function index()
    {
        $branch = current_branch();

        $groupAddresses = GroupAddress::withCount('items')
            ->when($branch, function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)
                      ->orWhereNull('branch_id');
            })
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('GroupAddresses/Index', [
            'groupAddresses' => $groupAddresses,
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        GroupAddress::create([
            'branch_id' => $branch?->id,
            'name'      => trim($request->name),
        ]);

        return back()->with('success', 'Group/Address location created successfully.');
    }

    public function update(Request $request, $branchParam, GroupAddress $groupAddress)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $groupAddress->update([
            'name' => trim($request->name),
        ]);

        return back()->with('success', 'Group/Address location updated successfully.');
    }

    public function destroy($branchParam, GroupAddress $groupAddress)
    {
        $groupAddress->delete();
        return back()->with('success', 'Group/Address location deleted successfully.');
    }
}
