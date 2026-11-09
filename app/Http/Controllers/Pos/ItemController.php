<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }

        setPermissionsTeamId($branch->id);
        $branch->ensureDefaultRoles();

        $itemsQuery = Item::where('branch_id', $branch->id)
            ->with(['category:id,name', 'brand:id,name', 'groupAddress:id,name', 'units', 'ecommerceLinks:id,pos_item_id,is_active'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sq) use ($request) {
                    $sq->where('item_name', 'like', '%' . $request->search . '%')
                        ->orWhere('barcode_number', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->brand_id, fn($q) => $q->where('brand_id', $request->brand_id))
            ->when($request->group_address_id, fn($q) => $q->where('group_address_id', $request->group_address_id))
            ->latest();

        $perPageParam = $request->input('per_page', 25);
        if ($perPageParam === 'all' || $perPageParam === 'All') {
            $count = (clone $itemsQuery)->count();
            $perPage = max($count, 25);
        } elseif (in_array((int) $perPageParam, [25, 50, 100], true)) {
            $perPage = (int) $perPageParam;
        } else {
            $perPage = 25;
        }

        $items = $itemsQuery
            ->paginate($perPage)
            ->withQueryString();

        // Total stock worth — use the pre-computed stored column (updated on every item save)
        $totalWorth = Item::where('branch_id', $branch->id)->sum('stock_worth');

        $categories = \App\Models\Category::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $brands = \App\Models\Brand::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $groupAddresses = \App\Models\GroupAddress::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Items/Index', [
            'items' => $items,
            'categories' => $categories,
            'brands' => $brands,
            'groupAddresses' => $groupAddresses,
            'filters' => $request->only('search', 'category_id', 'brand_id', 'group_address_id', 'per_page'),
            'totalWorth' => (float) $totalWorth,
        ]);
    }

    public function create(Request $request)
    {
        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }

        $clonedItem = null;
        if ($request->has('clone_from')) {
            $cloned = Item::where('branch_id', $branch->id)->with('units')->find($request->clone_from);
            if ($cloned) {
                $clonedItem = [
                    'item_name' => $cloned->item_name . ' (Copy)',
                    'category_id' => $cloned->category_id,
                    'brand_id' => $cloned->brand_id,
                    'group_address_id' => $cloned->group_address_id,
                    'buy_price' => $cloned->buy_price,
                    'price' => $cloned->price,
                    'wholesale_price' => $cloned->wholesale_price,
                    'unit_label' => $cloned->unit_label,
                    'item_description' => $cloned->item_description,
                    'is_imei_tracked' => $cloned->is_imei_tracked,
                    'price_locked' => $cloned->price_locked,
                    'hide_from_public_catalog' => (bool) $cloned->hide_from_public_catalog,
                    'expiry_date' => $cloned->expiry_date?->format('Y-m-d'),
                    'units' => $cloned->units->map(fn($u) => [
                        'unit_name' => $u->unit_name,
                        'quantity_in_base_units' => (int) $u->quantity_in_base_units,
                        'selling_price' => (float) $u->selling_price,
                        'wholesale_price' => (float) $u->wholesale_price,
                        'sort_order' => (int) $u->sort_order,
                        'status' => $u->status,
                    ])->values()->all(),
                ];
            }
        }

        return Inertia::render('Items/Create', [
            'clonedItem' => $clonedItem,
            'categories' => \App\Models\Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'brands' => \App\Models\Brand::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'groupAddresses' => \App\Models\GroupAddress::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'settings' => \App\Models\PosSettings::current(),
        ]);
    }

    public function store(Request $request)
    {
        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'barcode_number' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'group_address_id' => 'nullable|exists:group_addresses,id',
            'buy_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_locked' => 'boolean',
            'hide_from_public_catalog' => 'nullable|boolean',
            'is_imei_tracked' => 'nullable|boolean',
            'batch_tracking_enabled' => 'nullable|boolean',
            'initial_imeis' => 'nullable|string',
            'back_store_qty' => 'nullable|integer|min:0',
            'front_store_qty' => 'nullable|integer|min:0',
            'unit_label' => 'nullable|string|max:50',
            'expiry_date' => 'nullable|date',
            'item_description' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
            'units' => 'nullable|array',
            'units.*.unit_name' => 'required_with:units|string|max:100',
            'units.*.quantity_in_base_units' => 'required_with:units|integer|min:1',
            'units.*.selling_price' => 'required_with:units|numeric|min:0',
            'units.*.wholesale_price' => 'nullable|numeric|min:0',
            'units.*.sort_order' => 'nullable|integer',
            'units.*.status' => 'nullable|string|in:active,inactive',
        ]);

        // Barcode handling: leave null if blank or 'NO_BARCODE' (do not auto-generate)
        $barcode = trim($data['barcode_number'] ?? '');
        if (!empty($barcode) && strtoupper($barcode) !== 'NO_BARCODE') {
            // Check barcode uniqueness in active branch
            $exists = Item::where('branch_id', $branch->id)
                ->where('barcode_number', $barcode)
                ->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'barcode_number' => 'This barcode number is already assigned to another item in your branch.',
                ]);
            }
            $data['barcode_number'] = $barcode;
        } else {
            $data['barcode_number'] = null;
        }

        $units = $data['units'] ?? [];
        unset($data['units']);

        $data['branch_id'] = $branch->id;
        $data['wholesale_price'] = (!empty($data['wholesale_price']) && $data['wholesale_price'] > 0) ? (float) $data['wholesale_price'] : (float) $data['price'];
        $data['price_locked'] = !empty($data['price_locked']);
        $data['hide_from_public_catalog'] = !empty($data['hide_from_public_catalog']);
        $data['is_imei_tracked'] = !empty($data['is_imei_tracked']);
        $data['batch_tracking_enabled'] = !empty($data['batch_tracking_enabled']);
        $data['front_store_qty'] = $data['front_store_qty'] ?? 0;
        $data['back_store_qty'] = $data['back_store_qty'] ?? 0;
        $data['unit_label'] = !empty($data['unit_label']) ? $data['unit_label'] : 'Unit';

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }
        $initialImeisRaw = $data['initial_imeis'] ?? '';
        unset($data['image'], $data['initial_imeis']);

        $newItem = Item::create($data);

        // Save custom item units / variants
        if (!empty($units)) {
            foreach ($units as $idx => $u) {
                if (empty($u['unit_name']))
                    continue;
                ItemUnit::create([
                    'item_id' => $newItem->id,
                    'branch_id' => $branch->id,
                    'unit_name' => trim($u['unit_name']),
                    'quantity_in_base_units' => max(1, (int) ($u['quantity_in_base_units'] ?? 1)),
                    'selling_price' => (float) ($u['selling_price'] ?? 0),
                    'wholesale_price' => (float) ($u['wholesale_price'] ?? 0),
                    'sort_order' => (int) ($u['sort_order'] ?? $idx),
                    'status' => $u['status'] ?? 'active',
                ]);
            }
        }

        // If IMEI tracked and initial IMEIs were entered, create device units
        if ($newItem->is_imei_tracked && !empty($initialImeisRaw)) {
            $lines = preg_split('/[\r\n,]+/', $initialImeisRaw, -1, PREG_SPLIT_NO_EMPTY);
            $enteredImeis = array_values(array_unique(array_filter(array_map('trim', $lines))));

            $createdCount = 0;
            foreach ($enteredImeis as $imei) {
                if (empty($imei))
                    continue;
                \App\Models\ItemDeviceUnit::create([
                    'branch_id' => $branch->id,
                    'item_id' => $newItem->id,
                    'imei_or_device_id' => $imei,
                    'status' => 'in_stock',
                    'location' => 'front_store',
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                ]);
                $createdCount++;
            }
            if ($createdCount > 0) {
                $newItem->update(['front_store_qty' => $createdCount]);
            }
        }

        \App\Services\ActivityLogger::item("Created item '{$newItem->item_name}' (Barcode: {$newItem->barcode_number})", $branch->id);
        return redirect()->route('pos.items.index', ['branch' => $branch->slug])
            ->with('success', 'Item created successfully.');
    }

    public function edit($branchParam, Item $item)
    {
        $branch = $this->resolveActiveBranch(request(), $branchParam);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }
        $this->authorizeBranch($item, $branch);

        $totalBatchesCount = $item->batches()->count();
        $activeBatchesTotal = (int) $item->batches()->where('status', 'active')->sum('quantity');
        $actualStock = ((int) ($item->front_store_qty ?? 0)) + ((int) ($item->back_store_qty ?? 0));
        $batchVariance = $actualStock - $activeBatchesTotal;

        $item->load([
            'category',
            'brand',
            'units',
            'batches' => fn($q) => $q->orderBy('expiry_date', 'asc')->take(5),
        ]);

        return Inertia::render('Items/Edit', [
            'item' => array_merge($item->toArray(), [
                'active_batches_total' => $activeBatchesTotal,
                'batch_variance' => $batchVariance,
                'total_batches_count' => $totalBatchesCount,
            ]),
            'categories' => \App\Models\Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'brands' => \App\Models\Brand::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'groupAddresses' => \App\Models\GroupAddress::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'settings' => \App\Models\PosSettings::current(),
        ]);
    }

    public function update(Request $request, $branchParam, Item $item)
    {
        $branch = $this->resolveActiveBranch($request, $branchParam);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }
        $this->authorizeBranch($item, $branch);

        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'barcode_number' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'group_address_id' => 'nullable|exists:group_addresses,id',
            'buy_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_locked' => 'nullable|boolean',
            'hide_from_public_catalog' => 'nullable|boolean',
            'is_imei_tracked' => 'nullable|boolean',
            'batch_tracking_enabled' => 'nullable|boolean',
            'unit_label' => 'nullable|string|max:50',
            'front_store_qty' => 'nullable|integer|min:0',
            'back_store_qty' => 'nullable|integer|min:0',
            'expiry_date' => 'nullable|date',
            'item_description' => 'nullable|string|max:500',
            'image' => 'nullable|image|max:2048',
            'units' => 'nullable|array',
            'units.*.id' => 'nullable|integer',
            'units.*.unit_name' => 'required_with:units|string|max:100',
            'units.*.quantity_in_base_units' => 'required_with:units|integer|min:1',
            'units.*.selling_price' => 'required_with:units|numeric|min:0',
            'units.*.wholesale_price' => 'nullable|numeric|min:0',
            'units.*.sort_order' => 'nullable|integer',
            'units.*.status' => 'nullable|string|in:active,inactive',
        ]);

        $barcode = trim($data['barcode_number'] ?? '');
        if (!empty($barcode) && strtoupper($barcode) !== 'NO_BARCODE') {
            $existing = Item::where('branch_id', $branch->id)
                ->where('barcode_number', $barcode)
                ->where('id', '!=', $item->id)
                ->first();
            if ($existing) {
                return back()->withErrors(['barcode_number' => 'Barcode number already in use by another item.']);
            }
            $data['barcode_number'] = $barcode;
        } else {
            $data['barcode_number'] = null;
        }

        $units = $data['units'] ?? null;
        unset($data['units']);

        $data['wholesale_price'] = (!empty($data['wholesale_price']) && $data['wholesale_price'] > 0) ? (float) $data['wholesale_price'] : (float) $data['price'];
        $data['price_locked'] = !empty($data['price_locked']);
        $data['hide_from_public_catalog'] = !empty($data['hide_from_public_catalog']);
        $data['is_imei_tracked'] = !empty($data['is_imei_tracked']);
        $data['batch_tracking_enabled'] = !empty($data['batch_tracking_enabled']);
        $data['unit_label'] = !empty($data['unit_label']) ? $data['unit_label'] : 'Unit';

        if ($request->hasFile('image')) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = $request->file('image')->store('items', 'public');
        }
        unset($data['image']);

        $item->update($data);

        // Sync custom item units / variants if provided
        if (is_array($units)) {
            $retainedUnitIds = [];
            foreach ($units as $idx => $u) {
                if (empty($u['unit_name']))
                    continue;
                $unitId = !empty($u['id']) ? (int) $u['id'] : null;
                $unitData = [
                    'item_id' => $item->id,
                    'branch_id' => $branch->id,
                    'unit_name' => trim($u['unit_name']),
                    'quantity_in_base_units' => max(1, (int) ($u['quantity_in_base_units'] ?? 1)),
                    'selling_price' => (float) ($u['selling_price'] ?? 0),
                    'wholesale_price' => (float) ($u['wholesale_price'] ?? 0),
                    'sort_order' => (int) ($u['sort_order'] ?? $idx),
                    'status' => $u['status'] ?? 'active',
                ];

                if ($unitId) {
                    $existingUnit = ItemUnit::where('item_id', $item->id)->where('id', $unitId)->first();
                    if ($existingUnit) {
                        $existingUnit->update($unitData);
                        $retainedUnitIds[] = $existingUnit->id;
                        continue;
                    }
                }

                $created = ItemUnit::create($unitData);
                $retainedUnitIds[] = $created->id;
            }

            // Delete removed units
            ItemUnit::where('item_id', $item->id)->whereNotIn('id', $retainedUnitIds)->delete();
        }

        \App\Services\ActivityLogger::item("Updated details for item '{$item->item_name}'", $branch->id);
        return redirect()->route('pos.items.index', ['branch' => $branch->slug])
            ->with('success', 'Item updated successfully.');
    }

    public function destroy($branchParam, Item $item)
    {
        $branch = $this->resolveActiveBranch(request(), $branchParam);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }
        $this->authorizeBranch($item, $branch);

        // Prevent accidental deletion if mapped to active online product
        if ($item->isLinkedToEcommerce()) {
            $msg = "Cannot delete item '{$item->name}'. It is currently linked to an active product in your online Ecommerce store. Please unlink it from Ecommerce first.";
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'status'  => false,
                    'message' => $msg,
                ], 422);
            }
            return back()->with('error', $msg);
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $itemName = $item->item_name;
        try {
            $item->delete();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        \App\Services\ActivityLogger::item("Deleted item '{$itemName}'", $branch->id);
        return redirect()->route('pos.items.index', ['branch' => $branch->slug])
            ->with('success', 'Item deleted.');
    }

    /** Duplicate an item directly into the branch catalog */
    public function duplicate(Request $request, $branchParam = null, $itemParam = null)
    {
        $branch = $this->resolveActiveBranch($request, $branchParam);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }

        $item = $itemParam instanceof Item ? $itemParam : Item::with('units')->findOrFail($itemParam ?: $request->route('item'));
        $this->authorizeBranch($item, $branch);

        // Find an incremental unique name
        $baseName = $item->item_name;
        $copyName = $baseName . ' (Copy)';
        $counter = 2;
        while (Item::where('branch_id', $branch->id)->where('item_name', $copyName)->exists()) {
            $copyName = "{$baseName} (Copy {$counter})";
            $counter++;
        }

        $newItem = $item->replicate([
            'barcode_number',
            'stock_worth',
            'created_at',
            'updated_at',
        ]);

        $newItem->branch_id = $branch->id;
        $newItem->item_name = $copyName;
        $newItem->barcode_number = null; // Blank barcode to prevent collision
        $newItem->front_store_qty = 0;
        $newItem->back_store_qty = 0;
        $newItem->save();

        // Duplicate associated item_units
        foreach ($item->units as $u) {
            $newUnit = $u->replicate(['created_at', 'updated_at']);
            $newUnit->item_id = $newItem->id;
            $newUnit->branch_id = $branch->id;
            $newUnit->save();
        }

        \App\Services\ActivityLogger::item("Duplicated item '{$item->item_name}' as '{$newItem->item_name}'", $branch->id);

        return redirect()->route('pos.items.index', ['branch' => $branch->slug])
            ->with('success', "Item '{$item->item_name}' duplicated as '{$newItem->item_name}'.");
    }

    /** Execute batch operations on selected items */
    public function bulkAction(Request $request, $branchParam = null)
    {
        $branch = $this->resolveActiveBranch($request, $branchParam);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }

        $data = $request->validate([
            'action' => 'required|string|in:delete,update_category,update_brand,update_group,adjust_prices',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:items,id',
            'category_id' => 'nullable|required_if:action,update_category|exists:categories,id',
            'brand_id' => 'nullable|required_if:action,update_brand|exists:brands,id',
            'group_address_id' => 'nullable|required_if:action,update_group|exists:group_addresses,id',
            'price_change_type' => 'nullable|in:increase_percent,decrease_percent,set_fixed',
            'price_change_value' => 'nullable|numeric|min:0',
        ]);

        $items = Item::where('branch_id', $branch->id)
            ->whereIn('id', $data['item_ids'])
            ->get();

        if ($items->isEmpty()) {
            return back()->with('error', 'No matching items found.');
        }

        $count = $items->count();

        switch ($data['action']) {
            case 'delete':
                $blockedItems = [];
                $deletedCount = 0;
                foreach ($items as $item) {
                    if ($item->isLinkedToEcommerce()) {
                        $blockedItems[] = $item->name;
                        continue;
                    }
                    if ($item->image_path) {
                        Storage::disk('public')->delete($item->image_path);
                    }
                    $item->delete();
                    $deletedCount++;
                }

                if (!empty($blockedItems)) {
                    $blockedCount = count($blockedItems);
                    if ($deletedCount > 0) {
                        return back()->with('warning', "Deleted {$deletedCount} item(s). {$blockedCount} item(s) were protected from deletion because they are linked to active products in your online Ecommerce store.");
                    }
                    return back()->with('error', "Cannot delete the selected item(s). They are currently linked to active products in your online Ecommerce store. Please unlink them from Ecommerce first.");
                }

                \App\Services\ActivityLogger::item("Bulk deleted {$count} items", $branch->id);
                return back()->with('success', "Successfully deleted {$count} item(s).");

            case 'update_category':
                Item::where('branch_id', $branch->id)
                    ->whereIn('id', $data['item_ids'])
                    ->update(['category_id' => $data['category_id']]);
                \App\Services\ActivityLogger::item("Bulk moved {$count} items to category ID {$data['category_id']}", $branch->id);
                return back()->with('success', "Successfully updated category for {$count} item(s).");

            case 'update_brand':
                Item::where('branch_id', $branch->id)
                    ->whereIn('id', $data['item_ids'])
                    ->update(['brand_id' => $data['brand_id']]);
                \App\Services\ActivityLogger::item("Bulk moved {$count} items to brand ID {$data['brand_id']}", $branch->id);
                return back()->with('success', "Successfully updated brand for {$count} item(s).");

            case 'update_group':
                Item::where('branch_id', $branch->id)
                    ->whereIn('id', $data['item_ids'])
                    ->update(['group_address_id' => $data['group_address_id']]);
                \App\Services\ActivityLogger::item("Bulk moved {$count} items to shelf location ID {$data['group_address_id']}", $branch->id);
                return back()->with('success', "Successfully updated shelf location for {$count} item(s).");

            case 'adjust_prices':
                $val = (float) ($data['price_change_value'] ?? 0);
                $type = $data['price_change_type'] ?? 'increase_percent';

                foreach ($items as $item) {
                    $currentPrice = (float) $item->price;
                    if ($type === 'increase_percent') {
                        $newPrice = round($currentPrice * (1 + ($val / 100)), 2);
                    } elseif ($type === 'decrease_percent') {
                        $newPrice = max(0, round($currentPrice * (1 - ($val / 100)), 2));
                    } elseif ($type === 'set_fixed') {
                        $newPrice = max(0, round($val, 2));
                    } else {
                        $newPrice = $currentPrice;
                    }
                    $item->update(['price' => $newPrice]);
                }
                \App\Services\ActivityLogger::item("Bulk updated prices for {$count} items", $branch->id);
                return back()->with('success', "Successfully adjusted prices for {$count} item(s).");
        }

        return back();
    }

    /** API: real-time item search for POS screen */
    public function search(Request $request)
    {
        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return response()->json([]);
        }

        $q = $request->get('q', '');
        $purchaseType = $request->get('purchase_type', 'Consumer');

        $items = Item::where('branch_id', $branch->id)
            ->where(fn($query) => $query
                ->where('item_name', 'like', '%' . $q . '%')
                ->orWhere('barcode_number', 'like', $q . '%'))
            ->select(
                'id',
                'item_name',
                'barcode_number',
                'back_store_qty',
                'front_store_qty',
                'price',
                'wholesale_price',
                'buy_price',
                'expiry_date',
                'price_locked',
                'is_imei_tracked',
                'batch_tracking_enabled',
                'category_id',
                'image_path',
                'unit_label'
            )
            ->with([
                'activeUnits',
                'inStockDeviceUnits' => function ($query) use ($branch) {
                    $query->where('branch_id', $branch->id)->select('id', 'item_id', 'imei_or_device_id', 'location', 'status');
                }
            ])
            ->limit(25)
            ->get()
            ->map(fn($item) => array_merge($item->toArray(), [
                'qty' => $item->front_store_qty,
                'total_qty' => $item->total_qty,
                'display_price' => $item->getPriceForUnitLevel('unit', $purchaseType),
                'units' => $item->activeUnits->map(fn($u) => [
                    'id' => $u->id,
                    'unit_name' => $u->unit_name,
                    'quantity_in_base_units' => (int) $u->quantity_in_base_units,
                    'selling_price' => (float) $u->selling_price,
                    'wholesale_price' => (float) $u->wholesale_price,
                    'display_price' => $item->getPriceForUnitLevel($u->unit_name, $purchaseType, $u->id),
                ])->values()->all(),
                'image_url' => $item->image_url,
                'available_imeis' => $item->is_imei_tracked
                    ? $item->inStockDeviceUnits->pluck('imei_or_device_id')->values()->all()
                    : [],
            ]));

        return response()->json($items);
    }

    /** API: Get available in-stock IMEIs for an item */
    public function availableImeis(Request $request, $branchParam = null, $item = null)
    {
        $branch = $this->resolveActiveBranch($request, $branchParam);
        $branchId = $branch?->id;
        $itemId = $item instanceof Item ? $item->id : ($request->get('item_id') ?? $item);

        if (!$itemId) {
            return response()->json(['imeis' => [], 'count' => 0]);
        }

        $targetItem = $item instanceof Item
            ? $item
            : ($branchId ? Item::where('branch_id', $branchId)->find($itemId) : Item::find($itemId));

        if (!$targetItem) {
            return response()->json(['imeis' => [], 'count' => 0]);
        }

        $effectiveBranchId = $branchId ?? $targetItem->branch_id;

        $imeis = \App\Models\ItemDeviceUnit::where('branch_id', $effectiveBranchId)
            ->where('item_id', $targetItem->id)
            ->where('status', 'in_stock')
            ->orderBy('imei_or_device_id')
            ->get(['id', 'imei_or_device_id', 'location', 'status']);

        return response()->json([
            'item_id' => $targetItem->id,
            'item_name' => $targetItem->item_name,
            'is_imei_tracked' => (bool) $targetItem->is_imei_tracked,
            'count' => $imeis->count(),
            'imeis' => $imeis,
        ]);
    }

    /** Export sample CSV template matching multi-row variant items schema */
    public function exportTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=items_import_template.csv',
        ];

        $columns = [
            'item_ref',
            'item_name',
            'category_name',
            'brand_name',
            'group_address_name',
            'barcode_number',
            'cost_price',
            'unit_name',
            'quantity_in_base_units',
            'selling_price',
            'wholesale_price',
            'is_base_unit',
            'front_store_qty',
            'back_store_qty',
            'expiry_date',
            'item_description'
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // Group 1: Peak Milk Sachet with Roll & Carton variants
            fputcsv($file, [
                'P001',
                'Peak Milk Sachet',
                'Dairy & Provisions',
                'Peak',
                'Shelf A1',
                '8901234567890',
                '200.00',
                'Unit',
                '1',
                '250.00',
                '220.00',
                'Yes',
                '100',
                '500',
                '2028-12-31',
                'Instant full cream milk powder sachet'
            ]);
            fputcsv($file, [
                'P001',
                'Peak Milk Sachet',
                'Dairy & Provisions',
                'Peak',
                'Shelf A1',
                '8901234567890',
                '',
                'Roll (10-in-1)',
                '10',
                '2350.00',
                '2100.00',
                'No',
                '',
                '',
                '',
                ''
            ]);
            fputcsv($file, [
                'P001',
                'Peak Milk Sachet',
                'Dairy & Provisions',
                'Peak',
                'Shelf A1',
                '8901234567890',
                '',
                'Carton (100-in-1)',
                '100',
                '22500.00',
                '20000.00',
                'No',
                '',
                '',
                '',
                ''
            ]);

            // Group 2: Coca-Cola 50cl with Pack variant
            fputcsv($file, [
                'P002',
                'Coca-Cola 50cl',
                'Beverages',
                'Coca-Cola',
                'Cooler 1',
                '5449000000996',
                '150.00',
                'Bottle',
                '1',
                '250.00',
                '220.00',
                'Yes',
                '48',
                '240',
                '2027-06-30',
                'Carbonated soft drink bottle'
            ]);
            fputcsv($file, [
                'P002',
                'Coca-Cola 50cl',
                'Beverages',
                'Coca-Cola',
                'Cooler 1',
                '5449000000996',
                '',
                'Pack (12-in-1)',
                '12',
                '2800.00',
                '2500.00',
                'No',
                '',
                '',
                '',
                ''
            ]);

            // Group 3: Paracetamol 500mg (Base unit only)
            fputcsv($file, [
                'P003',
                'Paracetamol 500mg',
                'Analgesics',
                'Shelf A1',
                '6001234987654',
                '150.00',
                'Tablet',
                '1',
                '200.00',
                '180.00',
                'Yes',
                '50',
                '200',
                '2027-12-31',
                'Pain relief tablets'
            ]);

            // Group 4: Golden Penny Semovita 2kg (Base unit only, no barcode)
            fputcsv($file, [
                'P004',
                'Golden Penny Semovita 2kg',
                'Groceries',
                'Aisle 3',
                '',
                '1400.00',
                'Bag',
                '1',
                '1800.00',
                '1650.00',
                'Yes',
                '25',
                '100',
                '2028-06-30',
                'Premium wheat semovita flour'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** Export all current branch items into CSV matching multi-row variant schema */
    public function exportCsv(Request $request)
    {
        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }

        $branchName = $branch->name;
        $fileName = 'skynet_items_' . \Illuminate\Support\Str::slug($branchName) . '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        $selectedIds = $request->query('ids') ? explode(',', $request->query('ids')) : null;

        $items = Item::where('branch_id', $branch->id)
            ->when($selectedIds, fn($q) => $q->whereIn('id', $selectedIds))
            ->with(['category', 'brand', 'groupAddress', 'units'])
            ->latest()
            ->get();

        $columns = [
            'item_ref',
            'item_name',
            'barcode_number',
            'category_name',
            'brand_name',
            'group_address_name',
            'cost_price',
            'unit_name',
            'quantity_in_base_units',
            'selling_price',
            'wholesale_price',
            'is_base_unit',
            'front_store_qty',
            'back_store_qty',
            'expiry_date',
            'item_description'
        ];

        $callback = function () use ($items, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($items as $item) {
                $itemRef = 'REF-' . str_pad($item->id, 5, '0', STR_PAD_LEFT);

                $barcodeCsv = !empty($item->barcode_number) ? '="' . $item->barcode_number . '"' : '';

                // Base Unit Row
                fputcsv($file, [
                    $itemRef,
                    $item->item_name,
                    $barcodeCsv,
                    $item->category?->name ?? '',
                    $item->brand?->name ?? '',
                    $item->groupAddress?->name ?? '',
                    $item->buy_price,
                    $item->unit_label ?: 'Unit',
                    1,
                    $item->price,
                    $item->wholesale_price,
                    'Yes',
                    $item->front_store_qty,
                    $item->back_store_qty,
                    $item->expiry_date ? $item->expiry_date->format('Y-m-d') : '',
                    $item->item_description,
                ]);

                // Variant Rows
                if ($item->units && $item->units->count() > 0) {
                    foreach ($item->units as $unit) {
                        fputcsv($file, [
                            $itemRef,
                            $item->item_name,
                            $barcodeCsv,
                            $item->category?->name ?? '',
                            $item->brand?->name ?? '',
                            '',
                            '',
                            $unit->unit_name,
                            (int) $unit->quantity_in_base_units,
                            (float) $unit->selling_price,
                            (float) ($unit->wholesale_price ?? $unit->selling_price),
                            'No',
                            '',
                            '',
                            '',
                            '',
                        ]);
                    }
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Display a clean, high-resolution printable price list & catalog
     * for offline customer price consultation and manual sales continuity.
     */
    public function printCatalog(Request $request)
    {
        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are accessing an active branch.');
        }

        $selectedIds = $request->query('ids') ? array_filter(explode(',', $request->query('ids'))) : null;
        $categoryId = $request->query('category_id');
        $search = $request->query('search');

        $items = Item::where('branch_id', $branch->id)
            ->when($selectedIds, fn($q) => $q->whereIn('id', $selectedIds))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('item_name', 'like', '%' . $search . '%')
                        ->orWhere('barcode_number', 'like', '%' . $search . '%');
                });
            })
            ->with([
                'category:id,name',
                'brand:id,name',
                'groupAddress:id,name',
                'units' => fn($q) => $q->where('status', 'active')->orderBy('sort_order'),
            ])
            ->orderBy('category_id')
            ->orderBy('item_name')
            ->get();

        $categories = \App\Models\Category::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $brands = \App\Models\Brand::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Items/PrintCatalog', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'phone' => $branch->phone,
                'email' => $branch->email,
                'address' => $branch->address,
                'logo_url' => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
            ],
            'items' => $items,
            'categories' => $categories,
            'brands' => $brands,
            'selectedCategory' => $categoryId ? (int) $categoryId : null,
            'generatedAt' => now()->format('d M Y, h:i A'),
            'currencySymbol' => \App\Models\SystemSetting::get('currency_symbol', '₦'),
        ]);
    }

    /** Import items using multi-row item_ref / is_base_unit format with accurate duplicate reporting */
    public function importNativeCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $branch = $this->resolveActiveBranch($request);
        if (!$branch) {
            return back()->with('error', 'Active branch could not be determined. Please ensure you are logged into an active branch.');
        }

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        if (!$handle) {
            return back()->with('error', 'Could not open the uploaded CSV file.');
        }

        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            fclose($handle);
            return back()->with('error', 'CSV file appears to be empty.');
        }

        $header = array_map(fn($col) => strtolower(trim($col)), $rawHeader);

        $getColIndex = function (array $possibleNames) use ($header): ?int {
            foreach ($possibleNames as $name) {
                $idx = array_search(strtolower($name), $header, true);
                if ($idx !== false)
                    return $idx;
            }
            return null;
        };

        $refIdx = $getColIndex(['item_ref', 'item ref', 'ref', 'item_code', 'group_ref', 'reference', 'item_id']);
        $nameIdx = $getColIndex(['item_name', 'name', 'title', 'product_name', 'product']);
        $barcodeIdx = $getColIndex(['barcode_number', 'barcode', 'code', 'sku', 'upc']);
        $catIdx = $getColIndex(['category_name', 'category', 'dept']);
        $brandIdx = $getColIndex(['brand_name', 'brand', 'manufacturer', 'make']);
        $groupIdx = $getColIndex(['group_address_name', 'storage_location', 'group_address', 'shelf', 'location']);
        $buyPriceIdx = $getColIndex(['buy_price', 'cost_price', 'buying_price', 'cost']);
        $priceIdx = $getColIndex(['selling_price', 'price', 'retail_price', 'base_unit_price', 'unit_price']);
        $wholesaleIdx = $getColIndex(['wholesale_price', 'wholesale', 'ws_price', 'base_wholesale_price', 'unit_wholesale_price']);
        $unitLabelIdx = $getColIndex(['unit_name', 'unit_label', 'base_unit_name', 'unit', 'variant_name']);
        $qtyInBaseIdx = $getColIndex(['quantity_in_base_units', 'quantity in base units', 'base_quantity', 'qty_in_base', 'multiplier', 'conversion_qty']);
        $isBaseIdx = $getColIndex(['is_base_unit', 'is base unit', 'base_unit', 'is_base', 'base', 'is base', 'is_based_unit']);
        $frontQtyIdx = $getColIndex(['front_store_qty', 'qty', 'front_qty', 'initial_stock_quantity', 'stock_qty', 'quantity']);
        $backQtyIdx = $getColIndex(['back_store_qty', 'back_qty', 'back_store_quantity']);
        $expiryIdx = $getColIndex(['expiry_date', 'expiry', 'expiration_date']);
        $descIdx = $getColIndex(['item_description', 'description', 'notes']);

        $rowNumber = 1;
        $rawRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (empty(array_filter($row))) {
                continue;
            }
            $rawRows[] = ['line' => $rowNumber, 'data' => $row];
        }
        fclose($handle);

        if (empty($rawRows)) {
            return back()->with('error', 'CSV file does not contain any data rows.');
        }

        // Group rows by item_ref in-memory
        $itemGroups = [];
        $seenRefs = [];

        foreach ($rawRows as $itemRow) {
            $r = $itemRow['data'];
            $line = $itemRow['line'];

            $rawRef = $refIdx !== null ? trim($r[$refIdx] ?? '') : '';
            $itemName = $nameIdx !== null ? trim($r[$nameIdx] ?? '') : '';

            $refKey = $rawRef !== '' ? $rawRef : ($itemName !== '' ? 'AUTO_' . Str::slug($itemName) : 'ROW_' . $line);

            // Determine is_base_unit
            $isBaseVal = $isBaseIdx !== null ? strtolower(trim($r[$isBaseIdx] ?? '')) : '';
            $isBase = null;
            if (in_array($isBaseVal, ['yes', 'y', 'true', '1', 'base'], true)) {
                $isBase = true;
            } elseif (in_array($isBaseVal, ['no', 'n', 'false', '0'], true)) {
                $isBase = false;
            } else {
                // If not explicitly set, first row of refKey is base
                $isBase = !isset($seenRefs[$refKey]);
            }
            $seenRefs[$refKey] = true;

            $barcode = $barcodeIdx !== null ? trim($r[$barcodeIdx] ?? '') : '';
            if (str_starts_with($barcode, '="') && str_ends_with($barcode, '"')) {
                $barcode = substr($barcode, 2, -1);
            }
            $barcode = trim($barcode, " '\"\t\n\r\0\x0B");
            if (preg_match('/^[+\-]?[0-9]+(\.[0-9]+)?[eE][+\-]?[0-9]+$/', $barcode)) {
                $barcode = number_format((float) $barcode, 0, '', '');
            } elseif (is_numeric($barcode) && str_contains($barcode, '.')) {
                $parts = explode('.', $barcode);
                if (isset($parts[1]) && ltrim($parts[1], '0') === '') {
                    $barcode = $parts[0];
                }
            }
            if (!$barcode || in_array(strtoupper($barcode), ['NULL', 'N/A', 'NONE', 'NO_BARCODE', 'NO-BARCODE'], true)) {
                $barcode = null;
            }

            $categoryName = $catIdx !== null ? trim($r[$catIdx] ?? '') : '';
            $brandName = $brandIdx !== null ? trim($r[$brandIdx] ?? '') : '';
            $groupName = $groupIdx !== null ? trim($r[$groupIdx] ?? '') : '';
            $buyPrice = $buyPriceIdx !== null && trim($r[$buyPriceIdx] ?? '') !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$buyPriceIdx]) : 0.0;
            $price = $priceIdx !== null && trim($r[$priceIdx] ?? '') !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$priceIdx]) : 0.0;
            $wholesale = $wholesaleIdx !== null && trim($r[$wholesaleIdx] ?? '') !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$wholesaleIdx]) : $price;
            $unitName = $unitLabelIdx !== null && trim($r[$unitLabelIdx] ?? '') !== '' ? trim($r[$unitLabelIdx]) : ($isBase ? 'Unit' : 'Variant');
            $qtyInBase = $qtyInBaseIdx !== null && trim($r[$qtyInBaseIdx] ?? '') !== '' ? max(1, (int) preg_replace('/[^\d]/', '', $r[$qtyInBaseIdx])) : 1;
            $frontQty = $frontQtyIdx !== null && trim($r[$frontQtyIdx] ?? '') !== '' ? max(0, (int) preg_replace('/[^\d]/', '', $r[$frontQtyIdx])) : 0;
            $backQty = $backQtyIdx !== null && trim($r[$backQtyIdx] ?? '') !== '' ? max(0, (int) preg_replace('/[^\d]/', '', $r[$backQtyIdx])) : 0;
            $expiryDate = $expiryIdx !== null ? trim($r[$expiryIdx] ?? '') : null;
            $desc = $descIdx !== null ? trim($r[$descIdx] ?? '') : null;

            $rowObj = [
                'line' => $line,
                'item_name' => $itemName,
                'barcode' => $barcode,
                'category_name' => $categoryName,
                'brand_name' => $brandName,
                'group_address_name' => $groupName,
                'buy_price' => $buyPrice,
                'price' => $price,
                'wholesale_price' => $wholesale,
                'unit_name' => $unitName,
                'quantity_in_base_units' => $qtyInBase,
                'is_base_unit' => $isBase,
                'front_store_qty' => $frontQty,
                'back_store_qty' => $backQty,
                'expiry_date' => $expiryDate,
                'item_description' => $desc,
            ];

            if (!isset($itemGroups[$refKey])) {
                $itemGroups[$refKey] = [
                    'ref' => $refKey,
                    'base_row' => null,
                    'variant_rows' => [],
                ];
            }

            if ($isBase) {
                $itemGroups[$refKey]['base_row'] = $rowObj;
            } else {
                $itemGroups[$refKey]['variant_rows'][] = $rowObj;
            }
        }

        $successCount = 0;
        $failedItems = [];

        // Preload caches for fast in-memory resolution (Scoped to branch + global)
        $categoryCache = [];
        foreach (\App\Models\Category::where('branch_id', $branch->id)->orWhereNull('branch_id')->get() as $cat) {
            $categoryCache[strtolower(trim($cat->name))] = $cat->id;
        }

        $brandCache = [];
        foreach (\App\Models\Brand::where('branch_id', $branch->id)->orWhereNull('branch_id')->get() as $b) {
            $brandCache[strtolower(trim($b->name))] = $b->id;
        }

        $groupCache = [];
        foreach (\App\Models\GroupAddress::where('branch_id', $branch->id)->get() as $grp) {
            $groupCache[strtolower(trim($grp->name))] = $grp->id;
        }

        DB::beginTransaction();
        try {
            foreach ($itemGroups as $refKey => $group) {
                $baseRow = $group['base_row'];
                if (!$baseRow && !empty($group['variant_rows'])) {
                    $baseRow = array_shift($group['variant_rows']);
                }

                if (!$baseRow || empty($baseRow['item_name'])) {
                    $failedItems[] = [
                        'line' => $baseRow['line'] ?? 0,
                        'item_name' => 'Ref: ' . $refKey,
                        'reason' => 'Item name is missing or empty',
                    ];
                    continue;
                }

                $itemName = $baseRow['item_name'];
                $barcode = $baseRow['barcode'];

                // Duplicate Check strictly within this branch
                $duplicateQuery = Item::where('branch_id', $branch->id)
                    ->where(function ($q) use ($barcode, $itemName) {
                        if ($barcode) {
                            $q->where('barcode_number', $barcode);
                        }
                        $q->orWhere('item_name', $itemName);
                    });

                if ($duplicateQuery->exists()) {
                    $failedItems[] = [
                        'line' => $baseRow['line'],
                        'item_name' => $itemName,
                        'reason' => 'Duplicate item (barcode or name already exists in this branch)',
                    ];
                    continue; // Skip duplicate items
                }

                // Category Resolution
                $categoryId = null;
                if (!empty($baseRow['category_name'])) {
                    $catKey = strtolower($baseRow['category_name']);
                    if (!isset($categoryCache[$catKey])) {
                        $category = \App\Models\Category::firstOrCreate(
                            ['name' => $baseRow['category_name'], 'branch_id' => $branch->id],
                            ['slug' => Str::slug($baseRow['category_name']) . '-' . $branch->id . '-' . rand(100, 999)]
                        );
                        $categoryCache[$catKey] = $category->id;
                    }
                    $categoryId = $categoryCache[$catKey];
                }

                // Brand Resolution
                $brandId = null;
                if (!empty($baseRow['brand_name'])) {
                    $brandKey = strtolower(trim($baseRow['brand_name']));
                    if (!isset($brandCache[$brandKey])) {
                        $brand = \App\Models\Brand::create([
                            'name'      => trim($baseRow['brand_name']),
                            'branch_id' => $branch->id,
                            'slug'      => Str::slug(trim($baseRow['brand_name'])) . '-' . $branch->id . '-' . rand(100, 999),
                            'is_active' => true,
                        ]);
                        $brandCache[$brandKey] = $brand->id;
                    }
                    $brandId = $brandCache[$brandKey];
                }

                // Group Address Resolution
                $groupId = null;
                if (!empty($baseRow['group_address_name'])) {
                    $grpKey = strtolower($baseRow['group_address_name']);
                    if (!isset($groupCache[$grpKey])) {
                        $group = \App\Models\GroupAddress::firstOrCreate(
                            ['name' => $baseRow['group_address_name'], 'branch_id' => $branch->id]
                        );
                        $groupCache[$grpKey] = $group->id;
                    }
                    $groupId = $groupCache[$grpKey];
                }

                // Create base item on items table (No item_ref or is_base_unit in DB!)
                $item = new Item();
                $item->branch_id = $branch->id;
                $item->barcode_number = $barcode;
                $item->item_name = $itemName;
                $item->category_id = $categoryId;
                $item->brand_id = $brandId;
                $item->group_address_id = $groupId;
                $item->buy_price = $baseRow['buy_price'];
                $item->price = $baseRow['price'];
                $item->wholesale_price = $baseRow['wholesale_price'];
                $item->front_store_qty = $baseRow['front_store_qty'];
                $item->back_store_qty = $baseRow['back_store_qty'];
                $item->unit_label = $baseRow['unit_name'] ?: 'Unit';
                $item->item_description = $baseRow['item_description'];
                $item->price_locked = true;

                if (!empty($baseRow['expiry_date']) && strtotime($baseRow['expiry_date'])) {
                    $item->expiry_date = date('Y-m-d', strtotime($baseRow['expiry_date']));
                }

                $item->save();

                // Create ItemUnit records for variant rows
                $variantIndex = 1;
                $variantRows = $group['variant_rows'] ?? [];
                if (is_array($variantRows)) {
                    foreach ($variantRows as $vRow) {
                        $vUnitName = !empty($vRow['unit_name']) ? trim($vRow['unit_name']) : 'Variant ' . $variantIndex;
                        $vQtyInBase = max(1, (int) ($vRow['quantity_in_base_units'] ?? 1));
                        $vPrice = (float) ($vRow['price'] > 0 ? $vRow['price'] : ($item->price * $vQtyInBase));
                        $vWholesale = (float) ($vRow['wholesale_price'] > 0 ? $vRow['wholesale_price'] : ($item->wholesale_price * $vQtyInBase));

                        ItemUnit::create([
                            'item_id' => $item->id,
                            'branch_id' => $branch->id,
                            'unit_name' => $vUnitName,
                            'quantity_in_base_units' => $vQtyInBase,
                            'selling_price' => $vPrice,
                            'wholesale_price' => $vWholesale,
                            'sort_order' => $variantIndex,
                            'status' => 'active',
                        ]);
                        $variantIndex++;
                    }
                }

                $successCount++;
            }

            DB::commit();
        } catch (\Throwable $fatalError) {
            DB::rollBack();
            return back()->with('error', 'Import failed and was rolled back: ' . $fatalError->getMessage());
        }

        $totalProcessed = $successCount + count($failedItems);

        return back()->with([
            'success' => "CSV Import finished: {$successCount} item(s) uploaded successfully.",
            'importReport' => [
                'total' => $totalProcessed,
                'success' => $successCount,
                'failed' => $failedItems,
            ]
        ]);
    }

    public function importMedfusionCsv(Request $request)
    {
        return $this->importNativeCsv($request);
    }

    protected function authorizeBranch(Item $item, $branch): void
    {
        if ($item->branch_id !== $branch?->id) {
            abort(403, 'This item does not belong to your branch.');
        }
    }

    /**
     * Resolve the active branch from current context, route parameter, or the authenticated user.
     */
    protected function resolveActiveBranch(?Request $request = null, $branchParam = null): ?\App\Models\Branch
    {
        $branch = current_branch();

        if (!$branch && $branchParam) {
            if ($branchParam instanceof \App\Models\Branch) {
                $branch = $branchParam;
            } elseif (is_string($branchParam) && $branchParam !== '') {
                $branch = \App\Models\Branch::where('slug', $branchParam)->first();
            }
        }

        if (!$branch && $request) {
            $param = $request->route('branch');
            if ($param instanceof \App\Models\Branch) {
                $branch = $param;
            } elseif (is_string($param) && $param !== '') {
                $branch = \App\Models\Branch::where('slug', $param)->first();
            }
        }

        if (!$branch && auth()->check()) {
            $user = auth()->user();
            if ($user->relationLoaded('branch') && $user->branch) {
                $branch = $user->branch;
            } elseif ($user->branch) {
                $branch = $user->branch;
            } elseif (!empty($user->branch_id)) {
                $branch = \App\Models\Branch::find($user->branch_id);
            }
        }

        return $branch;
    }
}
