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
     * restocks inventory in base units, and logs the action.
     * Supports both receipt-based returns and direct returns (without receipt).
     *
     * @param  int|null $saleId
     * @param  array    $items  Each: ['item_id', 'qty', 'unit_used', 'reason']
     * @param  User     $user
     * @return void
     */
    public static function process(?int $saleId, array $items, User $user): void
    {
        $sale = $saleId ? Sale::with('saleOrders.item')->find($saleId) : null;
        $originalOrders = $sale ? $sale->saleOrders->keyBy('item_id') : collect();

        DB::transaction(function () use ($sale, $items, $originalOrders, $user) {
            foreach ($items as $returnItem) {
                $itemId   = $returnItem['item_id'];
                $qty      = (int) $returnItem['qty'];
                $unitUsed = strtolower($returnItem['unit_used'] ?? 'unit');
                $reason   = $returnItem['reason'] ?? 'No reason provided';

                if ($qty <= 0) continue;

                $item    = Item::findOrFail($itemId);
                $baseQty = $item->toBaseUnits($qty, $unitUsed);

                if ($sale) {
                    $orderLine = $originalOrders->get($itemId);
                    if (!$orderLine) {
                        throw new \InvalidArgumentException("Item '{$item->item_name}' was not part of sale #{$sale->receipt_id}.");
                    }

                    // Total base units purchased in this order line
                    $orderLineBaseQty = $item->toBaseUnits($orderLine->qty, $orderLine->unit_used ?? 'unit');
                    if ($orderLineBaseQty <= 0) {
                        $orderLineBaseQty = max(1, $orderLine->qty);
                    }

                    if ($baseQty > $orderLineBaseQty) {
                        $maxInSelectedUnit = match($unitUsed) {
                            'carton' => intdiv($orderLineBaseQty, ($item->packs_per_carton ?: 1) * ($item->units_per_pack ?: 1)),
                            'pack'   => intdiv($orderLineBaseQty, ($item->units_per_pack ?: 1)),
                            default  => $orderLineBaseQty,
                        };
                        $unitLabel = match($unitUsed) {
                            'carton' => $item->carton_label ?: 'Carton',
                            'pack'   => $item->pack_label ?: 'Pack',
                            default  => $item->unit_label ?: 'Unit',
                        };
                        throw new \InvalidArgumentException(
                            "Return quantity ({$qty} {$unitLabel}) exceeds maximum purchasable return amount ({$maxInSelectedUnit} {$unitLabel}) for '{$item->item_name}'."
                        );
                    }

                    // Calculate proportional refund amount based on base unit price
                    $pricePerBaseUnit = (float) ($orderLine->total_selling_price / $orderLineBaseQty);
                    $refundAmount     = $baseQty * $pricePerBaseUnit;
                    $unitPrice        = $qty > 0 ? ($refundAmount / $qty) : $orderLine->selling_price;

                    $purchaseType = $orderLine->purchase_type ?? 'Consumer';
                    $receiptRef   = $sale->receipt_id;
                } else {
                    $unitPrice    = $item->getPriceForUnitLevel($unitUsed, 'Consumer');
                    $refundAmount = $qty * $unitPrice;
                    $purchaseType = 'Consumer';
                    $receiptRef   = 'DIRECT-RETURN';
                }

                // Record the return
                SaleReturnItem::create([
                    'sale_id'       => $sale?->id,
                    'item_id'       => $itemId,
                    'item_name'     => $item->item_name,
                    'qty'           => $qty,
                    'unit_used'     => $unitUsed,
                    'price'         => $unitPrice,
                    'total_price'   => $refundAmount,
                    'purchase_type' => $purchaseType,
                    'return_reason' => $reason,
                    'refund_amount' => $refundAmount,
                    'user_id'       => $user->id,
                ]);

                // Format friendly inventory log note
                $unitLabel = match($unitUsed) {
                    'carton' => $item->carton_label ?: 'Carton',
                    'pack'   => $item->pack_label ?: 'Pack',
                    default  => $item->unit_label ?: 'Unit',
                };
                $logNote = "Returned {$qty} {$unitLabel}(s) ({$baseQty} base units) into front store. Reason: {$reason}";

                // Restock base units into front_store
                InventoryService::restock(
                    $itemId,
                    $baseQty,
                    $receiptRef,
                    $user,
                    'return',
                    'front_store',
                    $logNote
                );
            }
        });
    }
}
