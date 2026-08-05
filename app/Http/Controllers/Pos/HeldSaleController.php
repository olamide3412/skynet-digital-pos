<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HeldSaleController extends Controller
{
    /** API: list held carts for current user */
    public function apiIndex()
    {
        return response()->json(
            HeldSale::with(['items.item', 'customer'])
                ->where('user_id', Auth::id())
                ->where('status', 'Held')
                ->latest()
                ->get()
        );
    }

    /** API: save (hold) cart */
    public function apiStore(Request $request, $branchParam = null)
    {
        $data = $request->validate([
            'hold_name'   => 'nullable|string|max:255',
            'customer_id' => 'nullable|integer|exists:pos_customers,id',
            'items'       => 'required|array|min:1',
            'items.*.item_id'      => 'required|integer|exists:items,id',
            'items.*.qty'          => 'required|integer|min:1',
            'items.*.price'        => 'required|numeric|min:0',
            'items.*.unit_used'    => 'nullable|string|in:unit,pack,carton',
            'items.*.item_name'    => 'nullable|string',
            'items.*.purchase_type'=> 'nullable|in:Wholesale,Consumer',
        ]);

        $branch = current_branch();

        $held = DB::transaction(function () use ($data, $branch) {
            $heldSale = HeldSale::create([
                'branch_id'   => $branch?->id,
                'user_id'     => Auth::id(),
                'hold_name'   => $data['hold_name'] ?? null,
                'status'      => 'Held',
                'customer_id' => $data['customer_id'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                HeldSaleItem::create([
                    'held_sale_id'  => $heldSale->id,
                    'item_id'       => $item['item_id'],
                    'qty'           => $item['qty'],
                    'price'         => $item['price'],
                    'unit_used'     => $item['unit_used'] ?? 'unit',
                    'item_name'     => $item['item_name'] ?? null,
                    'purchase_type' => $item['purchase_type'] ?? 'Consumer',
                ]);
            }

            return $heldSale->load('items.item');
        });

        return response()->json($held, 201);
    }

    /** API: delete (discard) a held cart */
    public function apiDestroy($branchParam, $id = null)
    {
        $targetId = $id ?? $branchParam;

        $held = HeldSale::where('id', $targetId)
            ->where('user_id', Auth::id())
            ->firstOrFail();
        $held->delete();

        return response()->json(['message' => 'Held sale discarded.']);
    }
}
