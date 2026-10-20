<?php

namespace App\Modules\CommerceIntegration\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use App\Modules\CommerceIntegration\Models\CommerceReservation;
use App\Modules\CommerceIntegration\Services\CommerceWebhookDispatcher;
use App\Modules\CommerceIntegration\Services\InsufficientStockException;
use App\Modules\CommerceIntegration\Services\InventoryConfirmationService;
use App\Modules\CommerceIntegration\Services\InventoryReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationApiController extends Controller
{
    public function __construct(
        protected InventoryReservationService $reservationService,
        protected InventoryConfirmationService $confirmationService
    ) {
    }

    /**
     * Create an atomic inventory reservation (V1).
     */
    public function reserve(Request $request): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        // Pre-normalize payload for compatibility with companion clients
        $data = $request->all();
        if (!isset($data['external_order_reference']) && isset($data['external_reference'])) {
            $data['external_order_reference'] = $data['external_reference'];
        }
        if (!isset($data['ttl_minutes']) && isset($data['expiry_minutes'])) {
            $data['ttl_minutes'] = $data['expiry_minutes'];
        }
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (!isset($item['item_id']) && isset($item['pos_item_id'])) {
                    $item['item_id'] = $item['pos_item_id'];
                }
            }
            unset($item);
        }

        $validator = validator($data, [
            'external_order_reference' => 'required|string|max:191',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'ttl_minutes' => 'nullable|integer|min:1|max:' . config('commerce.max_reservation_ttl_minutes', 120),
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            $reservation = CommerceWebhookDispatcher::withoutWebhooks(function () use ($integration, $validated) {
                return $this->reservationService->reserve(
                    integration: $integration,
                    externalOrderReference: $validated['external_order_reference'],
                    items: $validated['items'],
                    ttlMinutes: $validated['ttl_minutes'] ?? null,
                    metadata: $validated['metadata'] ?? null
                );
            });

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Inventory successfully reserved.',
                'reservation_id' => $reservation->id,
                'reference' => $reservation->external_order_reference,
                'external_order_reference' => $reservation->external_order_reference,
                'expires_at' => $reservation->expires_at->toIso8601String(),
                'data' => [
                    'reservation_id' => $reservation->id,
                    'external_order_reference' => $reservation->external_order_reference,
                    'reference' => $reservation->external_order_reference,
                    'status' => $reservation->status,
                    'expires_at' => $reservation->expires_at->toIso8601String(),
                    'items' => $reservation->items->map(fn ($i) => [
                        'item_id' => $i->item_id,
                        'pos_item_id' => $i->item_id,
                        'name' => $i->item?->name,
                        'quantity' => $i->quantity,
                        'reserved_quantity' => $i->quantity,
                        'unit_price' => (float) $i->unit_price,
                    ]),
                ],
            ], 201);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'INSUFFICIENT_STOCK',
                'failures' => $e->getFailures(),
            ], 422);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'RESERVATION_ERROR',
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * Confirm a reserved order and deduct inventory (V1).
     */
    public function confirm(Request $request, ?string $externalReference = null): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $ref = $externalReference 
            ?: $request->input('external_order_reference') 
            ?: $request->input('external_reference');

        if (!$ref) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'The external order reference is required.',
            ], 422);
        }

        try {
            $reservation = CommerceWebhookDispatcher::withoutWebhooks(function () use ($integration, $ref, $request) {
                return $this->confirmationService->confirm(
                    integration: $integration,
                    externalOrderReference: $ref,
                    fulfillmentDetails: $request->input('fulfillment')
                );
            });

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Order inventory successfully confirmed and deducted.',
                'reference' => $reservation->external_order_reference,
                'data' => [
                    'reservation_id' => $reservation->id,
                    'external_order_reference' => $reservation->external_order_reference,
                    'status' => $reservation->status,
                    'confirmed_at' => $reservation->confirmed_at?->toIso8601String(),
                    'items' => $reservation->items->map(fn ($i) => [
                        'item_id' => $i->item_id,
                        'pos_item_id' => $i->item_id,
                        'name' => $i->item?->name,
                        'quantity_deducted' => $i->quantity,
                        'current_front_store_qty' => $i->item?->front_store_qty,
                    ]),
                ],
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'CONFIRMATION_ERROR',
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * Release a pending reservation (V1).
     */
    public function release(Request $request, ?string $externalReference = null): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $ref = $externalReference 
            ?: $request->input('external_order_reference') 
            ?: $request->input('external_reference');

        if (!$ref) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'The external order reference is required.',
            ], 422);
        }

        try {
            $reservation = CommerceWebhookDispatcher::withoutWebhooks(function () use ($integration, $ref, $request) {
                return $this->reservationService->release(
                    integration: $integration,
                    externalOrderReference: $ref,
                    reason: $request->input('reason', 'cancelled')
                );
            });

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Reservation successfully released.',
                'reference' => $reservation->external_order_reference,
                'data' => [
                    'reservation_id' => $reservation->id,
                    'external_order_reference' => $reservation->external_order_reference,
                    'status' => $reservation->status,
                    'released_at' => $reservation->released_at?->toIso8601String(),
                ],
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'RELEASE_ERROR',
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage(),
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    /**
     * Check status of a reservation by external order reference (V1).
     */
    public function status(Request $request, string $externalReference): JsonResponse
    {
        /** @var CommerceIntegration $integration */
        $integration = $request->attributes->get('commerce_integration');

        $reservation = CommerceReservation::where('commerce_integration_id', $integration->id)
            ->where('external_order_reference', $externalReference)
            ->with(['items.item'])
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => "Reservation with reference '{$externalReference}' not found.",
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'reference' => $reservation->external_order_reference,
            'reservation_status' => $reservation->status,
            'expires_at' => $reservation->expires_at?->toIso8601String(),
            'data' => [
                'reservation_id' => $reservation->id,
                'external_order_reference' => $reservation->external_order_reference,
                'status' => $reservation->status,
                'is_expired' => $reservation->isExpired(),
                'expires_at' => $reservation->expires_at?->toIso8601String(),
                'confirmed_at' => $reservation->confirmed_at?->toIso8601String(),
                'released_at' => $reservation->released_at?->toIso8601String(),
                'items' => $reservation->items->map(fn ($i) => [
                    'item_id' => $i->item_id,
                    'pos_item_id' => $i->item_id,
                    'name' => $i->item?->name,
                    'quantity' => $i->quantity,
                    'unit_price' => (float) $i->unit_price,
                ]),
                'metadata' => $reservation->metadata,
            ],
        ]);
    }
}
