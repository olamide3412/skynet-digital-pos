<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Process a sale return: validates quantities, records return items,
     * restocks inventory, and logs the action.
     *
     * @param  int    $saleId
     * @param  array  $items  Each: ['item_id', 'qty', 'reason']
     * @param  User   $user
     * @return void
     */
    public static function process(int $saleId, array $items, User $user): void
    {
        $sale = Sale::with('saleOrders')->findOrFail($saleId);

        // Map original ordered quantities by item_id
        $originalQtys = $sale->saleOrders->keyBy('item_id');

        DB::transaction(function () use ($sale, $items, $originalQtys, $user) {
            foreach ($items as $returnItem) {
                $itemId = $returnItem['item_id'];
                $qty    = (int) $returnItem['qty'];
                $reason = $returnItem['reason'] ?? 'No reason provided';

                $orderLine = $originalQtys->get($itemId);
                if (!$orderLine) {
                    throw new \InvalidArgumentException("Item {$itemId} was not part of sale #{$sale->receipt_id}.");
                }

                if ($qty > $orderLine->qty) {
                    throw new \InvalidArgumentException(
                        "Return qty ({$qty}) exceeds original ordered qty ({$orderLine->qty}) for item {$itemId}."
                    );
                }

                $item          = Item::findOrFail($itemId);
                $refundAmount  = $qty * $orderLine->selling_price;

                // Record the return
                SaleReturnItem::create([
                    'sale_id'       => $sale->id,
                    'item_id'       => $itemId,
                    'item_name'     => $orderLine->item_name,
                    'qty'           => $qty,
                    'price'         => $orderLine->selling_price,
                    'total_price'   => $refundAmount,
                    'purchase_type' => $orderLine->purchase_type,
                    'return_reason' => $reason,
                    'refund_amount' => $refundAmount,
                    'user_id'       => $user->id,
                ]);

                // Restock via InventoryService
                InventoryService::restock(
                    $itemId,
                    $qty,
                    $sale->receipt_id,
                    $user,
                    'return'
                );
            }
        });
    }
}
