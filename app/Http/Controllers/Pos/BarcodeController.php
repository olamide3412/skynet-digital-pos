<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\BarcodePrintLog;
use App\Models\Category;
use App\Models\Item;
use App\Models\PosSettings;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BarcodeController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        // 1. Items query for Label Studio & History
        $itemsQuery = Item::where('branch_id', $branch->id)
            ->with('category:id,name')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('item_name', 'like', "%{$search}%")
                       ->orWhere('barcode_number', 'like', "%{$search}%");
                });
            })
            ->when($request->category_id, fn($q, $cat) => $q->where('category_id', $cat))
            ->when($request->filter === 'missing', function ($q) {
                $q->where(function ($sq) {
                    $sq->whereNull('barcode_number')
                       ->orWhere('barcode_number', '')
                       ->orWhere('barcode_number', 'like', 'NO_BARCODE%');
                });
            })
            ->when($request->filter === 'assigned', function ($q) {
                $q->whereNotNull('barcode_number')
                  ->where('barcode_number', '!=', '')
                  ->where('barcode_number', 'not like', 'NO_BARCODE%');
            })
            ->orderBy('item_name');

        $items = (clone $itemsQuery)->paginate(30)->withQueryString();

        // 2. Count missing barcodes
        $missingCount = Item::where('branch_id', $branch->id)
            ->where(function ($q) {
                $q->whereNull('barcode_number')
                  ->orWhere('barcode_number', '')
                  ->orWhere('barcode_number', 'like', 'NO_BARCODE%');
            })
            ->count();

        $totalItems = Item::where('branch_id', $branch->id)->count();

        // 3. Print Logs History
        $printLogs = BarcodePrintLog::where('branch_id', $branch->id)
            ->with('user:id,name')
            ->latest()
            ->paginate(15, ['*'], 'logs_page')
            ->withQueryString();

        // 4. Categories for filter
        $categories = Category::where(function ($q) use ($branch) {
            $q->where('branch_id', $branch->id)->orWhereNull('branch_id');
        })->orderBy('name')->get(['id', 'name']);

        $settings = PosSettings::current();
        $settingsData = $settings->toArray();
        if ($branch) {
            $settingsData['business_name'] = $branch->name;
        }

        return Inertia::render('Items/Barcodes', [
            'items'        => $items,
            'missingCount' => $missingCount,
            'totalItems'   => $totalItems,
            'printLogs'    => $printLogs,
            'categories'   => $categories,
            'settings'     => $settingsData,
            'branch'       => $branch ? ['id' => $branch->id, 'name' => $branch->name] : null,
            'filters'      => $request->only(['search', 'category_id', 'filter']),
        ]);
    }

    /**
     * Auto-generate a Code 128 barcode for a single item.
     */
    public function generate(Request $request, $branchParam, Item $item)
    {
        $branch = current_branch();
        if ($item->branch_id !== $branch->id) {
            abort(403, 'Unauthorized item access.');
        }

        // Only generate if item has no barcode or placeholder
        if ($this->hasValidBarcode($item->barcode_number)) {
            return back()->with('info', "Item '{$item->item_name}' already has barcode: {$item->barcode_number}");
        }

        $newBarcode = $this->generateUniqueCode($branch->id, $item->id);
        $item->update(['barcode_number' => $newBarcode]);

        ActivityLogger::item("Auto-generated barcode '{$newBarcode}' for item '{$item->item_name}'", $branch->id);

        return back()->with('success', "Barcode '{$newBarcode}' successfully generated for '{$item->item_name}'.");
    }

    /**
     * Fast live async search endpoint for picking items across 5,000+ records.
     */
    public function searchItems(Request $request)
    {
        $branch = current_branch();
        $q = trim($request->q ?? '');

        if (!$q) {
            return response()->json([]);
        }

        $items = Item::where('branch_id', $branch->id)
            ->where(function ($query) use ($q) {
                $query->where('item_name', 'like', "%{$q}%")
                      ->orWhere('barcode_number', 'like', "%{$q}%");
            })
            ->select(['id', 'item_name', 'barcode_number', 'price', 'front_store_qty', 'category_id'])
            ->with('category:id,name')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    /**
     * Auto-generate barcodes in bulk for all items missing a barcode in the active branch.
     * Uses chunkById(100) for safe memory management with large catalogs (e.g. 5,000+ items).
     */
    public function generateBulk(Request $request)
    {
        $branch = current_branch();

        $count = 0;
        Item::where('branch_id', $branch->id)
            ->where(function ($q) {
                $q->whereNull('barcode_number')
                  ->orWhere('barcode_number', '')
                  ->orWhere('barcode_number', 'like', 'NO_BARCODE%');
            })
            ->chunkById(100, function ($missingItems) use ($branch, &$count) {
                foreach ($missingItems as $item) {
                    $barcode = $this->generateUniqueCode($branch->id, $item->id);
                    $item->update(['barcode_number' => $barcode]);
                    $count++;
                }
            });

        if ($count === 0) {
            return back()->with('info', 'All items in this branch already have assigned barcodes.');
        }

        ActivityLogger::item("Bulk auto-generated barcodes for {$count} items", $branch->id);

        return back()->with('success', "Successfully generated {$count} barcodes for items missing barcodes.");
    }

    /**
     * Log a print run for audit and reprinting.
     */
    public function logPrint(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'label_size' => 'required|string|max:100',
            'prints'     => 'required|array|min:1',
            'prints.*.item_id'          => 'nullable|exists:items,id',
            'prints.*.item_name'        => 'required|string|max:255',
            'prints.*.barcode_value'    => 'required|string|max:100',
            'prints.*.quantity_printed' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();

        foreach ($data['prints'] as $entry) {
            BarcodePrintLog::create([
                'branch_id'        => $branch->id,
                'item_id'          => $entry['item_id'] ?? null,
                'item_name'        => $entry['item_name'],
                'barcode_value'    => $entry['barcode_value'],
                'label_size'       => $data['label_size'],
                'quantity_printed' => $entry['quantity_printed'],
                'user_id'          => $userId,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Check if a barcode is considered already valid and assigned.
     */
    protected function hasValidBarcode(?string $barcode): bool
    {
        $val = trim($barcode ?? '');
        return !empty($val) && stripos($val, 'NO_BARCODE') !== 0;
    }

    /**
     * Generates a standard Code 128 formatted sequential unique barcode per branch.
     * e.g., ITM000001, ITM000002...
     */
    protected function generateUniqueCode(int $branchId, int $itemId): string
    {
        $prefix = 'ITM';
        $seed = $itemId;
        $candidate = sprintf('%s%06d', $prefix, $seed);
        $counter = 1;

        while (Item::where('branch_id', $branchId)->where('barcode_number', $candidate)->exists()) {
            $candidate = sprintf('%s%06d', $prefix, $seed + $counter);
            $counter++;
        }

        return $candidate;
    }
}
