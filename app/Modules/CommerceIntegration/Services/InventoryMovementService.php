<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Modules\CommerceIntegration\Models\CommerceInventoryMovement;

class InventoryMovementService
{
    /**
     * Record an inventory movement audit entry.
     */
    public function recordMovement(
        int $integrationId,
        int $organizationId,
        int $branchId,
        int $itemId,
        string $type,
        int $quantity,
        int $previousStock,
        int $currentStock,
        ?int $reservationId = null,
        ?string $externalReference = null,
        ?string $notes = null
    ): CommerceInventoryMovement {
        return CommerceInventoryMovement::create([
            'commerce_integration_id' => $integrationId,
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'item_id' => $itemId,
            'commerce_reservation_id' => $reservationId,
            'type' => $type,
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'current_stock' => $currentStock,
            'external_reference' => $externalReference,
            'notes' => $notes,
        ]);
    }
}
