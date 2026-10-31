<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Models\Item;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CommerceCatalogService
{
    public function __construct(
        protected InventoryAvailabilityService $availabilityService
    ) {
    }

    /**
     * Get paginated catalog with real-time available inventory for a commerce integration.
     *
     * @param CommerceIntegration $integration
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     updated_after?: string|null,
     *     search?: string|null,
     *     in_stock_only?: bool|null
     * } $filters
     * @return array
     */
    public function getCatalog(CommerceIntegration $integration, array $filters = []): array
    {
        $branchId = $integration->branch_id;
        $maxPerPage = (int) config('commerce.catalog.max_per_page', 200);
        $defaultPerPage = (int) config('commerce.catalog.default_per_page', 50);

        $perPage = isset($filters['per_page'])
            ? min((int) $filters['per_page'], $maxPerPage)
            : $defaultPerPage;
        $perPage = max(1, $perPage);

        $query = Item::query()
            ->where('branch_id', $branchId)
            ->with(['units', 'category:id,name,slug']);

        // Incremental sync filter
        if (!empty($filters['updated_after'])) {
            try {
                $date = Carbon::parse($filters['updated_after']);
                $query->where('updated_at', '>=', $date);
            } catch (\Throwable) {
                // If invalid date format, ignore
            }
        }

        // Search query
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function (Builder $q) use ($search) {
                $q->where('item_name', 'like', $search)
                  ->orWhere('barcode_number', 'like', $search);
            });
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->orderBy('id', 'asc')->paginate($perPage);

        // Fetch batch availability for all items on this page
        $itemIds = $paginator->getCollection()->pluck('id')->all();
        $availabilityMap = $this->availabilityService->getBatchAvailableStock($itemIds, $branchId);

        // Fetch mappings for this integration
        $mappings = CommerceItemMapping::where('commerce_integration_id', $integration->id)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        $items = $paginator->getCollection()->map(function (Item $item) use ($availabilityMap, $mappings) {
            $availability = $availabilityMap->get($item->id, [
                'front_store_qty' => (int) $item->front_store_qty,
                'reserved_qty' => 0,
                'available_qty' => max(0, (int) $item->front_store_qty),
            ]);

            $mapping = $mappings->get($item->id);

            return [
                'id' => $item->id,
                'name' => $item->name,
                'pos_item_name' => $item->name,
                'barcode' => $item->barcode,
                'item_reference' => $item->barcode ?: ('POS-' . $item->id),
                'sku' => $item->barcode ?: ('POS-' . $item->id),
                'description' => $item->description,
                'is_active' => true,
                'price' => (float) $item->price,
                'standard_price' => (float) $item->price,
                'selling_price' => (float) $item->price,
                'wholesale_price' => $item->wholesale_price ? (float) $item->wholesale_price : null,
                'category_id' => $item->category_id,
                'pos_category_id' => $item->category_id,
                'category_name' => $item->category?->name,
                'pos_category_name' => $item->category?->name,
                'pos_category_slug' => $item->category?->slug,
                'category' => $item->category ? [
                    'id' => $item->category->id,
                    'name' => $item->category->name,
                    'slug' => $item->category->slug,
                ] : null,
                'unit_label' => $item->unit_label,
                'base_unit_name' => $item->unit_label ?: 'Piece',
                'unit_name' => $item->unit_label ?: 'Piece',
                'front_store_qty' => $availability['front_store_qty'],
                'reserved_qty' => $availability['reserved_qty'],
                'available_qty' => $availability['available_qty'],
                'effective_availability' => $availability['available_qty'],
                'quantity' => $availability['available_qty'],
                'imei_tracking_enabled' => (bool) $item->is_imei_tracked,
                'units' => $item->units->map(fn ($u) => [
                    'id' => $u->id,
                    'pos_item_unit_id' => $u->id,
                    'unit_name' => $u->unit_name,
                    'quantity_in_base_units' => (float) $u->quantity_in_base_units,
                    'selling_price' => (float) $u->selling_price,
                    'standard_price' => (float) $u->selling_price,
                    'wholesale_price' => $u->wholesale_price ? (float) $u->wholesale_price : null,
                ])->values()->all(),
                'mapping' => $mapping ? [
                    'external_product_id' => $mapping->external_product_id,
                    'external_variant_id' => $mapping->external_variant_id,
                    'external_sku' => $mapping->external_sku,
                    'sync_status' => $mapping->sync_status,
                    'last_synced_at' => $mapping->last_synced_at?->toIso8601String(),
                ] : null,
                'created_at' => $item->created_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
            ];
        });

        // Filter in_stock_only if requested
        if (!empty($filters['in_stock_only'])) {
            $items = $items->filter(fn ($i) => $i['available_qty'] > 0)->values();
        }

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
