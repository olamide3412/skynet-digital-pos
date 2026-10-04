<?php

namespace App\Modules\CommerceIntegration\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use App\Modules\CommerceIntegration\Services\MappingRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MappingApiController extends Controller
{
    public function __construct(
        protected MappingRegistryService $mappingService
    ) {
    }

    /**
     * List current product mappings for this integration.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $mappings = CommerceItemMapping::where('commerce_integration_id', $integration->id)
            ->with(['item'])
            ->paginate($request->integer('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $mappings->items(),
            'pagination' => [
                'current_page' => $mappings->currentPage(),
                'per_page' => $mappings->perPage(),
                'total' => $mappings->total(),
                'last_page' => $mappings->lastPage(),
            ],
        ]);
    }

    /**
     * Create or update a single product mapping.
     */
    public function upsert(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $validated = $request->validate([
            'item_id' => 'required|integer',
            'external_product_id' => 'required|string|max:191',
            'external_variant_id' => 'nullable|string|max:191',
            'external_sku' => 'nullable|string|max:191',
            'metadata' => 'nullable|array',
        ]);

        try {
            $mapping = $this->mappingService->upsertMapping(
                integration: $integration,
                itemId: $validated['item_id'],
                externalProductId: $validated['external_product_id'],
                externalVariantId: $validated['external_variant_id'] ?? null,
                externalSku: $validated['external_sku'] ?? null,
                metadata: $validated['metadata'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Mapping saved successfully.',
                'data' => $mapping,
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch upsert multiple mappings.
     */
    public function batchUpsert(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $rawMappings = $request->input('mappings', []);
        $normalized = [];

        foreach ($rawMappings as $m) {
            $normalized[] = [
                'item_id' => $m['item_id'] ?? $m['pos_item_id'] ?? null,
                'external_product_id' => (string) ($m['external_product_id'] ?? $m['product_id'] ?? ''),
                'external_variant_id' => isset($m['external_variant_id']) ? (string) $m['external_variant_id'] : (isset($m['variant_id']) ? (string) $m['variant_id'] : null),
                'external_sku' => $m['external_sku'] ?? $m['sku'] ?? null,
                'metadata' => $m['metadata'] ?? null,
            ];
        }

        $validator = validator(['mappings' => $normalized], [
            'mappings' => 'required|array|min:1|max:500',
            'mappings.*.item_id' => 'required|integer',
            'mappings.*.external_product_id' => 'required|string',
            'mappings.*.external_variant_id' => 'nullable|string',
            'mappings.*.external_sku' => 'nullable|string',
            'mappings.*.metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->mappingService->batchUpsertMappings($integration, $validator->validated()['mappings']);

        return response()->json([
            'success' => true,
            'message' => "Successfully synced {$result['synced']} mappings.",
            'data' => $result,
        ]);
    }

    /**
     * Batch delete/unregister mappings.
     */
    public function batchUnregister(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $itemIds = $request->input('pos_item_ids', $request->input('item_ids', []));

        $count = 0;
        foreach ($itemIds as $id) {
            try {
                $this->mappingService->deleteMapping($integration, (int) $id);
                $count++;
            } catch (\Throwable) {
                // Skip if not found
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully unregistered {$count} mappings.",
        ]);
    }

    /**
     * Delete an existing item mapping.
     */
    public function destroy(Request $request, int $itemId): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        try {
            $this->mappingService->deleteMapping($integration, $itemId);

            return response()->json([
                'success' => true,
                'message' => 'Mapping removed successfully.',
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        }
    }
}
