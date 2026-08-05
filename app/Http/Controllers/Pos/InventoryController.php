<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\InventoryTransaction;

use App\Models\Item;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $transactions = InventoryTransaction::with(['item', 'user'])
            ->where('branch_id', $branch->id)
            ->when($request->item_id, fn ($q) => $q->where('item_id', $request->item_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'transactions' => $transactions,
            'filters'      => $request->only('item_id', 'type'),
        ]);
    }

    public function adjustForm()
    {
        $branch = current_branch();
        return Inertia::render('Inventory/Adjust', [
            'items' => Item::where('branch_id', $branch->id)
                ->select('id', 'item_name', 'barcode_number', 'front_store_qty', 'back_store_qty')
                ->orderBy('item_name')
                ->limit(25)
                ->get()
        ]);
    }

    public function processAdjustment(Request $request)
    {
        $branch = current_branch();
        $data = $request->validate([
            'item_id'  => 'required|exists:items,id',
            'location' => 'required|in:front_store,back_store',
            'type'     => 'required|in:Addition,Subtraction',
            'qty'      => 'required|integer|min:1',
            'reason'   => 'required|string|max:255',
        ]);

        $item = Item::where('branch_id', $branch->id)->findOrFail($data['item_id']);
        $col  = $data['location'] === 'back_store' ? 'back_store_qty' : 'front_store_qty';
        
        if ($data['type'] === 'Subtraction' && $item->{$col} < $data['qty']) {
            $locName = $data['location'] === 'back_store' ? 'Back Store' : 'Front Store';
            throw \Illuminate\Validation\ValidationException::withMessages([
                'qty' => "Cannot subtract {$data['qty']} from {$item->{$col}} in {$locName}. Insufficient stock.",
            ]);
        }

        $adjType = $data['type'] === 'Addition' ? 'add' : 'subtract';
        
        InventoryService::adjust(
            $data['item_id'],
            $adjType,
            $data['qty'],
            $data['reason'],
            Auth::user(),
            $data['location']
        );

        return redirect()->route('pos.inventory.index')->with('success', 'Inventory successfully adjusted.');
    }
}
