<?php

namespace App\Modules\CommerceIntegration\Services;

use App\Models\Item;
use App\Modules\CommerceIntegration\Models\CommerceReservationItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryAvailabilityService
{
    /**
     * Get the available quantity for a single item in a branch.
     * Available quantity = front_store_qty - active_reserved_qty
     * Note: back_store_qty is NEVER exposed to ecommerce.
     */
    public function getAvailableStock(Item|int $item, int $branchId): int
    {
        $itemId = $item instanceof Item ? $item->id : $item;
        $frontStoreQty = $item instanceof Item ? $item->front_store_qty : (int) DB::table('items')
            ->where('id', $itemId)
            ->where('branch_id', $branchId)
            ->value('front_store_qty') ?? 0;

        $activeReservedQty = $this->getActiveReservedQuantity($itemId, $branchId);

        return max(0, $frontStoreQty - $activeReservedQty);
    }

    /**
     * Get the currently active reserved quantity for an item.
     */
    public function getActiveReservedQuantity(int $itemId, int $branchId): int
    {
        return (int) DB::table('commerce_reservation_items')
            ->join('commerce_reservations', 'commerce_reservation_items.commerce_reservation_id', '=', 'commerce_reservations.id')
            ->where('commerce_reservations.branch_id', $branchId)
            ->where('commerce_reservations.status', 'pending')
            ->where('commerce_reservations.expires_at', '>', now())
            ->where('commerce_reservation_items.item_id', $itemId)
            ->sum('commerce_reservation_items.quantity');
    }

    /**
     * Get availability details for multiple items in a branch in one optimized query.
     *
     * @param array<int> $itemIds
     * @param int $branchId
     * @return Collection<int, array{item_id: int, front_store_qty: int, reserved_qty: int, available_qty: int}>
     */
    public function getBatchAvailableStock(array $itemIds, int $branchId): Collection
    {
        if (empty($itemIds)) {
            return collect();
        }

        // Fetch items front store qty
        $items = DB::table('items')
            ->where('branch_id', $branchId)
            ->whereIn('id', $itemIds)
            ->pluck('front_store_qty', 'id');

        // Fetch active reservations aggregated by item
        $reservations = DB::table('commerce_reservation_items')
            ->join('commerce_reservations', 'commerce_reservation_items.commerce_reservation_id', '=', 'commerce_reservations.id')
            ->where('commerce_reservations.branch_id', $branchId)
            ->where('commerce_reservations.status', 'pending')
            ->where('commerce_reservations.expires_at', '>', now())
            ->whereIn('commerce_reservation_items.item_id', $itemIds)
            ->groupBy('commerce_reservation_items.item_id')
            ->select('commerce_reservation_items.item_id', DB::raw('SUM(commerce_reservation_items.quantity) as total_reserved'))
            ->pluck('total_reserved', 'item_id');

        $result = collect();

        foreach ($items as $id => $frontQty) {
            $reserved = (int) ($reservations[$id] ?? 0);
            $available = max(0, (int) $frontQty - $reserved);

            $result->put($id, [
                'item_id' => (int) $id,
                'front_store_qty' => (int) $frontQty,
                'reserved_qty' => $reserved,
                'available_qty' => $available,
            ]);
        }

        return $result;
    }
}
