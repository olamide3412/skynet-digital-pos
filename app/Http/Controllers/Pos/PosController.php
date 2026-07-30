<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemGrid;
use App\Models\PosSettings;
use App\Models\PosCustomer;
use App\Models\SaleDiscount;
use App\Models\HeldSale;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PosController extends Controller
{
    public function index()
    {
        $settings     = PosSettings::current();
        $user         = Auth::user();
        $activeDiscount = SaleDiscount::where('is_apply', true)
            ->where('start_date_time', '<=', now())
            ->where('end_date_time',   '>=', now())
            ->first();

        // Grid items for gallery mode
        $itemGrids = ItemGrid::with('item.category')->get();

        // Held sales for current user
        $heldSales = HeldSale::with(['items.item', 'customer'])
            ->where('user_id', $user->id)
            ->where('status', 'Held')
            ->latest()
            ->get();

        return Inertia::render('Pos/Index', [
            'settings'       => $settings,
            'itemGrids'      => $itemGrids,
            'heldSales'      => $heldSales,
            'activeDiscount' => $activeDiscount,
            'canEditPrice'   => \App\Services\RoleService::canEditPrice(),
            'canApplyDiscount' => \App\Services\RoleService::canApplyDiscount(),
            'now'            => now()->toISOString(),
        ]);
    }
}
