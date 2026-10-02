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
        $branch = current_branch();

        setPermissionsTeamId($branch->id);
        $branch->ensureDefaultRoles();

        $items = Item::where('branch_id', $branch->id)
            ->with(['category:id,name', 'groupAddress:id,name', 'units'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sq) use ($request) {
                    $sq->where('item_name', 'like', '%' . $request->search . '%')
                       ->orWhere('barcode_number', 'like', '%' . $request->search . '%');
                });
            })
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->group_address_id, fn($q) => $q->where('group_address_id', $request->group_address_id))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Total stock worth — use the pre-computed stored column (updated on every item save)
        $totalWorth = Item::where('branch_id', $branch->id)->sum('stock_worth');

        $categories = \App\Models\Category::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        $groupAddresses = \App\Models\GroupAddress::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Items/Index', [
            'items' => $items,
            'categories' => $categories,
            'groupAddresses' => $groupAddresses,
            'filters' => $request->only('search', 'category_id', 'group_address_id'),
            'totalWorth' => (float) $totalWorth,
        ]);
    }

    public function create(Request $request)
    {
        $branch = current_branch();

        $clonedItem = null;
        if ($request->has('clone_from')) {
            $cloned = Item::where('branch_id', $branch->id)->with('units')->find($request->clone_from);
            if ($cloned) {
                $clonedItem = [
                    'item_name' => $cloned->item_name . ' (Copy)',
                    'category_id' => $cloned->category_id,
                    'group_address_id' => $cloned->group_address_id,
                    'buy_price' => $cloned->buy_price,
                    'price' => $cloned->price,
                    'wholesale_price' => $cloned->wholesale_price,
                    'unit_label' => $cloned->unit_label,
                    'item_description' => $cloned->item_description,
                    'is_imei_tracked' => $cloned->is_imei_tracked,
                    'price_locked' => $cloned->price_locked,
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
            'groupAddresses' => \App\Models\GroupAddress::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'settings' => \App\Models\PosSettings::current(),
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch() ?? ($request->route('branch') ? \App\Models\Branch::where('slug', $request->route('branch'))->first() : null);
        if (!$branch && auth()->check() && auth()->user()->branch_id) {
            $branch = \App\Models\Branch::find(auth()->user()->branch_id);
        }
        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'barcode_number' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'group_address_id' => 'nullable|exists:group_addresses,id',
            'buy_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_locked' => 'boolean',
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
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $this->authorizeBranch($item, $branch);

        $totalBatchesCount = $item->batches()->count();
        $activeBatchesTotal = (int) $item->batches()->where('status', 'active')->sum('quantity');
        $actualStock = ((int) ($item->front_store_qty ?? 0)) + ((int) ($item->back_store_qty ?? 0));
        $batchVariance = $actualStock - $activeBatchesTotal;

        $item->load([
            'category',
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
            'groupAddresses' => \App\Models\GroupAddress::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->orderBy('name')->get(['id', 'name']),
            'settings' => \App\Models\PosSettings::current(),
        ]);
    }

    public function update(Request $request, $branchParam, Item $item)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $this->authorizeBranch($item, $branch);

        $data = $request->validate([
            'item_name' => 'required|string|max:255',
            'barcode_number' => 'nullable|string|max:100',
            'category_id' => 'required|exists:categories,id',
            'group_address_id' => 'nullable|exists:group_addresses,id',
            'buy_price' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'wholesale_price' => 'nullable|numeric|min:0',
            'price_locked' => 'nullable|boolean',
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
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $this->authorizeBranch($item, $branch);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $itemName = $item->item_name;
        $item->delete();
        \App\Services\ActivityLogger::item("Deleted item '{$itemName}'", $branch->id);
        return redirect()->route('pos.items.index', ['branch' => $branch->slug])
            ->with('success', 'Item deleted.');
    }

    /** Duplicate an item directly into the branch catalog */
    public function duplicate(Request $request, $branchParam = null, $itemParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);

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
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);

        $data = $request->validate([
            'action' => 'required|string|in:delete,update_category,update_group,adjust_prices',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:items,id',
            'category_id' => 'nullable|required_if:action,update_category|exists:categories,id',
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
                foreach ($items as $item) {
                    if ($item->image_path) {
                        Storage::disk('public')->delete($item->image_path);
                    }
                    $item->delete();
                }
                \App\Services\ActivityLogger::item("Bulk deleted {$count} items", $branch->id);
                return back()->with('success', "Successfully deleted {$count} item(s).");

            case 'update_category':
                Item::where('branch_id', $branch->id)
                    ->whereIn('id', $data['item_ids'])
                    ->update(['category_id' => $data['category_id']]);
                \App\Services\ActivityLogger::item("Bulk moved {$count} items to category ID {$data['category_id']}", $branch->id);
                return back()->with('success', "Successfully updated category for {$count} item(s).");

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
        $branch = current_branch() ?? ($request->route('branch') ? \App\Models\Branch::where('slug', $request->route('branch'))->first() : null);
        if (!$branch && auth()->check() && auth()->user()->branch_id) {
            $branch = \App\Models\Branch::find(auth()->user()->branch_id);
        }
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
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
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
        $branch = current_branch() ?: \App\Models\Branch::first();
        $branchName = $branch ? $branch->name : 'branch';
        $fileName = 'skynet_items_' . \Illuminate\Support\Str::slug($branchName) . '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        $selectedIds = $request->query('ids') ? explode(',', $request->query('ids')) : null;

        $items = Item::when($branch, fn($q) => $q->where('branch_id', $branch->id))
            ->when($selectedIds, fn($q) => $q->whereIn('id', $selectedIds))
            ->with(['category', 'groupAddress', 'units'])
            ->latest()
            ->get();

        $columns = [
            'item_ref',
            'item_name',
            'barcode_number',
            'category_name',
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

    /** Import items using multi-row item_ref / is_base_unit format with accurate duplicate reporting */
    public function importNativeCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $branch = current_branch() ?: \App\Models\Branch::first();
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
        $groupIdx = $getColIndex(['group_address_name', 'storage_location', 'group_address', 'shelf', 'location']);
        $buyPriceIdx = $getColIndex(['buy_price', 'cost_price', 'buying_price', 'cost']);
        $priceIdx = $getColIndex(['selling_price', 'price', 'retail_price', 'base_unit_price', 'unit_price']);
        $wholesaleIdx = $getColIndex(['wholesale_price', 'wholesale', 'ws_price', 'base_wholesale_price', 'unit_wholesale_price']);
        $unitLabelIdx = $getColIndex(['unit_name', 'unit_label', 'base_unit_name', 'unit', 'variant_name']);
        $qtyInBaseIdx = $getColIndex(['quantity_in_base_units', 'quantity in base units', 'base_quantity', 'qty_in_base', 'multiplier', 'units_per_pack', 'pack_quantity', 'carton_quantity', 'conversion_qty']);
        $isBaseIdx = $getColIndex(['is_base_unit', 'is base unit', 'base_unit', 'is_base', 'base', 'is base', 'is_based_unit']);
        $frontQtyIdx = $getColIndex(['front_store_qty', 'qty', 'front_qty', 'initial_stock_quantity', 'stock_qty', 'quantity']);
        $backQtyIdx = $getColIndex(['back_store_qty', 'back_qty', 'back_store_quantity']);
        $expiryIdx = $getColIndex(['expiry_date', 'expiry', 'expiration_date']);
        $descIdx = $getColIndex(['item_description', 'description', 'notes']);

        // Legacy pack/carton aliases for backwards compatibility with old CSV files
        $packNameIdx = $getColIndex(['pack_unit_name', 'pack_name', 'pack_label']);
        $packQtyIdx = $getColIndex(['pack_quantity', 'units_per_pack', 'pack_qty', 'pack_size']);
        $packPriceIdx = $getColIndex(['pack_price', 'pack_selling_price']);
        $packWholesaleIdx = $getColIndex(['pack_wholesale_price', 'pack_ws_price']);
        $cartonNameIdx = $getColIndex(['carton_unit_name', 'carton_name', 'carton_label']);
        $cartonQtyIdx = $getColIndex(['carton_quantity', 'packs_per_carton', 'carton_qty', 'carton_size']);
        $cartonPriceIdx = $getColIndex(['carton_price', 'carton_selling_price']);
        $cartonWholesaleIdx = $getColIndex(['carton_wholesale_price', 'carton_ws_price']);

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

            // Legacy pack/carton support for single-row files
            $legacyPack = null;
            if ($packQtyIdx !== null || $packPriceIdx !== null) {
                $pQty = $packQtyIdx !== null ? max(1, (int) preg_replace('/[^\d]/', '', (string) ($r[$packQtyIdx] ?? '1'))) : 1;
                $pPrice = $packPriceIdx !== null && trim((string) ($r[$packPriceIdx] ?? '')) !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$packPriceIdx]) : null;
                $pWholesale = $packWholesaleIdx !== null && trim((string) ($r[$packWholesaleIdx] ?? '')) !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$packWholesaleIdx]) : null;
                $pName = $packNameIdx !== null && trim((string) ($r[$packNameIdx] ?? '')) !== '' ? trim($r[$packNameIdx]) : 'Pack';
                if (($pPrice !== null && $pPrice > 0) || $pQty > 1) {
                    $legacyPack = [
                        'unit_name' => $pName,
                        'quantity_in_base_units' => $pQty,
                        'selling_price' => $pPrice ?? ($price * $pQty),
                        'wholesale_price' => $pWholesale ?? ($wholesale * $pQty),
                    ];
                }
            }

            $legacyCarton = null;
            if ($cartonQtyIdx !== null || $cartonPriceIdx !== null) {
                $cQty = $cartonQtyIdx !== null ? max(1, (int) preg_replace('/[^\d]/', '', (string) ($r[$cartonQtyIdx] ?? '1'))) : 1;
                $cPrice = $cartonPriceIdx !== null && trim((string) ($r[$cartonPriceIdx] ?? '')) !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$cartonPriceIdx]) : null;
                $cWholesale = $cartonWholesaleIdx !== null && trim((string) ($r[$cartonWholesaleIdx] ?? '')) !== '' ? (float) preg_replace('/[^\d.]/', '', $r[$cartonWholesaleIdx]) : null;
                $cName = $cartonNameIdx !== null && trim((string) ($r[$cartonNameIdx] ?? '')) !== '' ? trim($r[$cartonNameIdx]) : 'Carton';
                if (($cPrice !== null && $cPrice > 0) || $cQty > 1) {
                    $pMultiplier = $legacyPack ? $legacyPack['quantity_in_base_units'] : 1;
                    $cBaseQty = $pMultiplier * $cQty;
                    $legacyCarton = [
                        'unit_name' => $cName,
                        'quantity_in_base_units' => $cBaseQty,
                        'selling_price' => $cPrice ?? ($price * $cBaseQty),
                        'wholesale_price' => $cWholesale ?? ($wholesale * $cBaseQty),
                    ];
                }
            }

            $rowObj = [
                'line' => $line,
                'item_name' => $itemName,
                'barcode' => $barcode,
                'category_name' => $categoryName,
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
                'legacy_pack' => $legacyPack,
                'legacy_carton' => $legacyCarton,
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
        $categoryCache = [];
        $groupCache = [];

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

                // If legacy pack / carton columns were present on base row, create them as ItemUnits
                if (!empty($baseRow['legacy_pack'])) {
                    ItemUnit::create([
                        'item_id' => $item->id,
                        'branch_id' => $branch->id,
                        'unit_name' => $baseRow['legacy_pack']['unit_name'],
                        'quantity_in_base_units' => $baseRow['legacy_pack']['quantity_in_base_units'],
                        'selling_price' => $baseRow['legacy_pack']['selling_price'],
                        'wholesale_price' => $baseRow['legacy_pack']['wholesale_price'],
                        'sort_order' => $variantIndex++,
                        'status' => 'active',
                    ]);
                }

                if (!empty($baseRow['legacy_carton'])) {
                    ItemUnit::create([
                        'item_id' => $item->id,
                        'branch_id' => $branch->id,
                        'unit_name' => $baseRow['legacy_carton']['unit_name'],
                        'quantity_in_base_units' => $baseRow['legacy_carton']['quantity_in_base_units'],
                        'selling_price' => $baseRow['legacy_carton']['selling_price'],
                        'wholesale_price' => $baseRow['legacy_carton']['wholesale_price'],
                        'sort_order' => $variantIndex++,
                        'status' => 'active',
                    ]);
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
}
