<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $items = Item::where('branch_id', $branch->id)
            ->with('category')
            ->when($request->search, fn($q) => $q
                ->where('item_name', 'like', '%' . $request->search . '%')
                ->orWhere('barcode_number', 'like', '%' . $request->search . '%'))
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Total stock worth — use the pre-computed stored column (updated on every item save)
        $totalWorth = Item::where('branch_id', $branch->id)->sum('stock_worth');

        return Inertia::render('Items/Index', [
            'items'      => $items,
            'filters'    => $request->only('search', 'category_id'),
            'totalWorth' => (float) $totalWorth,
        ]);
    }

    public function create()
    {
        $branch = current_branch();
        return Inertia::render('Items/Create', [
            'categories'     => \App\Models\Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'groupAddresses' => \App\Models\GroupAddress::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'settings'       => \App\Models\PosSettings::current(),
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'item_name'              => 'required|string|max:255',
            'barcode_number'         => 'nullable|string|max:100',
            'category_id'            => 'nullable|exists:categories,id',
            'group_address_id'       => 'nullable|exists:group_addresses,id',
            'buy_price'              => 'required|numeric|min:0',
            'price'                  => 'required|numeric|min:0',
            'wholesale_price'        => 'nullable|numeric|min:0',
            'pack_price'             => 'nullable|numeric|min:0',
            'carton_price'           => 'nullable|numeric|min:0',
            'pack_wholesale_price'   => 'nullable|numeric|min:0',
            'carton_wholesale_price' => 'nullable|numeric|min:0',
            'price_locked'           => 'boolean',
            'back_store_qty'         => 'nullable|integer|min:0',
            'front_store_qty'        => 'nullable|integer|min:0',
            'unit_label'             => 'nullable|string|max:50',
            'pack_label'             => 'nullable|string|max:50',
            'carton_label'           => 'nullable|string|max:50',
            'units_per_pack'         => 'nullable|integer|min:1',
            'packs_per_carton'       => 'nullable|integer|min:1',
            'expiry_date'            => 'nullable|date',
            'item_description'       => 'nullable|string|max:500',
            'image'                  => 'nullable|image|max:2048',
        ]);

        $data['branch_id']        = $branch->id;
        $data['unit_label']       = $data['unit_label'] ?: 'Unit';
        $data['pack_label']       = $data['pack_label'] ?: 'Pack';
        $data['carton_label']     = $data['carton_label'] ?: 'Carton';
        $data['units_per_pack']   = $data['units_per_pack'] ?: 1;
        $data['packs_per_carton'] = $data['packs_per_carton'] ?: 1;

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($data['image']);

        Item::create($data);
        return redirect()->route('pos.items.index')
            ->with('success', 'Item created successfully.');
    }

    public function edit($branchParam, Item $item)
    {
        $branch = current_branch();
        $this->authorizeBranch($item, $branch);

        return Inertia::render('Items/Edit', [
            'item'           => $item->load('category'),
            'categories'     => \App\Models\Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'groupAddresses' => \App\Models\GroupAddress::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'settings'       => \App\Models\PosSettings::current(),
        ]);
    }

    public function update(Request $request, $branchParam, Item $item)
    {
        $branch = current_branch();
        $this->authorizeBranch($item, $branch);

        $data = $request->validate([
            'item_name'              => 'required|string|max:255',
            'barcode_number'         => 'nullable|string|max:100',
            'category_id'            => 'nullable|exists:categories,id',
            'group_address_id'       => 'nullable|exists:group_addresses,id',
            'buy_price'              => 'required|numeric|min:0',
            'price'                  => 'required|numeric|min:0',
            'wholesale_price'        => 'nullable|numeric|min:0',
            'pack_price'             => 'nullable|numeric|min:0',
            'carton_price'           => 'nullable|numeric|min:0',
            'pack_wholesale_price'   => 'nullable|numeric|min:0',
            'carton_wholesale_price' => 'nullable|numeric|min:0',
            'price_locked'           => 'boolean',
            'back_store_qty'         => 'nullable|integer|min:0',
            'front_store_qty'        => 'nullable|integer|min:0',
            'unit_label'             => 'nullable|string|max:50',
            'pack_label'             => 'nullable|string|max:50',
            'carton_label'           => 'nullable|string|max:50',
            'units_per_pack'         => 'nullable|integer|min:1',
            'packs_per_carton'       => 'nullable|integer|min:1',
            'expiry_date'            => 'nullable|date',
            'item_description'       => 'nullable|string|max:500',
            'image'                  => 'nullable|image|max:2048',
        ]);

        $data['unit_label']       = $data['unit_label'] ?: 'Unit';
        $data['pack_label']       = $data['pack_label'] ?: 'Pack';
        $data['carton_label']     = $data['carton_label'] ?: 'Carton';
        $data['units_per_pack']   = $data['units_per_pack'] ?: 1;
        $data['packs_per_carton'] = $data['packs_per_carton'] ?: 1;

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($data['image']);

        $item->update($data);
        return redirect()->route('pos.items.index')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy($branchParam, Item $item)
    {
        $branch = current_branch();
        $this->authorizeBranch($item, $branch);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();
        return redirect()->route('pos.items.index')
            ->with('success', 'Item deleted.');
    }

    /** API: real-time item search for POS screen */
    public function search(Request $request)
    {
        $branch       = current_branch();
        $q            = $request->get('q', '');
        $purchaseType = $request->get('purchase_type', 'Consumer');

        $items = Item::where('branch_id', $branch->id)
            ->where(fn($query) => $query
                ->where('item_name', 'like', '%' . $q . '%')
                ->orWhere('barcode_number', 'like', $q . '%'))
            ->select('id', 'item_name', 'barcode_number',
                     'back_store_qty', 'front_store_qty', 'price', 'wholesale_price',
                     'pack_price', 'carton_price', 'pack_wholesale_price', 'carton_wholesale_price',
                     'buy_price', 'expiry_date', 'price_locked', 'category_id', 'image_path',
                     'unit_label', 'pack_label', 'carton_label',
                     'units_per_pack', 'packs_per_carton')
            ->limit(20)
            ->get()
            ->map(fn($item) => array_merge($item->toArray(), [
                'display_price' => $item->getPriceForUnitLevel('unit', $purchaseType),
                'pack_display_price' => $item->getPriceForUnitLevel('pack', $purchaseType),
                'carton_display_price' => $item->getPriceForUnitLevel('carton', $purchaseType),
                'image_url' => $item->image_url,
            ]));

        return response()->json($items);
    }

    protected function authorizeBranch(Item $item, $branch): void
    {
        if ($item->branch_id !== $branch?->id) {
            abort(403, 'This item does not belong to your branch.');
        }
    }
}
