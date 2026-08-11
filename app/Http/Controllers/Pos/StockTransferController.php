<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockTransferController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $transfers = StockTransfer::where('branch_id', $branch->id)
            ->with(['item:id,item_name,barcode_number', 'user:id,name'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $items = Item::where('branch_id', $branch->id)
            ->select('id', 'item_name', 'barcode_number', 'back_store_qty', 'front_store_qty',
                     'unit_label', 'pack_label', 'carton_label', 'units_per_pack', 'packs_per_carton')
            ->orderBy('item_name')
            ->limit(25)
            ->get();

        return Inertia::render('Inventory/StockTransfer', [
            'transfers' => $transfers,
            'items'     => $items,
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $data = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'qty'           => 'required|integer|min:1',
            'unit'          => 'required|in:unit,pack,carton',
            'from_location' => 'required|in:back_store,front_store',
            'to_location'   => 'required|in:back_store,front_store',
            'notes'         => 'nullable|string|max:500',
        ]);

        $item = Item::where('branch_id', $branch->id)->findOrFail($data['item_id']);

        // Convert to base units using the item's conversion rates
        $qtyBase = $item->toBaseUnits($data['qty'], $data['unit']);

        try {
            InventoryService::transferStock(
                item: $item,
                qtyBaseUnits: $qtyBase,
                from: $data['from_location'],
                to: $data['to_location'],
                user: auth()->user(),
                unitUsed: $data['unit'],
                notes: $data['notes'] ?? null,
            );

            \App\Services\ActivityLogger::stock(
                "Transferred {$data['qty']} {$data['unit']}(s) of '{$item->item_name}' from {$data['from_location']} to {$data['to_location']}",
                $branch->id,
                [
                    'item_id'       => $item->id,
                    'item_name'     => $item->item_name,
                    'qty'           => $data['qty'],
                    'unit'          => $data['unit'],
                    'from_location' => $data['from_location'],
                    'to_location'   => $data['to_location'],
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        $unitLabel = match($data['unit']) {
            'carton' => $item->carton_label,
            'pack'   => $item->pack_label,
            default  => $item->unit_label,
        };

        return back()->with('success',
            "Transferred {$data['qty']} {$unitLabel} of '{$item->item_name}' " .
            "from {$data['from_location']} to {$data['to_location']}."
        );
    }
}
