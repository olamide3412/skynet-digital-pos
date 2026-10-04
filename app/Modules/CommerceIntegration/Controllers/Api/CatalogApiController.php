<?php

namespace App\Modules\CommerceIntegration\Controllers\Api;

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
     * Retrieve paginated catalog with real-time stock availability.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $filters = [
            'page' => $request->integer('page', 1),
            'per_page' => $request->integer('per_page', 50),
            'updated_after' => $request->query('updated_after'),
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
            'data' => $catalog['data'],
            'pagination' => $pagination,
            'meta' => $pagination,
            'next_page_url' => $nextPageUrl,
        ]);
    }

    /**
     * Get real-time stock for a single item.
     */
    public function stock(Request $request, int $itemId): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $item = Item::where('id', $itemId)
            ->where('branch_id', $integration->branch_id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => "Item ID {$itemId} not found in this branch.",
                'error_code' => 'ITEM_NOT_FOUND',
            ], 404);
        }

        $activeReserved = $this->availabilityService->getActiveReservedQuantity($item->id, $integration->branch_id);
        $available = max(0, (int) $item->front_store_qty - $activeReserved);

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $item->id,
                'name' => $item->name,
                'front_store_qty' => (int) $item->front_store_qty,
                'reserved_qty' => $activeReserved,
                'available_qty' => $available,
            ],
        ]);
    }

    /**
     * Batch check stock for multiple items.
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

        return response()->json([
            'success' => true,
            'data' => $stockMap->values(),
        ]);
    }

    /**
     * Authenticated ping/identity endpoint for connection verification.
     */
    public function ping(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        return response()->json([
            'success' => true,
            'message' => 'POS connection verified successfully.',
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
