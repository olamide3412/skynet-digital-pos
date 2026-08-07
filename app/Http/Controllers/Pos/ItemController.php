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
            'category_id'            => 'required|exists:categories,id',
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

        // Auto-generate barcode if blank or 'NO_BARCODE'
        $barcode = trim($data['barcode_number'] ?? '');
        if (!$barcode || strtoupper($barcode) === 'NO_BARCODE') {
            $barcode = 'BAR-' . strtoupper(\Illuminate\Support\Str::random(6));
        } else {
            // Check barcode uniqueness in active branch
            $exists = Item::where('branch_id', $branch->id)
                ->where('barcode_number', $barcode)
                ->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'barcode_number' => 'This barcode number is already assigned to another item in your branch.',
                ]);
            }
        }
        $data['barcode_number'] = $barcode;

        $data['branch_id']        = $branch->id;
        $data['wholesale_price']  = (!empty($data['wholesale_price']) && $data['wholesale_price'] > 0) ? $data['wholesale_price'] : $data['price'];
        $data['front_store_qty']  = $data['front_store_qty'] ?? 0;
        $data['back_store_qty']   = $data['back_store_qty'] ?? 0;
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
            'category_id'            => 'required|exists:categories,id',
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

        $barcode = trim($data['barcode_number'] ?? '');
        if (!$barcode || strtoupper($barcode) === 'NO_BARCODE') {
            $barcode = $item->barcode_number ?: ('BAR-' . strtoupper(\Illuminate\Support\Str::random(6)));
        } else {
            // Check barcode uniqueness in active branch excluding current item
            $exists = Item::where('branch_id', $branch->id)
                ->where('barcode_number', $barcode)
                ->where('id', '!=', $item->id)
                ->exists();
            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'barcode_number' => 'This barcode number is already assigned to another item in your branch.',
                ]);
            }
        }
        $data['barcode_number'] = $barcode;

        $data['wholesale_price']  = (!empty($data['wholesale_price']) && $data['wholesale_price'] > 0) ? $data['wholesale_price'] : $data['price'];
        $data['front_store_qty']  = $data['front_store_qty'] ?? 0;
        $data['back_store_qty']   = $data['back_store_qty'] ?? 0;
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
                'qty' => $item->front_store_qty,
                'total_qty' => $item->total_qty,
                'display_price' => $item->getPriceForUnitLevel('unit', $purchaseType),
                'pack_display_price' => $item->getPriceForUnitLevel('pack', $purchaseType),
                'carton_display_price' => $item->getPriceForUnitLevel('carton', $purchaseType),
                'image_url' => $item->image_url,
            ]));

        return response()->json($items);
    }

    /** Export sample CSV template matching current items table schema */
    public function exportTemplate()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=items_import_template.csv',
        ];

        $columns = [
            'item_name', 'barcode_number', 'category_name', 'group_address_name',
            'buy_price', 'price', 'wholesale_price', 'pack_price', 'carton_price',
            'front_store_qty', 'back_store_qty', 'unit_label', 'units_per_pack',
            'packs_per_carton', 'expiry_date', 'item_description'
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            fputcsv($file, [
                'Paracetamol 500mg', 'BAR-100201', 'Analgesics', 'Shelf A1',
                '250.00', '350.00', '320.00', '3200.00', '30000.00',
                '50', '200', 'Tablet', '10', '10',
                '2027-12-31', 'Pain relief tablets'
            ]);
            fputcsv($file, [
                'Amoxicillin 250mg', 'BAR-100202', 'Antibiotics', 'Shelf B2',
                '1200.00', '1500.00', '1400.00', '14000.00', '130000.00',
                '30', '100', 'Capsule', '10', '10',
                '2026-10-15', 'Broad spectrum antibiotic'
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** Export all current branch items into CSV using native schema */
    public function exportCsv()
    {
        $branch = current_branch();
        $fileName = 'skynet_items_' . \Illuminate\Support\Str::slug($branch->name) . '_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        $items = Item::where('branch_id', $branch->id)
            ->with(['category', 'groupAddress'])
            ->latest()
            ->get();

        $columns = [
            'item_name', 'barcode_number', 'category_name', 'group_address_name',
            'buy_price', 'price', 'wholesale_price', 'pack_price', 'carton_price',
            'front_store_qty', 'back_store_qty', 'unit_label', 'units_per_pack',
            'packs_per_carton', 'expiry_date', 'item_description'
        ];

        $callback = function () use ($items, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($items as $item) {
                fputcsv($file, [
                    $item->item_name,
                    $item->barcode_number,
                    $item->category?->name ?? '',
                    $item->groupAddress?->name ?? '',
                    $item->buy_price,
                    $item->price,
                    $item->wholesale_price,
                    $item->pack_price,
                    $item->carton_price,
                    $item->front_store_qty,
                    $item->back_store_qty,
                    $item->unit_label,
                    $item->units_per_pack,
                    $item->packs_per_carton,
                    $item->expiry_date ? $item->expiry_date->format('Y-m-d') : '',
                    $item->item_description,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /** Import items using current schema & return detailed success / fail report */
    public function importNativeCsv(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $branch = current_branch();
        $file   = $request->file('csv_file');
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
                if ($idx !== false) return $idx;
            }
            return null;
        };

        $nameIdx        = $getColIndex(['item_name', 'name', 'title']);
        $barcodeIdx     = $getColIndex(['barcode_number', 'barcode', 'code']);
        $catIdx         = $getColIndex(['category_name', 'category']);
        $groupIdx       = $getColIndex(['group_address_name', 'storage_location', 'group_address']);
        $buyPriceIdx    = $getColIndex(['buy_price', 'buying_price', 'cost']);
        $priceIdx       = $getColIndex(['price', 'selling_price']);
        $wholesaleIdx   = $getColIndex(['wholesale_price', 'wholesale']);
        $packPriceIdx   = $getColIndex(['pack_price']);
        $cartonPriceIdx = $getColIndex(['carton_price']);
        $frontQtyIdx    = $getColIndex(['front_store_qty', 'qty', 'front_qty']);
        $backQtyIdx     = $getColIndex(['back_store_qty', 'back_qty']);
        $unitLabelIdx   = $getColIndex(['unit_label']);
        $unitsPackIdx   = $getColIndex(['units_per_pack']);
        $packsCartonIdx = $getColIndex(['packs_per_carton']);
        $expiryIdx      = $getColIndex(['expiry_date', 'expiry']);
        $descIdx        = $getColIndex(['item_description', 'description']);

        $rowNumber    = 1;
        $successCount = 0;
        $failedItems  = [];
        $categoryCache = [];
        $groupCache    = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (empty(array_filter($row))) continue;

            $itemName = $nameIdx !== null ? trim($row[$nameIdx] ?? '') : '';
            if (!$itemName) {
                $failedItems[] = [
                    'line'      => $rowNumber,
                    'item_name' => 'Row ' . $rowNumber,
                    'reason'    => 'Item name is missing or empty',
                ];
                continue;
            }

            $barcode      = $barcodeIdx !== null ? trim($row[$barcodeIdx] ?? '') : '';
            $categoryName = $catIdx !== null ? trim($row[$catIdx] ?? '') : '';
            $groupName    = $groupIdx !== null ? trim($row[$groupIdx] ?? '') : '';
            $buyPrice     = $buyPriceIdx !== null ? (float) ($row[$buyPriceIdx] ?? 0) : 0.0;
            $price        = $priceIdx !== null ? (float) ($row[$priceIdx] ?? 0) : 0.0;
            $wholesale    = $wholesaleIdx !== null ? (float) ($row[$wholesaleIdx] ?? 0) : $price;
            $packPrice    = $packPriceIdx !== null && ($row[$packPriceIdx] ?? '') !== '' ? (float) $row[$packPriceIdx] : null;
            $cartonPrice  = $cartonPriceIdx !== null && ($row[$cartonPriceIdx] ?? '') !== '' ? (float) $row[$cartonPriceIdx] : null;
            $frontQty     = $frontQtyIdx !== null ? (int) ($row[$frontQtyIdx] ?? 0) : 0;
            $backQty      = $backQtyIdx !== null ? (int) ($row[$backQtyIdx] ?? 0) : 0;
            $unitLabel    = $unitLabelIdx !== null ? trim($row[$unitLabelIdx] ?? '') : 'Unit';
            $unitsPerPack = $unitsPackIdx !== null ? max(1, (int) ($row[$unitsPackIdx] ?? 1)) : 1;
            $packsCarton  = $packsCartonIdx !== null ? max(1, (int) ($row[$packsCartonIdx] ?? 1)) : 1;
            $expiryDate   = $expiryIdx !== null ? trim($row[$expiryIdx] ?? '') : null;
            $desc         = $descIdx !== null ? trim($row[$descIdx] ?? '') : null;

            if (!$barcode || strtoupper($barcode) === 'NO_BARCODE') {
                $barcode = 'BAR-' . strtoupper(\Illuminate\Support\Str::random(6));
            }

            try {
                $categoryId = null;
                if ($categoryName) {
                    $catKey = strtolower($categoryName);
                    if (!isset($categoryCache[$catKey])) {
                        $category = \App\Models\Category::firstOrCreate(
                            ['name' => $categoryName, 'branch_id' => $branch->id],
                            ['slug' => \Illuminate\Support\Str::slug($categoryName)]
                        );
                        $categoryCache[$catKey] = $category->id;
                    }
                    $categoryId = $categoryCache[$catKey];
                }

                $groupId = null;
                if ($groupName) {
                    $grpKey = strtolower($groupName);
                    if (!isset($groupCache[$grpKey])) {
                        $group = \App\Models\GroupAddress::firstOrCreate(
                            ['name' => $groupName, 'branch_id' => $branch->id]
                        );
                        $groupCache[$grpKey] = $group->id;
                    }
                    $groupId = $groupCache[$grpKey];
                }

                $item = Item::where('branch_id', $branch->id)
                    ->where(fn($q) => $q->where('barcode_number', $barcode)->orWhere('item_name', $itemName))
                    ->first();

                if (!$item) {
                    $item = new Item();
                    $item->branch_id      = $branch->id;
                    $item->barcode_number = $barcode;
                }

                $item->item_name        = $itemName;
                $item->category_id      = $categoryId;
                $item->group_address_id = $groupId;
                $item->buy_price        = $buyPrice;
                $item->price            = $price;
                $item->wholesale_price  = $wholesale;
                $item->pack_price       = $packPrice;
                $item->carton_price     = $cartonPrice;
                $item->front_store_qty  = $frontQty;
                $item->back_store_qty   = $backQty;
                $item->unit_label       = $unitLabel ?: 'Unit';
                $item->units_per_pack   = $unitsPerPack;
                $item->packs_per_carton = $packsCarton;
                $item->item_description = $desc;
                $item->price_locked     = true;

                if ($expiryDate && strtotime($expiryDate)) {
                    $item->expiry_date = date('Y-m-d', strtotime($expiryDate));
                }

                $item->save();
                $successCount++;
            } catch (\Throwable $e) {
                $failedItems[] = [
                    'line'      => $rowNumber,
                    'item_name' => $itemName ?: 'Row ' . $rowNumber,
                    'reason'    => $e->getMessage(),
                ];
            }
        }

        fclose($handle);

        $totalProcessed = $successCount + count($failedItems);

        return back()->with([
            'success'      => "CSV Import finished: {$successCount} item(s) uploaded successfully.",
            'importReport' => [
                'total'   => $totalProcessed,
                'success' => $successCount,
                'failed'  => $failedItems,
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
