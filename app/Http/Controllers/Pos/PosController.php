<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemGrid;
use App\Models\PosSettings;
use App\Models\PosCustomer;
use App\Models\SaleDiscount;
use App\Models\HeldSale;
use App\Services\QuickAccessService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index()
    {
        $branch       = current_branch();
        $settings     = PosSettings::current();
        $user         = Auth::user();
        $activeDiscount = $branch ? SaleDiscount::where('branch_id', $branch->id)
            ->where('is_apply', true)
            ->where('start_date_time', '<=', now())
            ->where('end_date_time',   '>=', now())
            ->first() : null;

        // Quick Access Widget data (capped at 10 items)
        $quickAccess = $branch ? QuickAccessService::getQuickAccessData($branch) : [
            'mode'       => 'none',
            'title'      => '',
            'subtitle'   => '',
            'items'      => [],
            'categories' => [],
        ];

        // Grid items for gallery mode (strictly scoped to this branch)
        $itemGrids = $branch ? ItemGrid::whereHas('item', fn($q) => $q->where('branch_id', $branch->id))
            ->with(['item' => function ($query) use ($branch) {
                $query->where('branch_id', $branch->id)
                      ->with(['category', 'inStockDeviceUnits' => function ($dq) use ($branch) {
                          $dq->where('branch_id', $branch->id)->select('id', 'item_id', 'imei_or_device_id', 'location', 'status');
                      }]);
            }])
            ->orderBy('menu_index')
            ->get() : collect();

        // Held sales respecting branch visibility setting
        $isShared = ($settings?->held_sales_visibility === 'shared_across_branch');
        $heldQuery = HeldSale::with(['items.item', 'customer'])->where('status', 'Held');
        if ($branch) {
            $heldQuery->where('branch_id', $branch->id);
            if (!$isShared && $user) {
                $heldQuery->where('user_id', $user->id);
            }
        } elseif ($user) {
            $heldQuery->where('user_id', $user->id);
        }

        $heldSales = $heldQuery->latest()->get()->map(function ($held) {
            return [
                'id'             => $held->id,
                'held_sale_id'   => $held->held_sale_uuid ?: (string) $held->id,
                'held_sale_uuid' => $held->held_sale_uuid,
                'hold_name'      => $held->hold_name,
                'customer_id'    => $held->customer_id,
                'customer'       => $held->customer ? [
                    'id'    => $held->customer->id,
                    'name'  => $held->customer->name,
                    'phone' => $held->customer->phone,
                ] : null,
                'created_at'     => $held->created_at?->toIso8601String(),
                'status'         => $held->status,
                'items'          => $held->items->map(function ($item) {
                    return [
                        'id'                => $item->id,
                        'item_id'           => $item->item_id,
                        'item_name'         => $item->item_name ?: $item->item?->item_name,
                        'qty'               => (int) $item->qty,
                        'price'             => (float) $item->price,
                        'unit_price'        => (float) $item->price,
                        'unit_used'         => $item->unit_used ?: 'unit',
                        'purchase_type'     => $item->purchase_type ?: 'Consumer',
                        'imei_or_device_id' => $item->imei_or_device_id,
                        'item'              => $item->item,
                    ];
                })->values()->all(),
            ];
        });

        // Merge branch business details into settings for receipt/UI use
        $settingsData = $settings->toArray();
        if ($branch) {
            $settingsData['business_name']           = $branch->name;
            $settingsData['business_address']        = $branch->address;
            $settingsData['business_contact_number'] = $branch->phone;
            $settingsData['business_email']          = $branch->email;
        }

        return Inertia::render('Pos/Index', [
            'settings'         => $settingsData,
            'quickAccess'      => $quickAccess,
            'itemGrids'        => $itemGrids,
            'heldSales'        => $heldSales,
            'activeDiscount'   => $activeDiscount,
            'canEditPrice'     => \App\Services\RoleService::canEditPrice(),
            'canApplyDiscount' => \App\Services\RoleService::canApplyDiscount(),
            'now'              => now()->toISOString(),
        ]);
    }
}
