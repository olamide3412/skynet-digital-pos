<?php

namespace App\Services;

use App\Models\Item;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Restock an item (from purchase/return) and log the transaction.
     */
    public static function restock(int $itemId, int $qty, string $ref, User $user, string $type = 'purchase'): void
    {
        DB::transaction(function () use ($itemId, $qty, $ref, $user, $type) {
            $item = Item::lockForUpdate()->findOrFail($itemId);
            $previousQty = $item->qty;
            $newQty      = $previousQty + $qty;

            $item->increment('qty', $qty);

            InventoryTransaction::create([
                'item_id'          => $itemId,
                'transaction_type' => $type,
                'qty'              => $qty,
                'previous_qty'     => $previousQty,
                'new_qty'          => $newQty,
                'reference_id'     => $ref,
                'notes'            => "Restocked via {$type}",
                'user_id'          => $user->id,
            ]);
        });
    }

    /**
     * Deduct qty from an item (from sale) and log the transaction.
     */
    public static function deduct(int $itemId, int $qty, string $ref, User $user): void
    {
        DB::transaction(function () use ($itemId, $qty, $ref, $user) {
            $item = Item::lockForUpdate()->findOrFail($itemId);
            $previousQty = $item->qty;
            $newQty      = max(0, $previousQty - $qty);

            $item->decrement('qty', $qty);

            InventoryTransaction::create([
                'item_id'          => $itemId,
                'transaction_type' => 'sale',
                'qty'              => $qty,
                'previous_qty'     => $previousQty,
                'new_qty'          => $newQty,
                'reference_id'     => $ref,
                'notes'            => 'Deducted via sale',
                'user_id'          => $user->id,
            ]);
        });
    }

    /**
     * Manual adjustment (add or remove).
     */
    public static function adjust(int $itemId, string $adjustmentType, int $qty, string $reason, User $user): void
    {
        DB::transaction(function () use ($itemId, $adjustmentType, $qty, $reason, $user) {
            $item = Item::lockForUpdate()->findOrFail($itemId);
            $previousQty = $item->qty;

            if ($adjustmentType === 'add') {
                $item->increment('qty', $qty);
                $newQty = $previousQty + $qty;
            } else {
                $item->decrement('qty', $qty);
                $newQty = max(0, $previousQty - $qty);
            }

            InventoryTransaction::create([
                'item_id'          => $itemId,
                'transaction_type' => 'adjustment',
                'qty'              => $qty,
                'previous_qty'     => $previousQty,
                'new_qty'          => $newQty,
                'reference_id'     => null,
                'notes'            => $reason,
                'user_id'          => $user->id,
            ]);
        });
    }
}
