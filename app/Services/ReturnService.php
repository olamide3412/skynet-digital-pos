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

        DB::transaction(function () use ($sale, $items, $user) {
            foreach ($items as $returnItem) {
                $itemId   = $returnItem['item_id'];
                $qty      = (int) $returnItem['qty'];
                $unitUsed = strtolower($returnItem['unit_used'] ?? 'unit');
                $reason   = $returnItem['reason'] ?? 'No reason provided';

                if ($qty <= 0) continue;

                $item    = Item::with('units')->findOrFail($itemId);
                $baseQty = $item->toBaseUnits($qty, $unitUsed);

                if ($sale) {
                    // Match order line by explicit ID, or by unit match, or by item ID
                    $orderLine = null;
                    if (!empty($returnItem['sale_order_id'])) {
                        $orderLine = $sale->saleOrders->firstWhere('id', $returnItem['sale_order_id']);
                    }
                    if (!$orderLine) {
                        $orderLine = $sale->saleOrders->first(fn($ol) => $ol->item_id === $itemId && strtolower($ol->unit_used ?? 'unit') === $unitUsed)
                            ?? $sale->saleOrders->firstWhere('item_id', $itemId);
                    }

                    if (!$orderLine) {
                        throw new \InvalidArgumentException("Item '{$item->item_name}' was not part of sale #{$sale->receipt_id}.");
                    }

                    // Total base units purchased in this order line
                    $orderLineBaseQty = $item->toBaseUnits($orderLine->qty, $orderLine->unit_used ?? 'unit');
                    if ($orderLineBaseQty <= 0) {
                        $orderLineBaseQty = max(1, $orderLine->qty);
                    }

                    if ($baseQty > $orderLineBaseQty) {
                        $unitFactor = max(1, $item->toBaseUnits(1, $unitUsed));
                        $maxInSelectedUnit = intdiv($orderLineBaseQty, $unitFactor);
                        $unitLabel = ($unitUsed === 'unit') ? ($item->unit_label ?: 'Unit') : $unitUsed;
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
                $imei = $returnItem['imei_or_device_id'] ?? null;
                $returnRecord = SaleReturnItem::create([
                    'sale_id'            => $sale?->id,
                    'item_id'            => $itemId,
                    'item_name'          => $item->item_name,
                    'imei_or_device_id'  => $imei,
                    'qty'                => $qty,
                    'unit_used'          => $unitUsed,
                    'price'              => $unitPrice,
                    'total_price'        => $refundAmount,
                    'purchase_type'      => $purchaseType,
                    'return_reason'      => $reason,
                    'refund_amount'      => $refundAmount,
                    'user_id'            => $user->id,
                ]);

                // Double-entry accounting post (Rules 6 & 7: reverse revenue & restock cost)
                \App\Modules\Accounting\Services\SalePostingService::postSaleReturn($returnRecord);

                // Restore device unit status to in_stock
                if ($item->is_imei_tracked && $imei) {
                    \App\Models\ItemDeviceUnit::where('item_id', $item->id)
                        ->where('imei_or_device_id', $imei)
                        ->update([
                            'status'        => 'in_stock',
                            'location'      => 'front_store',
                            'sale_id'       => null,
                            'sale_order_id' => null,
                            'sold_at'       => null,
                        ]);
                }

                // Format friendly inventory log note
                $resolvedUnitName = ($unitUsed === 'unit' || $unitUsed === strtolower($item->unit_label ?? 'unit'))
                    ? ($item->unit_label ?: 'Unit')
                    : (optional($item->units->first(fn($u) => strtolower($u->unit_name) === $unitUsed))->unit_name ?: ucfirst($unitUsed));
                $logNote = "Returned {$qty} {$resolvedUnitName}(s) ({$baseQty} base units) into front store. Reason: {$reason}";

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
