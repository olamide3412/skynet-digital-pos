<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\SaleDiscount;
use App\Services\OfflineSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfflineSyncController extends Controller
{
    /**
     * API: Bootstrap and export complete branch data bundle for client IndexedDB caching.
     */
    public function bootstrap(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if (!$branch) {
            return response()->json(['error' => 'Branch not found'], 404);
        }

        $settings = PosSettings::forBranch($branch->id);

        // All active items for this branch with unit prices and stock
        $items = Item::where('branch_id', $branch->id)
            ->select([
                'id', 'item_name', 'barcode_number', 'category_id',
                'price', 'wholesale_price', 'pack_price', 'carton_price',
                'pack_wholesale_price', 'carton_wholesale_price', 'buy_price',
                'front_store_qty', 'back_store_qty',
                'unit_label', 'pack_label', 'carton_label',
                'units_per_pack', 'packs_per_carton',
                'is_imei_tracked', 'price_locked', 'expiry_date', 'image_path',
            ])
            ->get()
            ->map(function ($item) {
                return array_merge($item->toArray(), [
                    'image_url' => $item->image_url,
                ]);
            });

        // In-stock device units for IMEI-tracked items
        $availableImeis = ItemDeviceUnit::where('branch_id', $branch->id)
            ->where('status', 'in_stock')
            ->select(['id', 'item_id', 'imei_or_device_id', 'location', 'status'])
            ->get();

        // Customers list
        $customers = PosCustomer::where('branch_id', $branch->id)
            ->select(['id', 'name', 'phone', 'address', 'debt_bal'])
            ->get();

        // Categories list
        $categories = Category::where('branch_id', $branch->id)
            ->orWhereNull('branch_id')
            ->select(['id', 'name'])
            ->get();

        // Active Discount
        $activeDiscount = SaleDiscount::where('branch_id', $branch->id)
            ->where('is_apply', true)
            ->where('start_date_time', '<=', now())
            ->where('end_date_time', '>=', now())
            ->first();

        return response()->json([
            'branch' => [
                'id'       => $branch->id,
                'name'     => $branch->name,
                'slug'     => $branch->slug,
                'address'  => $branch->address,
                'phone'    => $branch->phone,
                'email'    => $branch->email,
            ],
            'settings' => [
                'is_offline_enabled'      => (bool) $settings->is_offline_enabled,
                'offline_receipt_prefix' => $settings->offline_receipt_prefix ?: 'OFF',
                'is_tax_enabled'          => (bool) $settings->is_tax_enabled,
                'tax_percentage'          => (float) $settings->tax_percentage,
                'is_imei_enabled'         => (bool) $settings->is_imei_enabled,
                'is_price_editable'       => (bool) $settings->is_price_editable,
                'receipt_paper_size'      => $settings->receipt_paper_size ?: '80mm',
                'receipt_copies'          => (int) ($settings->receipt_copies ?: 1),
                'business_name'           => $branch->name,
                'business_address'        => $branch->address,
                'business_contact_number' => $branch->phone,
                'business_email'          => $branch->email,
            ],
            'items'          => $items,
            'available_imeis'=> $availableImeis,
            'customers'      => $customers,
            'categories'     => $categories,
            'activeDiscount' => $activeDiscount,
            'server_time'    => now()->toIso8601String(),
        ]);
    }

    /**
     * API: Receive queued offline sales and sync them back to the server.
     */
    public function sync(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if (!$branch) {
            return response()->json(['error' => 'Branch not found'], 404);
        }

        $data = $request->validate([
            'sales'   => 'nullable|array',
            'sale'    => 'nullable|array',
        ]);

        $queuedSales = $data['sales'] ?? ($data['sale'] ? [$data['sale']] : []);

        if (empty($queuedSales)) {
            return response()->json([
                'message' => 'No offline sales provided for sync.',
                'results' => [],
            ]);
        }

        $results = OfflineSyncService::syncBatch($queuedSales, $branch);

        return response()->json([
            'message' => 'Offline sales synchronized successfully.',
            'count'   => count($results),
            'results' => $results,
        ]);
    }
}
