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
        $grids = ItemGrid::with('item.category')->orderBy('menu_index')->get();
        $items = Item::select('id', 'item_name', 'barcode_number')->orderBy('item_name')->get();

        return Inertia::render('Items/Grid', [
            'grids' => $grids,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_id'    => 'required|exists:items,id',
            'menu_name'  => 'nullable|string|max:100',
            'menu_index' => 'required|integer',
            'fore_color' => 'nullable|string|max:20',
            'back_color' => 'nullable|string|max:20',
        ]);

        ItemGrid::create($data);

        return back()->with('success', 'Grid item added successfully.');
    }

    public function update(Request $request, ItemGrid $item_grid)
    {
        $data = $request->validate([
            'item_id'    => 'required|exists:items,id',
            'menu_name'  => 'nullable|string|max:100',
            'menu_index' => 'required|integer',
            'fore_color' => 'nullable|string|max:20',
            'back_color' => 'nullable|string|max:20',
        ]);

        $item_grid->update($data);

        return back()->with('success', 'Grid item updated successfully.');
    }

    public function destroy(ItemGrid $item_grid)
    {
        $item_grid->delete();

        return back()->with('success', 'Grid item removed.');
    }
}
