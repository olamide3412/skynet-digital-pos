<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Models\Item;
use App\Modules\CommerceIntegration\Events\CommerceStockConfirmed;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceInventoryMovement;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InventoryConfirmationService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {
    }

    /**
     * Confirm an active reservation and deduct inventory from front_store_qty.
     *
     * @param CommerceIntegration $integration
     * @param string $externalOrderReference
     * @param array|null $fulfillmentDetails
     * @return CommerceReservation
     */
    public function confirm(
        CommerceIntegration $integration,
        string $externalOrderReference,
        ?array $fulfillmentDetails = null
    ): CommerceReservation {
        return DB::transaction(function () use ($integration, $externalOrderReference, $fulfillmentDetails) {
            $reservation = CommerceReservation::where('commerce_integration_id', $integration->id)
                ->where('external_order_reference', $externalOrderReference)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                throw new UnprocessableEntityHttpException("Reservation with reference '{$externalOrderReference}' not found.");
            }

            // Idempotent success if already confirmed
            if ($reservation->isConfirmed()) {
                return $reservation->load(['items.item', 'movements']);
            }

            if ($reservation->isReleased()) {
                throw new ConflictHttpException("Cannot confirm reservation '{$externalOrderReference}' because it was already released.");
            }

            if ($reservation->isExpired()) {
                // If expired but still marked pending, finalize it as expired
                if ($reservation->isPending()) {
                    $reservation->update([
                        'status' => 'expired',
                        'released_at' => now(),
                    ]);
                }
                throw new ConflictHttpException("Cannot confirm reservation '{$externalOrderReference}' because it has expired.");
            }

            // Load items
            $reservation->load('items');

            foreach ($reservation->items as $resItem) {
                $item = Item::where('id', $resItem->item_id)
                    ->where('branch_id', $integration->branch_id)
                    ->lockForUpdate()
                    ->first();

                if (!$item) {
                    throw new UnprocessableEntityHttpException("Item ID {$resItem->item_id} no longer exists in branch.");
                }

                $previousQty = (int) $item->front_store_qty;
                $deductQty = (int) $resItem->quantity;
                $newQty = max(0, $previousQty - $deductQty);

                // Update physical stock on the item
                $item->front_store_qty = $newQty;
                $item->save();

                // Record movement audit
                $this->movementService->recordMovement(
                    integrationId: $integration->id,
                    organizationId: $integration->organization_id,
                    branchId: $integration->branch_id,
                    itemId: $item->id,
                    type: CommerceInventoryMovement::TYPE_STOCK_DEDUCTION,
                    quantity: $deductQty,
                    previousStock: $previousQty,
                    currentStock: $newQty,
                    reservationId: $reservation->id,
                    externalReference: $externalOrderReference,
                    notes: "Stock deducted upon order confirmation {$externalOrderReference}"
                );
            }

            $metadata = $reservation->metadata ?? [];
            if ($fulfillmentDetails) {
                $metadata['fulfillment'] = $fulfillmentDetails;
            }

            $reservation->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'metadata' => $metadata,
            ]);

            CommerceStockConfirmed::dispatch($reservation);

            return $reservation->load(['items.item', 'movements']);
        });
    }
}
