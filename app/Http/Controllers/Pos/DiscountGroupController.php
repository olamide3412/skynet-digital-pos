<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\DiscountGroup;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DiscountGroupController extends Controller
{
    public function store(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after_or_equal:starts_at',
            'is_active'      => 'boolean',
            'item_ids'       => 'nullable|array',
            'item_ids.*'     => 'exists:items,id',
        ]);

        $data['user_id']   = Auth::id();
        $data['branch_id'] = $branch?->id;
        $data['is_active'] = $request->boolean('is_active', true);

        $itemIds = $data['item_ids'] ?? [];
        unset($data['item_ids']);

        DB::transaction(function () use ($data, $itemIds, $branch) {
            $group = DiscountGroup::create($data);

            if (!empty($itemIds)) {
                // If this group is active, remove these items from any other active groups in the branch to avoid overlapping active group memberships
                if ($group->is_active) {
                    $otherActiveGroupIds = DiscountGroup::where('is_active', true)
                        ->where('id', '!=', $group->id)
                        ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
                        ->pluck('id');

                    if ($otherActiveGroupIds->isNotEmpty()) {
                        DB::table('discount_group_items')
                            ->whereIn('discount_group_id', $otherActiveGroupIds)
                            ->whereIn('item_id', $itemIds)
                            ->delete();
                    }
                }

                $group->items()->sync($itemIds);
            }
        });

        return back()->with('success', 'Discount group created successfully.');
    }

    public function update(Request $request, $branchParam, DiscountGroup $discountGroup)
    {
        $branch = current_branch();

        $data = $request->validate([
            'name'           => 'required|string|max:150',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after_or_equal:starts_at',
            'is_active'      => 'boolean',
            'item_ids'       => 'nullable|array',
            'item_ids.*'     => 'exists:items,id',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $itemIds = $data['item_ids'] ?? [];
        unset($data['item_ids']);

        DB::transaction(function () use ($discountGroup, $data, $itemIds, $branch) {
            $discountGroup->update($data);

            if ($discountGroup->is_active && !empty($itemIds)) {
                $otherActiveGroupIds = DiscountGroup::where('is_active', true)
                    ->where('id', '!=', $discountGroup->id)
                    ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
                    ->pluck('id');

                if ($otherActiveGroupIds->isNotEmpty()) {
                    DB::table('discount_group_items')
                        ->whereIn('discount_group_id', $otherActiveGroupIds)
                        ->whereIn('item_id', $itemIds)
                        ->delete();
                }
            }

            $discountGroup->items()->sync($itemIds);
        });

        return back()->with('success', 'Discount group updated successfully.');
    }

    public function toggleActive($branchParam, DiscountGroup $discountGroup)
    {
        $newStatus = !$discountGroup->is_active;
        $discountGroup->update(['is_active' => $newStatus]);

        return back()->with('success', 'Discount group status updated.');
    }

    public function destroy($branchParam, DiscountGroup $discountGroup)
    {
        $discountGroup->items()->detach();
        $discountGroup->delete();

        return back()->with('success', 'Discount group deleted.');
    }
}
