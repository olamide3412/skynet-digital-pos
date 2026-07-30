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
        $transactions = InventoryTransaction::with(['item', 'user'])
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
        return Inertia::render('Inventory/Adjust', [
            'items' => Item::select('id', 'item_name', 'qty')->orderBy('item_name')->get()
        ]);
    }

    public function processAdjustment(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|exists:items,id',
            'type'    => 'required|in:Addition,Subtraction',
            'qty'     => 'required|integer|min:1',
            'reason'  => 'required|string|max:255',
        ]);

        $item = Item::findOrFail($data['item_id']);
        
        if ($data['type'] === 'Subtraction' && $item->qty < $data['qty']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'qty' => "Cannot subtract {$data['qty']} from {$item->qty}. Insufficient stock.",
            ]);
        }

        $adjType = $data['type'] === 'Addition' ? 'add' : 'subtract';
        
        InventoryService::adjust(
            $data['item_id'],
            $adjType,
            $data['qty'],
            $data['reason'],
            Auth::user()
        );

        return redirect()->route('pos.inventory.index')->with('success', 'Inventory successfully adjusted.');
    }
}
