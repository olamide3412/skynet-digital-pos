<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GlobalItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GlobalItemController extends Controller
{
    public function index(Request $request)
    {
        $items = GlobalItem::when($request->search, fn($q) => $q
            ->where('item_name', 'like', '%' . $request->search . '%')
            ->orWhere('barcode_number', 'like', '%' . $request->search . '%')
            ->orWhere('category_hint', 'like', '%' . $request->search . '%')
        )->latest()->paginate(25)->withQueryString();

        $branches = Branch::where('is_active', true)->get(['id', 'name', 'slug']);

        return Inertia::render('SuperAdmin/GlobalItems/Index', [
            'items'    => $items,
            'branches' => $branches,
            'filters'  => $request->only('search'),
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/GlobalItems/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'item_name'        => 'required|string|max:255',
            'barcode_number'   => 'nullable|string|max:100|unique:global_items',
            'buy_price'        => 'required|numeric|min:0',
            'price'            => 'required|numeric|min:0',
            'wholesale_price'  => 'nullable|numeric|min:0',
            'unit_label'       => 'nullable|string|max:50',
            'pack_label'       => 'nullable|string|max:50',
            'carton_label'     => 'nullable|string|max:50',
            'units_per_pack'   => 'nullable|integer|min:1',
            'packs_per_carton' => 'nullable|integer|min:1',
            'item_description' => 'nullable|string|max:500',
            'category_hint'    => 'nullable|string|max:100',
        ]);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('global-items', 'public');
        }

        GlobalItem::create($data);
        return redirect()->route('superadmin.global-items.index')
            ->with('success', 'Item added to global pool.');
    }

    public function edit(GlobalItem $globalItem)
    {
        return Inertia::render('SuperAdmin/GlobalItems/Edit', [
            'item' => $globalItem,
        ]);
    }

    public function update(Request $request, GlobalItem $globalItem)
    {
        $data = $request->validate([
            'item_name'        => 'required|string|max:255',
            'barcode_number'   => 'nullable|string|max:100|unique:global_items,barcode_number,' . $globalItem->id,
            'buy_price'        => 'required|numeric|min:0',
            'price'            => 'required|numeric|min:0',
            'wholesale_price'  => 'nullable|numeric|min:0',
            'unit_label'       => 'nullable|string|max:50',
            'pack_label'       => 'nullable|string|max:50',
            'carton_label'     => 'nullable|string|max:50',
            'units_per_pack'   => 'nullable|integer|min:1',
            'packs_per_carton' => 'nullable|integer|min:1',
            'item_description' => 'nullable|string|max:500',
            'category_hint'    => 'nullable|string|max:100',
        ]);

        $globalItem->update($data);
        return back()->with('success', 'Global item updated.');
    }

    public function destroy(GlobalItem $globalItem)
    {
        $globalItem->delete();
        return redirect()->route('superadmin.global-items.index')
            ->with('success', 'Item removed from global pool.');
    }

    /**
     * Import a global item into a specific branch's catalog.
     * Creates a one-time copy — the branch owns it after import.
     */
    public function import(Request $request, GlobalItem $globalItem, Branch $branch)
    {
        $data = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
        ]);

        // Check if item already exists in branch catalog (by barcode OR item_name)
        $exists = \App\Models\Item::where('branch_id', $branch->id)
            ->where(function ($q) use ($globalItem) {
                if ($globalItem->barcode_number) {
                    $q->where('barcode_number', $globalItem->barcode_number);
                }
                $q->orWhere('item_name', $globalItem->item_name);
            })
            ->exists();

        if ($exists) {
            return back()->with('info', "'{$globalItem->item_name}' already exists in {$branch->name} catalog.");
        }

        $item = $globalItem->importToBranch($branch, $data['category_id'] ?? null);

        return back()->with('success', "'{$item->item_name}' imported into {$branch->name}.");
    }
}
