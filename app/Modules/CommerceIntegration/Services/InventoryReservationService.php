<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Models\Item;
use App\Modules\CommerceIntegration\Events\CommerceStockReleased;
use App\Modules\CommerceIntegration\Events\CommerceStockReserved;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceInventoryMovement;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use App\Modules\CommerceIntegration\Models\CommerceReservationItem;
use Exception;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InventoryReservationService
{
    public function __construct(
        protected InventoryAvailabilityService $availabilityService,
        protected InventoryMovementService $movementService
    ) {
    }

    /**
     * Reserve inventory atomically for an ecommerce order.
     *
     * @param CommerceIntegration $integration
     * @param string $externalOrderReference
     * @param array<array{item_id: int, quantity: int, unit_price?: float|null}> $items
     * @param int|null $ttlMinutes
     * @param array|null $metadata
     * @return CommerceReservation
     * @throws Exception
     */
    public function reserve(
        CommerceIntegration $integration,
        string $externalOrderReference,
        array $items,
        ?int $ttlMinutes = null,
        ?array $metadata = null
    ): CommerceReservation {
        // Idempotency check: see if reservation already exists
        $existing = CommerceReservation::where('commerce_integration_id', $integration->id)
            ->where('external_order_reference', $externalOrderReference)
            ->first();

        if ($existing) {
            if ($existing->isPending() && !$existing->isExpired()) {
                // Idempotent return of the active reservation
                return $existing->load('items.item');
            }

            if ($existing->isConfirmed()) {
                throw new ConflictHttpException("Order reference '{$externalOrderReference}' is already confirmed.");
            }

            // If existing was expired or released, we re-use or replace if appropriate, or reject
            if ($existing->isExpired() || $existing->isReleased()) {
                throw new ConflictHttpException("Order reference '{$externalOrderReference}' has previously been {$existing->status}. Please use a unique order reference.");
            }
        }

        $ttl = $ttlMinutes ?? $integration->getReservationTtlMinutes();
        $expiresAt = now()->addMinutes($ttl);

        return DB::transaction(function () use ($integration, $externalOrderReference, $items, $expiresAt, $metadata) {
            $itemIds = array_column($items, 'item_id');

            // Lock requested items for update to prevent race conditions
            $lockedItems = Item::where('branch_id', $integration->branch_id)
                ->whereIn('id', $itemIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Validate that all items exist in this integration's branch
            foreach ($itemIds as $itemId) {
                if (!$lockedItems->has($itemId)) {
                    throw new UnprocessableEntityHttpException("Item ID {$itemId} not found in branch {$integration->branch_id}.");
                }
            }

            // Check stock availability for all items under the lock
            $failures = [];
            foreach ($items as $req) {
                $itemId = $req['item_id'];
                $requestedQty = (int) $req['quantity'];
                $item = $lockedItems->get($itemId);

                if ($requestedQty <= 0) {
                    throw new UnprocessableEntityHttpException("Reservation quantity for item {$itemId} must be greater than zero.");
                }

                $availableStock = $this->availabilityService->getAvailableStock($item, $integration->branch_id);

                if ($requestedQty > $availableStock) {
                    $failures[] = [
                        'item_id' => $itemId,
                        'item_name' => $item->name,
                        'requested_qty' => $requestedQty,
                        'available_qty' => $availableStock,
                    ];
                }
            }

            if (!empty($failures)) {
                $errorMsg = 'Insufficient available stock for one or more items.';
                $exception = new UnprocessableEntityHttpException($errorMsg);
                // Attach failures for controller to format cleanly in response
                throw new InsufficientStockException($errorMsg, $failures);
            }

            // Create reservation
            $reservation = CommerceReservation::create([
                'commerce_integration_id' => $integration->id,
                'organization_id' => $integration->organization_id,
                'branch_id' => $integration->branch_id,
                'external_order_reference' => $externalOrderReference,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ]);

            // Create reservation items & audit movements
            foreach ($items as $req) {
                $itemId = $req['item_id'];
                $quantity = (int) $req['quantity'];
                $unitPrice = $req['unit_price'] ?? $lockedItems->get($itemId)->price;

                CommerceReservationItem::create([
                    'commerce_reservation_id' => $reservation->id,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ]);

                $item = $lockedItems->get($itemId);

                // Record movement audit (stock is not decremented yet, but held)
                $this->movementService->recordMovement(
                    integrationId: $integration->id,
                    organizationId: $integration->organization_id,
                    branchId: $integration->branch_id,
                    itemId: $itemId,
                    type: CommerceInventoryMovement::TYPE_RESERVATION_HOLD,
                    quantity: $quantity,
                    previousStock: $item->front_store_qty,
                    currentStock: $item->front_store_qty, // actual on-hand hasn't changed, hold placed
                    reservationId: $reservation->id,
                    externalReference: $externalOrderReference,
                    notes: "Stock hold for external order {$externalOrderReference}"
                );
            }

            CommerceStockReserved::dispatch($reservation);

            return $reservation->load('items.item');
        });
    }

    /**
     * Release an active reservation (e.g. cart abandoned, user cancelled).
     */
    public function release(
        CommerceIntegration $integration,
        string $externalOrderReference,
        string $reason = 'cancelled'
    ): CommerceReservation {
        return DB::transaction(function () use ($integration, $externalOrderReference, $reason) {
            $reservation = CommerceReservation::where('commerce_integration_id', $integration->id)
                ->where('external_order_reference', $externalOrderReference)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                throw new UnprocessableEntityHttpException("Reservation with reference '{$externalOrderReference}' not found.");
            }

            if ($reservation->isConfirmed()) {
                throw new ConflictHttpException("Cannot release already confirmed reservation '{$externalOrderReference}'.");
            }

            if ($reservation->status === 'released') {
                return $reservation->load('items.item'); // Idempotent
            }

            $reservation->update([
                'status' => 'released',
                'released_at' => now(),
            ]);

            // Record movement audit
            $reservation->load('items.item');
            foreach ($reservation->items as $resItem) {
                $this->movementService->recordMovement(
                    integrationId: $integration->id,
                    organizationId: $integration->organization_id,
                    branchId: $integration->branch_id,
                    itemId: $resItem->item_id,
                    type: CommerceInventoryMovement::TYPE_RESERVATION_RELEASE,
                    quantity: $resItem->quantity,
                    previousStock: $resItem->item->front_store_qty,
                    currentStock: $resItem->item->front_store_qty,
                    reservationId: $reservation->id,
                    externalReference: $externalOrderReference,
                    notes: "Reservation released. Reason: {$reason}"
                );
            }

            CommerceStockReleased::dispatch($reservation, $reason);

            return $reservation;
        });
    }

    /**
     * Expire all pending reservations past their expiration timestamp.
     *
     * @return int Number of expired reservations
     */
    public function expireOutdatedReservations(): int
    {
        $expiredCount = 0;

        $outdatedReservations = CommerceReservation::expiredPending()
            ->with(['items.item', 'integration'])
            ->get();

        foreach ($outdatedReservations as $reservation) {
            DB::transaction(function () use ($reservation, &$expiredCount) {
                $locked = CommerceReservation::where('id', $reservation->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$locked) {
                    return;
                }

                $locked->update([
                    'status' => 'expired',
                    'released_at' => now(),
                ]);

                foreach ($reservation->items as $resItem) {
                    $item = $resItem->item;
                    $this->movementService->recordMovement(
                        integrationId: $reservation->commerce_integration_id,
                        organizationId: $reservation->organization_id,
                        branchId: $reservation->branch_id,
                        itemId: $resItem->item_id,
                        type: CommerceInventoryMovement::TYPE_RESERVATION_RELEASE,
                        quantity: $resItem->quantity,
                        previousStock: $item ? $item->front_store_qty : 0,
                        currentStock: $item ? $item->front_store_qty : 0,
                        reservationId: $locked->id,
                        externalReference: $locked->external_order_reference,
                        notes: 'Reservation expired automatically'
                    );
                }

                CommerceStockReleased::dispatch($locked, 'expired');
                $expiredCount++;
            });
        }

        return $expiredCount;
    }
}
