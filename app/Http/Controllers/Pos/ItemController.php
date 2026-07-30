<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $items = Item::with('category')
            ->when($request->search, fn ($q) => $q
                ->where('item_name', 'like', '%' . $request->search . '%')
                ->orWhere('barcode_number', 'like', '%' . $request->search . '%'))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Items/Index', [
            'items'   => $items,
            'filters' => $request->only('search', 'category_id'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Items/Create', [
            'categories'    => \App\Models\Category::orderBy('name')->get(['id', 'name']),
            'groupAddresses'=> \App\Models\GroupAddress::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(\App\Http\Requests\Pos\StoreItemRequest $request)
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }
        Item::create($data);
        return redirect()->route('pos.items.index')->with('success', 'Item created successfully.');
    }

    public function edit(Item $item)
    {
        return Inertia::render('Items/Edit', [
            'item'          => $item->load('category'),
            'categories'    => \App\Models\Category::orderBy('name')->get(['id', 'name']),
            'groupAddresses'=> \App\Models\GroupAddress::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(\App\Http\Requests\Pos\StoreItemRequest $request, Item $item)
    {
        $data = $request->validated();
        if ($request->hasFile('image')) {
            if ($item->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }
        $item->update($data);
        return redirect()->route('pos.items.index')->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        if ($item->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();
        return redirect()->route('pos.items.index')->with('success', 'Item deleted.');
    }

    /** API: real-time search for POS screen */
    public function search(Request $request)
    {
        $q            = $request->get('q', '');
        $purchaseType = $request->get('purchase_type', 'Consumer');

        $items = Item::with('category')
            ->where(fn ($query) => $query
                ->where('item_name', 'like', '%' . $q . '%')
                ->orWhere('barcode_number', 'like', $q . '%'))
            ->select('id', 'item_name', 'barcode_number', 'qty', 'price',
                     'wholesale_price', 'buy_price', 'expiry_date',
                     'price_locked', 'category_id', 'image_path')
            ->limit(20)
            ->get()
            ->map(fn ($item) => array_merge($item->toArray(), [
                'display_price' => $purchaseType === 'Wholesale'
                    ? $item->wholesale_price
                    : $item->price,
            ]));

        return response()->json($items);
    }
}
