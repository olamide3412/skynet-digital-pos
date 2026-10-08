<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiscountGroup;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\ItemDiscount;
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

        // All active items for this branch with unit prices, variants and stock
        $items = Item::where('branch_id', $branch->id)
            ->with(['activeUnits'])
            ->select([
                'id', 'item_name', 'barcode_number', 'category_id',
                'price', 'wholesale_price', 'buy_price',
                'front_store_qty', 'back_store_qty',
                'unit_label',
                'is_imei_tracked', 'price_locked', 'expiry_date', 'image_path',
            ])
            ->get()
            ->map(function ($item) {
                return array_merge($item->toArray(), [
                    'image_url' => $item->image_url,
                    'units'     => $item->activeUnits->map(fn($u) => [
                        'id'                     => $u->id,
                        'unit_name'              => $u->unit_name,
                        'quantity_in_base_units' => (int) $u->quantity_in_base_units,
                        'selling_price'          => (float) $u->selling_price,
                        'wholesale_price'        => (float) $u->wholesale_price,
                        'sort_order'             => (int) $u->sort_order,
                    ])->values()->all(),
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

        // Active Overall Discount
        $activeDiscount = SaleDiscount::where('branch_id', $branch->id)
            ->where('is_apply', true)
            ->where('start_date_time', '<=', now())
            ->where('end_date_time', '>=', now())
            ->first();

        // Active & scheduled Item Discounts
        $itemDiscounts = ItemDiscount::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('ends_at', '>=', now()->subDays(2))
            ->select(['id', 'item_id', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'is_active'])
            ->get();

        // Active & scheduled Discount Groups (with assigned item IDs)
        $discountGroups = DiscountGroup::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('ends_at', '>=', now()->subDays(2))
            ->with(['items:id'])
            ->select(['id', 'name', 'discount_type', 'discount_value', 'starts_at', 'ends_at', 'is_active'])
            ->get()
            ->map(function ($g) {
                return [
                    'id'             => $g->id,
                    'name'           => $g->name,
                    'discount_type'  => $g->discount_type,
                    'discount_value' => (float) $g->discount_value,
                    'starts_at'      => $g->starts_at?->toIso8601String(),
                    'ends_at'        => $g->ends_at?->toIso8601String(),
                    'is_active'      => (bool) $g->is_active,
                    'item_ids'       => $g->items->pluck('id')->all(),
                ];
            });

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
                'is_qty_deduction'        => (bool) $settings->is_qty_deduction,
                'is_offline_qty_deduction'=> (bool) $settings->is_offline_qty_deduction,
                'held_sales_visibility'  => $settings->held_sales_visibility ?: 'device_only',
                'offline_receipt_prefix' => $settings->offline_receipt_prefix ?: 'OFF',
                'is_tax_enabled'          => (bool) $settings->is_tax_enabled,
                'tax_percentage'          => (float) $settings->tax_percentage,
                'is_imei_enabled'         => (bool) $settings->is_imei_enabled,
                'is_camera_scanner_enabled' => (bool) ($settings->is_camera_scanner_enabled ?? true),
                'is_price_editable'       => (bool) $settings->is_price_editable,
                'require_till_opening'    => (bool) ($settings->require_till_opening ?? true),
                'print_till_slip'         => (bool) ($settings->print_till_slip ?? false),
                'receipt_paper_size'      => $settings->receipt_paper_size ?: '80mm',
                'receipt_copies'          => (int) ($settings->receipt_copies ?: 1),
                'business_name'           => $branch->name,
                'business_address'        => $branch->address,
                'business_contact_number' => $branch->phone,
                'business_email'          => $branch->email,
            ],
            'items'               => $items,
            'available_imeis'     => $availableImeis,
            'customers'           => $customers,
            'categories'          => $categories,
            'activeDiscount'      => $activeDiscount,
            'item_discounts'      => $itemDiscounts,
            'discount_groups'     => $discountGroups,
            'quick_access'        => \App\Services\QuickAccessService::getQuickAccessData($branch),
            'active_till_session' => Auth::id() && ($till = \App\Services\TillSessionService::getActiveSession(Auth::id(), $branch->id)) ? [
                'id'             => $till->id,
                'branch_id'      => $till->branch_id,
                'cashier_id'     => $till->cashier_id,
                'opening_amount' => (float) $till->opening_amount,
                'opened_at'      => $till->opened_at?->toIso8601String(),
                'status'         => $till->status,
                'summary'        => $till->calculateSummary(),
            ] : null,
            'server_time'         => now()->toIso8601String(),
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
            'sales'         => 'nullable|array',
            'sale'          => 'nullable|array',
            'till_sessions' => 'nullable|array',
            'till_session'  => 'nullable|array',
        ]);

        $queuedSales = $data['sales'] ?? (isset($data['sale']) ? [$data['sale']] : []);
        $queuedTillSessions = $data['till_sessions'] ?? (isset($data['till_session']) ? [$data['till_session']] : []);

        if (empty($queuedSales) && empty($queuedTillSessions)) {
            return response()->json([
                'message'      => 'No offline records provided for sync.',
                'results'      => [],
                'till_results' => [],
            ]);
        }

        // 1. First sync any pending till sessions so subsequent sales link to them
        $tillResults = [];
        foreach ($queuedTillSessions as $sessionData) {
            try {
                $tillResults[] = OfflineSyncService::syncSingleTillSession($sessionData, $branch);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to sync offline till session: " . $e->getMessage());
                $tillResults[] = [
                    'offline_session_id' => $sessionData['offline_session_id'] ?? null,
                    'status'             => 'failed',
                    'error'              => $e->getMessage(),
                ];
            }
        }

        // 2. Then sync queued sales
        $results = [];
        if (!empty($queuedSales)) {
            $results = OfflineSyncService::syncBatch($queuedSales, $branch);
        }

        return response()->json([
            'message'      => 'Offline records synchronized successfully.',
            'count'        => count($results),
            'results'      => $results,
            'till_results' => $tillResults,
        ]);
    }
}
