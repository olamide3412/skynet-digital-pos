<?php

namespace App\Services;

use App\Models\Item;
use App\Models\InventoryTransaction;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Restock an item into back_store (from purchase receipt) and log.
     */
    public static function restock(
        int $itemId,
        int $qty,
        string $ref,
        User $user,
        string $type = 'purchase',
        string $location = 'back_store'
    ): void {
        DB::transaction(function () use ($itemId, $qty, $ref, $user, $type, $location) {
            $item = Item::lockForUpdate()->findOrFail($itemId);
            $col  = $location === 'back_store' ? 'back_store_qty' : 'front_store_qty';

            $previousQty = $item->{$col};
            $newQty      = $previousQty + $qty;

            $item->increment($col, $qty);

            InventoryTransaction::create([
                'branch_id'        => $item->branch_id,
                'item_id'          => $itemId,
                'transaction_type' => $type,
                'qty'              => $qty,
                'previous_qty'     => $previousQty,
                'new_qty'          => $newQty,
                'location'         => $location,
                'reference_id'     => $ref,
                'notes'            => "Restocked via {$type} into {$location}",
                'user_id'          => $user->id,
            ]);
        });
    }

    /**
     * Deduct qty from front_store (from sale) and log.
     */
    public static function deduct(int $itemId, int $qty, string $ref, User $user): void
    {
        DB::transaction(function () use ($itemId, $qty, $ref, $user) {
            $item = Item::lockForUpdate()->findOrFail($itemId);
            $previousQty = $item->front_store_qty;
            $newQty      = max(0, $previousQty - $qty);

            $item->decrement('front_store_qty', $qty);

            InventoryTransaction::create([
                'branch_id'        => $item->branch_id,
                'item_id'          => $itemId,
                'transaction_type' => 'sale',
                'qty'              => $qty,
                'previous_qty'     => $previousQty,
                'new_qty'          => $newQty,
                'location'         => 'front_store',
                'reference_id'     => $ref,
                'notes'            => 'Deducted via sale',
                'user_id'          => $user->id,
            ]);
        });
    }

    /**
     * Manual stock adjustment on either location.
     */
    public static function adjust(
        int $itemId,
        string $adjustmentType,
        int $qty,
        string $reason,
        User $user,
        string $location = 'front_store'
    ): void {
        DB::transaction(function () use ($itemId, $adjustmentType, $qty, $reason, $user, $location) {
            $item = Item::lockForUpdate()->findOrFail($itemId);
            $col  = $location === 'back_store' ? 'back_store_qty' : 'front_store_qty';
            $previousQty = $item->{$col};

            if ($adjustmentType === 'add') {
                $item->increment($col, $qty);
                $newQty = $previousQty + $qty;
            } else {
                $item->decrement($col, $qty);
                $newQty = max(0, $previousQty - $qty);
            }

            InventoryTransaction::create([
                'branch_id'        => $item->branch_id,
                'item_id'          => $itemId,
                'transaction_type' => 'adjustment',
                'qty'              => $qty,
                'previous_qty'     => $previousQty,
                'new_qty'          => $newQty,
                'location'         => $location,
                'reference_id'     => null,
                'notes'            => $reason,
                'user_id'          => $user->id,
            ]);
        });
    }

    /**
     * Transfer stock between back_store and front_store for the given item.
     *
     * @param Item   $item
     * @param int    $qtyBaseUnits  Quantity in base units (already converted)
     * @param string $from          'back_store'|'front_store'
     * @param string $to            'back_store'|'front_store'
     * @param User   $user
     * @param string $unitUsed      Original unit entered by user (for display)
     * @param string|null $notes
     */
    public static function transferStock(
        Item $item,
        int $qtyBaseUnits,
        string $from,
        string $to,
        User $user,
        string $unitUsed = 'unit',
        ?string $notes = null
    ): StockTransfer {
        if ($from === $to) {
            throw new \InvalidArgumentException('Source and destination must be different.');
        }

        return DB::transaction(function () use ($item, $qtyBaseUnits, $from, $to, $user, $unitUsed, $notes) {
            $item->refresh()->lockForUpdate();

            $fromCol = $from === 'back_store' ? 'back_store_qty' : 'front_store_qty';
            $toCol   = $to   === 'back_store' ? 'back_store_qty' : 'front_store_qty';

            if ($item->{$fromCol} < $qtyBaseUnits) {
                throw new \InvalidArgumentException(
                    "Insufficient stock in {$from}. Available: {$item->{$fromCol}} base units."
                );
            }

            $item->decrement($fromCol, $qtyBaseUnits);
            $item->increment($toCol, $qtyBaseUnits);

            // Log transfer
            $transfer = StockTransfer::create([
                'branch_id'      => $item->branch_id,
                'item_id'        => $item->id,
                'qty_base_units' => $qtyBaseUnits,
                'unit_used'      => $unitUsed,
                'from_location'  => $from,
                'to_location'    => $to,
                'notes'          => $notes,
                'user_id'        => $user->id,
            ]);

            // Also log as inventory transactions
            InventoryTransaction::insert([
                [
                    'branch_id'        => $item->branch_id,
                    'item_id'          => $item->id,
                    'transaction_type' => 'transfer',
                    'qty'              => -$qtyBaseUnits,
                    'previous_qty'     => $item->{$fromCol} + $qtyBaseUnits,
                    'new_qty'          => $item->{$fromCol},
                    'location'         => $from,
                    'reference_id'     => "TRF-{$transfer->id}",
                    'notes'            => "Transfer out to {$to}",
                    'user_id'          => $user->id,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
                [
                    'branch_id'        => $item->branch_id,
                    'item_id'          => $item->id,
                    'transaction_type' => 'transfer',
                    'qty'              => $qtyBaseUnits,
                    'previous_qty'     => $item->{$toCol} - $qtyBaseUnits,
                    'new_qty'          => $item->{$toCol},
                    'location'         => $to,
                    'reference_id'     => "TRF-{$transfer->id}",
                    'notes'            => "Transfer in from {$from}",
                    'user_id'          => $user->id,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            ]);

            return $transfer;
        });
    }
}
