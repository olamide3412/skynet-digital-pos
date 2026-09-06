<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemDiscount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemDiscountController extends Controller
{
    public function store(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'item_id'        => 'required|exists:items,id',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after_or_equal:starts_at',
            'is_active'      => 'boolean',
        ]);

        $data['user_id']   = Auth::id();
        $data['branch_id'] = $branch?->id;
        $data['is_active'] = $request->boolean('is_active', true);

        // If newly created discount is active, ensure no conflicting active item discount remains active
        if ($data['is_active']) {
            ItemDiscount::where('item_id', $data['item_id'])
                ->where('is_active', true)
                ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
                ->update(['is_active' => false]);
        }

        ItemDiscount::create($data);

        return back()->with('success', 'Item discount created successfully.');
    }

    public function update(Request $request, $branchParam, ItemDiscount $itemDiscount)
    {
        $branch = current_branch();

        $data = $request->validate([
            'item_id'        => 'required|exists:items,id',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after_or_equal:starts_at',
            'is_active'      => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['is_active']) {
            ItemDiscount::where('item_id', $data['item_id'])
                ->where('id', '!=', $itemDiscount->id)
                ->where('is_active', true)
                ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
                ->update(['is_active' => false]);
        }

        $itemDiscount->update($data);

        return back()->with('success', 'Item discount updated successfully.');
    }

    public function toggleActive($branchParam, ItemDiscount $itemDiscount)
    {
        $branch = current_branch();
        $newStatus = !$itemDiscount->is_active;

        if ($newStatus) {
            ItemDiscount::where('item_id', $itemDiscount->item_id)
                ->where('id', '!=', $itemDiscount->id)
                ->where('is_active', true)
                ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
                ->update(['is_active' => false]);
        }

        $itemDiscount->update(['is_active' => $newStatus]);

        return back()->with('success', 'Item discount status updated.');
    }

    public function destroy($branchParam, ItemDiscount $itemDiscount)
    {
        $itemDiscount->delete();

        return back()->with('success', 'Item discount deleted.');
    }
}
