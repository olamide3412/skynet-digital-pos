<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\Item;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $orders = PurchaseOrder::with(['vendor', 'user'])
            ->where('branch_id', $branch->id)
            ->when($request->search, fn ($q) => $q->where('po_number', 'like', '%' . $request->search . '%'))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Purchases/Index', [
            'orders'  => $orders,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function create()
    {
        $branch = current_branch();
        return Inertia::render('Purchases/Create', [
            'vendors' => Vendor::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
                ->where('status', 'Active')
                ->orderBy('name')
                ->get(['id', 'name', 'company_name']),
            'availableItems' => Item::where('branch_id', $branch->id)
                ->orderBy('item_name')
                ->get(['id', 'item_name', 'barcode_number', 'buy_price', 'front_store_qty', 'back_store_qty']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id'       => 'required|exists:vendors,id',
            'expected_date'   => 'nullable|date',
            'shipping_cost'   => 'nullable|numeric|min:0',
            'discount'        => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty'     => 'required|integer|min:1',
            'items.*.cost'    => 'required|numeric|min:0',
        ]);

        $po = DB::transaction(function () use ($data) {
            $branch   = current_branch();
            $subtotal = collect($data['items'])->sum(fn ($i) => $i['qty'] * $i['cost']);
            $total    = $subtotal + ($data['shipping_cost'] ?? 0) - ($data['discount'] ?? 0);

            $po = PurchaseOrder::create([
                'branch_id'     => $branch->id,
                'vendor_id'     => $data['vendor_id'],
                'user_id'       => Auth::id(),
                'po_number'     => 'PO-' . date('Ymd') . '-' . strtoupper(\Illuminate\Support\Str::random(5)),
                'order_date'    => now()->toDateString(),
                'status'        => 'Pending',
                'expected_date' => $data['expected_date'] ?? null,
                'shipping_cost' => $data['shipping_cost'] ?? 0,
                'discount'      => $data['discount'] ?? 0,
                'total_amount'  => $total,
                'notes'         => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $itemData) {
                $item = Item::find($itemData['item_id']);
                if ($item) {
                    $po->items()->create([
                        'item_id'   => $item->id,
                        'item_name' => $item->item_name,
                        'qty'       => $itemData['qty'],
                        'cost'      => $itemData['cost'],
                        'total'     => $itemData['qty'] * $itemData['cost'],
                    ]);
                }
            }

            return $po;
        });

        return redirect()->route('pos.purchases.index')->with('success', 'Purchase order created: ' . $po->po_number);
    }

    public function show($branchParam, PurchaseOrder $purchase)
    {
        $purchase->load(['vendor', 'user', 'items.item', 'receivedItems.user']);
        return Inertia::render('Purchases/Show', ['order' => $purchase]);
    }

    public function receiveForm($branchParam, PurchaseOrder $purchase)
    {
        if ($purchase->status === 'Received') {
            return redirect()->route('pos.purchases.show', $purchase)->with('error', 'Order already fully received.');
        }

        $purchase->load(['vendor', 'items.item']);
        return Inertia::render('Purchases/Receive', ['order' => $purchase]);
    }

    public function processReceive(Request $request, $branchParam, PurchaseOrder $purchase)
    {
        if ($purchase->status === 'Received') {
            return back()->with('error', 'Order already fully received.');
        }

        $data = $request->validate([
            'items'               => 'required|array|min:1',
            'items.*.id'          => 'required|exists:purchase_order_items,id',
            'items.*.receive_qty' => 'required|integer|min:0',
            'items.*.location'    => 'nullable|in:back_store,front_store',
            'items.*.imeis'       => 'nullable|array',
            'items.*.imeis.*'     => 'nullable|string|max:100',
        ]);

        $branch = current_branch();

        DB::transaction(function () use ($data, $purchase, $branch) {
            $allReceived = true;
            $itemsReceived = 0;

            foreach ($data['items'] as $receiveData) {
                $poItem = $purchase->items()->with('item')->find($receiveData['id']);
                if (!$poItem || $receiveData['receive_qty'] <= 0) {
                    if ($poItem && $poItem->received_qty < $poItem->qty) $allReceived = false;
                    continue;
                }

                $qtyToReceive = $receiveData['receive_qty'];
                $destLocation = $receiveData['location'] ?? 'back_store';
                
                // Prevent over-receiving
                if ($poItem->received_qty + $qtyToReceive > $poItem->qty) {
                    $qtyToReceive = $poItem->qty - $poItem->received_qty;
                }

                $item = $poItem->item;
                $enteredImeis = [];

                // Handle IMEI tracking validation
                if ($item && $item->is_imei_tracked && $qtyToReceive > 0) {
                    $rawImeis = $receiveData['imeis'] ?? [];
                    $enteredImeis = array_values(array_unique(array_filter(array_map('trim', $rawImeis))));

                    if (count($enteredImeis) !== $qtyToReceive) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => "Please enter exactly {$qtyToReceive} distinct IMEI/Device IDs for '{$item->item_name}' (entered: " . count($enteredImeis) . ").",
                        ]);
                    }

                    // Check duplicate in-stock IMEIs for this branch
                    $duplicate = \App\Models\ItemDeviceUnit::where('branch_id', $branch->id)
                        ->where('status', 'in_stock')
                        ->whereIn('imei_or_device_id', $enteredImeis)
                        ->value('imei_or_device_id');

                    if ($duplicate) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => "IMEI/Device ID '{$duplicate}' is already in stock in this branch.",
                        ]);
                    }
                }
                
                if ($qtyToReceive > 0) {
                    $purchase->receivedItems()->create([
                        'item_id' => $poItem->item_id,
                        'user_id' => Auth::id(),
                        'qty'     => $qtyToReceive,
                        'cost'    => $poItem->cost,
                    ]);

                    $poItem->increment('received_qty', $qtyToReceive);

                    // Insert individual device unit records
                    if ($item && $item->is_imei_tracked && !empty($enteredImeis)) {
                        foreach ($enteredImeis as $imei) {
                            \App\Models\ItemDeviceUnit::create([
                                'branch_id'         => $branch->id,
                                'item_id'           => $item->id,
                                'imei_or_device_id' => $imei,
                                'status'            => 'in_stock',
                                'location'          => $destLocation,
                                'purchase_order_id' => $purchase->id,
                                'user_id'           => Auth::id(),
                            ]);
                        }
                    }
                    
                    // Update item stock natively into chosen location (back_store by default)
                    InventoryService::restock(
                        $poItem->item_id,
                        $qtyToReceive,
                        $purchase->po_number,
                        Auth::user(),
                        'purchase',
                        $destLocation
                    );
                    
                    // Update base item buy_price based on new cost
                    Item::where('id', $poItem->item_id)->update(['buy_price' => $poItem->cost]);
                    $itemsReceived++;
                }

                if ($poItem->fresh()->received_qty < $poItem->qty) {
                    $allReceived = false;
                }
            }

            if ($itemsReceived > 0) {
                $purchase->update(['status' => $allReceived ? 'Received' : 'Partial']);
            }
        });

        return redirect()->route('pos.purchases.show', $purchase)->with('success', 'Order reception processed.');
    }

    public function destroy($branchParam, PurchaseOrder $purchase)
    {
        if ($purchase->status !== 'Pending') {
            return back()->withErrors(['order' => 'Only pending orders can be deleted.']);
        }
        $purchase->delete();
        return redirect()->route('pos.purchases.index')->with('success', 'Purchase order deleted.');
    }
}
