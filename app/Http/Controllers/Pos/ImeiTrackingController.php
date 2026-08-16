<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImeiTrackingController extends Controller
{
    public function index(Request $request)
    {
        $branch   = current_branch();
        $settings = PosSettings::current();

        $query = ItemDeviceUnit::where('branch_id', $branch->id)
            ->with([
                'item:id,item_name,barcode_number,price,buy_price',
                'sale:id,receipt_id,customer_id,user_id,final_total,created_at',
                'sale.customer:id,name,phone',
                'sale.user:id,name',
                'purchaseOrder:id,po_number,vendor_id',
                'purchaseOrder.vendor:id,name',
                'user:id,name',
            ]);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('imei_or_device_id', 'like', "%{$search}%")
                  ->orWhereHas('item', function ($iq) use ($search) {
                      $iq->where('item_name', 'like', "%{$search}%")
                         ->orWhere('barcode_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('sale', function ($sq) use ($search) {
                      $sq->where('receipt_id', 'like', "%{$search}%");
                  })
                  ->orWhereHas('sale.customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($location = $request->input('location')) {
            $query->where('location', $location);
        }

        if ($itemId = $request->input('item_id')) {
            $query->where('item_id', $itemId);
        }

        $units = $query->latest()->paginate(20)->withQueryString();

        // High-level statistics
        $stats = [
            'total'          => ItemDeviceUnit::where('branch_id', $branch->id)->count(),
            'in_stock_front' => ItemDeviceUnit::where('branch_id', $branch->id)->where('status', 'in_stock')->where('location', 'front_store')->count(),
            'in_stock_back'  => ItemDeviceUnit::where('branch_id', $branch->id)->where('status', 'in_stock')->where('location', 'back_store')->count(),
            'sold'           => ItemDeviceUnit::where('branch_id', $branch->id)->where('status', 'sold')->count(),
        ];

        // Tracked items list for filter dropdown
        $trackedItems = Item::where('branch_id', $branch->id)
            ->where('is_imei_tracked', true)
            ->orderBy('item_name')
            ->get(['id', 'item_name']);

        return Inertia::render('Items/Imeis', [
            'units'        => $units,
            'filters'      => $request->only('search', 'status', 'location', 'item_id'),
            'stats'        => $stats,
            'trackedItems' => $trackedItems,
            'settings'     => $settings,
        ]);
    }
}
