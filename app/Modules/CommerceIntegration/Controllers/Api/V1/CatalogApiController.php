<?php

namespace App\Modules\CommerceIntegration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Services\CommerceCatalogService;
use App\Modules\CommerceIntegration\Services\InventoryAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogApiController extends Controller
{
    public function __construct(
        protected CommerceCatalogService $catalogService,
        protected InventoryAvailabilityService $availabilityService
    ) {
    }

    /**
     * Retrieve paginated catalog with real-time stock availability (V1).
     */
    public function index(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $filters = [
            'page' => $request->integer('page', 1),
            'per_page' => $request->integer('per_page', 50),
            'updated_after' => $request->query('updated_after') ?? $request->query('since'),
            'search' => $request->query('search'),
            'in_stock_only' => $request->boolean('in_stock_only'),
        ];

        $catalog = $this->catalogService->getCatalog($integration, $filters);
        $pagination = $catalog['pagination'];
        $nextPageUrl = ($pagination['current_page'] < $pagination['last_page'])
            ? $request->fullUrlWithQuery(['page' => $pagination['current_page'] + 1])
            : null;

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $catalog['data'],
            'pagination' => $pagination,
            'meta' => $pagination,
            'current_page' => $pagination['current_page'],
            'last_page' => $pagination['last_page'],
            'per_page' => $pagination['per_page'],
            'total' => $pagination['total'],
            'next_page_url' => $nextPageUrl,
        ]);
    }

    /**
     * Get real-time stock for a single item (V1).
     */
    public function stock(Request $request, int $itemId): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $item = Item::where('id', $itemId)
            ->where('branch_id', $integration->branch_id)
            ->with(['units'])
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => "Item ID {$itemId} not found in this branch.",
                'error_code' => 'ITEM_NOT_FOUND',
            ], 404);
        }

        $activeReserved = $this->availabilityService->getActiveReservedQuantity($item->id, $integration->branch_id);
        $available = max(0, (int) $item->front_store_qty - $activeReserved);

        return response()->json([
            'success' => true,
            'status' => true,
            'pos_item_id' => $item->id,
            'available_quantity' => $available,
            'data' => [
                'id' => $item->id,
                'item_id' => $item->id,
                'pos_item_id' => $item->id,
                'name' => $item->name,
                'pos_item_name' => $item->name,
                'barcode' => $item->barcode,
                'sku' => $item->barcode ?: ('POS-' . $item->id),
                'price' => (float) $item->price,
                'standard_price' => (float) $item->price,
                'selling_price' => (float) $item->price,
                'front_store_qty' => (int) $item->front_store_qty,
                'reserved_qty' => $activeReserved,
                'available_qty' => $available,
                'available_quantity' => $available,
                'units' => $item->units->map(fn ($u) => [
                    'id' => $u->id,
                    'pos_item_unit_id' => $u->id,
                    'unit_name' => $u->unit_name,
                    'multiplier' => (float) $u->quantity_in_base_units,
                    'quantity_in_base_units' => (float) $u->quantity_in_base_units,
                    'price' => (float) $u->selling_price,
                    'selling_price' => (float) $u->selling_price,
                    'standard_price' => (float) $u->selling_price,
                    'barcode' => $u->barcode ?? null,
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Batch check stock for multiple items (V1).
     */
    public function batchStock(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer',
        ]);

        $itemIds = $request->input('item_ids', []);
        $stockMap = $this->availabilityService->getBatchAvailableStock($itemIds, $integration->branch_id);

        $normalized = $stockMap->map(fn ($entry) => [
            'item_id' => $entry['item_id'],
            'pos_item_id' => $entry['item_id'],
            'front_store_qty' => $entry['front_store_qty'],
            'reserved_qty' => $entry['reserved_qty'],
            'available_qty' => $entry['available_qty'],
            'available_quantity' => $entry['available_qty'],
            'effective_availability' => $entry['available_qty'],
        ])->values();

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $normalized,
        ]);
    }

    /**
     * Authenticated ping/identity endpoint for connection verification (V1).
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'POS connection verified successfully.',
            'branch_id' => $integration->branch_id,
            'branch_name' => $integration->branch?->name,
            'version' => '1.0.0',
            'data' => [
                'status' => 'connected',
                'branch_id' => $integration->branch_id,
                'branch_name' => $integration->branch?->name,
                'integration_id' => $integration->id,
                'client_id' => $integration->client_id,
                'platform' => $integration->platform,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
