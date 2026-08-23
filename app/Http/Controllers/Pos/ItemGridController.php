<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\ItemGrid;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemGridController extends Controller
{
    public function index()
    {
        $branch = current_branch();

        $grids = ItemGrid::whereHas('item', fn ($q) => $q->where('branch_id', $branch->id))
            ->with(['item' => function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)->with('category');
            }])
            ->orderBy('menu_index')
            ->get();

        $items = Item::where('branch_id', $branch->id)
            ->select('id', 'item_name', 'barcode_number')
            ->orderBy('item_name')
            ->limit(25)
            ->get();

        return Inertia::render('Items/Grid', [
            'grids' => $grids,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch() ?? ($request->route('branch') ? \App\Models\Branch::where('slug', $request->route('branch'))->first() : null);
        if (!$branch && auth()->check() && auth()->user()->branch_id) {
            $branch = \App\Models\Branch::find(auth()->user()->branch_id);
        }

        $data = $request->validate([
            'item_id'    => [
                'required',
                \Illuminate\Validation\Rule::exists('items', 'id')->where('branch_id', $branch->id),
            ],
            'menu_name'  => 'nullable|string|max:100',
            'menu_index' => 'required|integer',
            'fore_color' => 'nullable|string|max:20',
            'back_color' => 'nullable|string|max:20',
        ]);

        ItemGrid::create($data);

        return back()->with('success', 'Grid item added successfully.');
    }

    public function update(Request $request, $branchParam, ItemGrid $item_grid)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if (!$branch && auth()->check() && auth()->user()->branch_id) {
            $branch = \App\Models\Branch::find(auth()->user()->branch_id);
        }

        if ($item_grid->item?->branch_id !== $branch->id) {
            abort(403, 'This grid item does not belong to your branch.');
        }

        $data = $request->validate([
            'item_id'    => [
                'required',
                \Illuminate\Validation\Rule::exists('items', 'id')->where('branch_id', $branch->id),
            ],
            'menu_name'  => 'nullable|string|max:100',
            'menu_index' => 'required|integer',
            'fore_color' => 'nullable|string|max:20',
            'back_color' => 'nullable|string|max:20',
        ]);

        $item_grid->update($data);

        return back()->with('success', 'Grid item updated successfully.');
    }

    public function destroy($branchParam, ItemGrid $item_grid)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if (!$branch && auth()->check() && auth()->user()->branch_id) {
            $branch = \App\Models\Branch::find(auth()->user()->branch_id);
        }

        if ($item_grid->item?->branch_id !== $branch->id) {
            abort(403, 'This grid item does not belong to your branch.');
        }

        $item_grid->delete();

        return back()->with('success', 'Grid item removed.');
    }
}
