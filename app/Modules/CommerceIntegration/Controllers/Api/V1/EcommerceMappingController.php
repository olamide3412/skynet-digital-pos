<?php

namespace App\Modules\CommerceIntegration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EcommerceProductLink;
use App\Models\Item;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceMappingController extends Controller
{
    /**
     * Resolve the branch ID from headers, authenticated integration, user, or mapping items.
     */
    protected function resolveBranchId(Request $request, array $mappings = []): ?int
    {
        $branchId = $request->header('X-Branch-Id')
            ?: $request->attributes->get('commerce_integration')?->branch_id
            ?: auth('sanctum')->user()?->branch_id
            ?: auth()->user()?->branch_id;

        if (!$branchId && !empty($mappings)) {
            $firstItemId = $mappings[0]['pos_item_id'] ?? $mappings[0]['item_id'] ?? null;
            if ($firstItemId) {
                $branchId = Item::where('id', $firstItemId)->value('branch_id');
            }
        }

        return $branchId ? (int) $branchId : null;
    }

    /**
     * POST /api/v1/pos-integration/mappings/register
     *
     * Registers item-to-ecommerce links for deletion protection (V1).
     */
    public function register(Request $request): JsonResponse
    {
        $rawMappings = $request->input('mappings', []);
        $normalized = [];

        foreach ($rawMappings as $m) {
            $normalized[] = [
                'pos_item_id'          => $m['pos_item_id'] ?? $m['item_id'] ?? null,
                'ecommerce_product_id' => $m['ecommerce_product_id'] ?? $m['external_product_id'] ?? $m['product_id'] ?? null,
                'type'                 => $m['type'] ?? 'simple',
            ];
        }

        $validator = validator(['mappings' => $normalized], [
            'mappings'                          => 'required|array|min:1',
            'mappings.*.pos_item_id'            => 'required|integer',
            'mappings.*.ecommerce_product_id'   => 'required',
            'mappings.*.type'                   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $branchId = $this->resolveBranchId($request, $validated['mappings']);
        $registered = 0;

        $integration = $request->attributes->get('commerce_integration')
            ?: ($branchId ? CommerceIntegration::where('branch_id', $branchId)->first() : null);

        foreach ($validated['mappings'] as $mapping) {
            $itemBranchId = $branchId ?: Item::where('id', $mapping['pos_item_id'])->value('branch_id');

            EcommerceProductLink::updateOrCreate(
                [
                    'pos_item_id'          => $mapping['pos_item_id'],
                    'ecommerce_product_id' => (string) $mapping['ecommerce_product_id'],
                ],
                [
                    'branch_id'      => $itemBranchId,
                    'type'           => $mapping['type'] ?? 'simple',
                    'is_active'      => true,
                    'last_synced_at' => now(),
                ]
            );

            // Also keep CommerceItemMapping in sync when integration exists
            if ($integration) {
                CommerceItemMapping::updateOrCreate(
                    [
                        'commerce_integration_id' => $integration->id,
                        'item_id'                 => $mapping['pos_item_id'],
                    ],
                    [
                        'branch_id'           => $itemBranchId,
                        'external_product_id' => (string) $mapping['ecommerce_product_id'],
                        'external_variant_id' => ($mapping['type'] ?? '') === 'variant' ? (string) $mapping['ecommerce_product_id'] : null,
                        'is_active'           => true,
                        'sync_status'         => CommerceItemMapping::STATUS_SYNCED,
                        'registered_at'       => now(),
                        'last_seen_at'        => now(),
                        'last_synced_at'      => now(),
                    ]
                );
            }

            $registered++;
        }

        return response()->json([
            'success'    => true,
            'status'     => true,
            'message'    => "Successfully registered {$registered} mappings with POS.",
            'registered' => $registered,
        ]);
    }

    /**
     * POST /api/v1/pos-integration/mappings/unregister
     *
     * Unregisters mappings when an item is deleted or unlinked on Ecommerce (V1).
     */
    public function unregister(Request $request): JsonResponse
    {
        $rawItemIds = $request->input('pos_item_ids', $request->input('item_ids', []));

        $validator = validator(['pos_item_ids' => $rawItemIds], [
            'pos_item_ids'   => 'required|array|min:1',
            'pos_item_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $itemIds = $validator->validated()['pos_item_ids'];
        $branchId = $this->resolveBranchId($request);

        $deleted = EcommerceProductLink::whereIn('pos_item_id', $itemIds)->delete();

        // Also clean up CommerceItemMapping
        CommerceItemMapping::whereIn('item_id', $itemIds)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->delete();

        return response()->json([
            'success'      => true,
            'status'       => true,
            'message'      => "Unregistered {$deleted} item mappings.",
            'unregistered' => $deleted,
        ]);
    }

    /**
     * POST /api/v1/pos-integration/mappings/reconcile
     *
     * Reconciles all active mappings to repair any drift (V1).
     */
    public function reconcile(Request $request): JsonResponse
    {
        $rawMappings = $request->input('mappings', []);
        $normalized = [];

        foreach ($rawMappings as $m) {
            $normalized[] = [
                'pos_item_id'          => $m['pos_item_id'] ?? $m['item_id'] ?? null,
                'ecommerce_product_id' => $m['ecommerce_product_id'] ?? $m['external_product_id'] ?? $m['product_id'] ?? null,
                'type'                 => $m['type'] ?? 'simple',
            ];
        }

        $validator = validator(['mappings' => $normalized], [
            'mappings'                          => 'present|array',
            'mappings.*.pos_item_id'            => 'required|integer',
            'mappings.*.ecommerce_product_id'   => 'required',
            'mappings.*.type'                   => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $branchId = $this->resolveBranchId($request, $validated['mappings']);
        $activeItemIds = collect($validated['mappings'])->pluck('pos_item_id')->toArray();

        // Deactivate or delete links not in the active list
        EcommerceProductLink::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('pos_item_id', $activeItemIds)
            ->delete();

        CommerceItemMapping::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereNotIn('item_id', $activeItemIds)
            ->delete();

        $integration = $request->attributes->get('commerce_integration')
            ?: ($branchId ? CommerceIntegration::where('branch_id', $branchId)->first() : null);

        // Upsert active ones
        foreach ($validated['mappings'] as $mapping) {
            $itemBranchId = $branchId ?: Item::where('id', $mapping['pos_item_id'])->value('branch_id');

            EcommerceProductLink::updateOrCreate(
                [
                    'pos_item_id'          => $mapping['pos_item_id'],
                    'ecommerce_product_id' => (string) $mapping['ecommerce_product_id'],
                ],
                [
                    'branch_id'      => $itemBranchId,
                    'type'           => $mapping['type'] ?? 'simple',
                    'is_active'      => true,
                    'last_synced_at' => now(),
                ]
            );

            if ($integration) {
                CommerceItemMapping::updateOrCreate(
                    [
                        'commerce_integration_id' => $integration->id,
                        'item_id'                 => $mapping['pos_item_id'],
                    ],
                    [
                        'branch_id'           => $itemBranchId,
                        'external_product_id' => (string) $mapping['ecommerce_product_id'],
                        'external_variant_id' => ($mapping['type'] ?? '') === 'variant' ? (string) $mapping['ecommerce_product_id'] : null,
                        'is_active'           => true,
                        'sync_status'         => CommerceItemMapping::STATUS_SYNCED,
                        'registered_at'       => now(),
                        'last_seen_at'        => now(),
                        'last_synced_at'      => now(),
                    ]
                );
            }
        }

        return response()->json([
            'success'    => true,
            'status'     => true,
            'message'    => 'Mapping registry reconciled successfully.',
            'reconciled' => count($validated['mappings']),
        ]);
    }
}
