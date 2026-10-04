<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Models\Item;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceItemMapping;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class MappingRegistryService
{
    /**
     * Upsert a single item mapping.
     */
    public function upsertMapping(
        CommerceIntegration $integration,
        int $itemId,
        string $externalProductId,
        ?string $externalVariantId = null,
        ?string $externalSku = null,
        ?array $metadata = null
    ): CommerceItemMapping {
        // Validate item belongs to integration's branch
        $item = Item::where('id', $itemId)
            ->where('branch_id', $integration->branch_id)
            ->first();

        if (!$item) {
            throw new UnprocessableEntityHttpException("Item ID {$itemId} does not exist in branch {$integration->branch_id}.");
        }

        $existing = CommerceItemMapping::where('commerce_integration_id', $integration->id)
            ->where('item_id', $itemId)
            ->first();

        return CommerceItemMapping::updateOrCreate(
            [
                'commerce_integration_id' => $integration->id,
                'item_id' => $itemId,
            ],
            [
                'branch_id' => $integration->branch_id,
                'external_product_id' => $externalProductId,
                'external_variant_id' => $externalVariantId,
                'external_sku' => $externalSku,
                'is_active' => true,
                'registered_at' => $existing?->registered_at ?? now(),
                'last_seen_at' => now(),
                'unregistered_at' => null,
                'sync_status' => CommerceItemMapping::STATUS_SYNCED,
                'last_synced_at' => now(),
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * Manually deactivate a stale item mapping from the POS side.
     */
    public function deactivateMapping(int $mappingId, ?int $branchId = null): CommerceItemMapping
    {
        $query = CommerceItemMapping::where('id', $mappingId);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }
        $mapping = $query->first();

        if (!$mapping) {
            throw new NotFoundHttpException("Mapping #{$mappingId} not found.");
        }

        $mapping->update([
            'is_active' => false,
            'unregistered_at' => now(),
        ]);

        return $mapping;
    }

    /**
     * Batch upsert multiple mappings in a single transaction.
     *
     * @param CommerceIntegration $integration
     * @param array<array{item_id: int, external_product_id: string, external_variant_id?: string|null, external_sku?: string|null, metadata?: array|null}> $mappingsData
     * @return array{synced: int, errors: array}
     */
    public function batchUpsertMappings(CommerceIntegration $integration, array $mappingsData): array
    {
        return DB::transaction(function () use ($integration, $mappingsData) {
            $syncedCount = 0;
            $errors = [];

            foreach ($mappingsData as $index => $row) {
                try {
                    $this->upsertMapping(
                        $integration,
                        (int) $row['item_id'],
                        (string) $row['external_product_id'],
                        $row['external_variant_id'] ?? null,
                        $row['external_sku'] ?? null,
                        $row['metadata'] ?? null
                    );
                    $syncedCount++;
                } catch (\Throwable $e) {
                    $errors[] = [
                        'index' => $index,
                        'item_id' => $row['item_id'] ?? null,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return [
                'synced' => $syncedCount,
                'errors' => $errors,
            ];
        });
    }

    /**
     * Delete an item mapping.
     */
    public function deleteMapping(CommerceIntegration $integration, int $itemId): bool
    {
        $mapping = CommerceItemMapping::where('commerce_integration_id', $integration->id)
            ->where('item_id', $itemId)
            ->first();

        if (!$mapping) {
            throw new NotFoundHttpException("Mapping for item ID {$itemId} not found.");
        }

        return (bool) $mapping->delete();
    }

    /**
     * Find POS item by external product / variant ID.
     */
    public function findItemByExternal(
        CommerceIntegration $integration,
        string $externalProductId,
        ?string $externalVariantId = null
    ): ?Item {
        $query = CommerceItemMapping::where('commerce_integration_id', $integration->id)
            ->where('external_product_id', $externalProductId);

        if ($externalVariantId !== null) {
            $query->where('external_variant_id', $externalVariantId);
        }

        $mapping = $query->first();

        return $mapping?->item;
    }
}
