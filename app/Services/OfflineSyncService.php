<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\StockConflict;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncService
{
    /**
     * Process an array of queued offline sales in FIFO order.
     *
     * @param array $queuedSales
     * @param Branch $branch
     * @return array
     */
    public static function syncBatch(array $queuedSales, Branch $branch): array
    {
        $results = [];

        // Sort by client creation timestamp to ensure strict FIFO processing
        usort($queuedSales, function ($a, $b) {
            $tA = strtotime($a['created_at'] ?? now()->toIso8601String());
            $tB = strtotime($b['created_at'] ?? now()->toIso8601String());
            return $tA <=> $tB;
        });

        foreach ($queuedSales as $salePayload) {
            try {
                $result = static::syncSingleSale($salePayload, $branch);
                $results[] = $result;
            } catch (\Throwable $e) {
                Log::error("Failed to sync offline sale [{$salePayload['offline_sale_id']}]: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[] = [
                    'offline_sale_id' => $salePayload['offline_sale_id'] ?? null,
                    'status'          => 'failed',
                    'error'           => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Process and reconcile a single offline sale with idempotency and conflict tracking.
     */
    public static function syncSingleSale(array $data, Branch $branch): array
    {
        $offlineSaleId = $data['offline_sale_id'] ?? null;
        if (!$offlineSaleId) {
            throw new \InvalidArgumentException("Missing required offline_sale_id for sync.");
        }

        // 1. Idempotency Check — Skip duplicate insertion if previously synced
        $existingSale = Sale::where('offline_sale_id', $offlineSaleId)->first();
        if ($existingSale) {
            return [
                'offline_sale_id' => $offlineSaleId,
                'status'          => 'synced',
                'sale_id'         => $existingSale->id,
                'receipt_id'      => $existingSale->receipt_id,
                'has_conflict'    => (bool) $existingSale->has_conflict,
                'is_duplicate'    => true,
            ];
        }

        $cartItems = $data['items'] ?? [];
        if (empty($cartItems)) {
            throw new \InvalidArgumentException("Offline sale [{$offlineSaleId}] contains no line items.");
        }

        // 2. Resolve Cashier User
        $user = null;
        if (!empty($data['cashier_id'])) {
            $user = User::where('branch_id', $branch->id)->find($data['cashier_id']);
        }
        if (!$user) {
            $user = Auth::user() ?? User::where('branch_id', $branch->id)->first();
        }

        $settings     = PosSettings::forBranch($branch->id);
        $purchaseType = $data['purchase_type'] ?? 'Consumer';
        $customerId   = $data['customer_id'] ?? null;

        // Load items mapped
        $itemIds  = array_column($cartItems, 'item_id');
        $itemsMap = Item::where('branch_id', $branch->id)->whereIn('id', $itemIds)->get()->keyBy('id');

        // 3. Compute Totals, Profits, and Taxes
        $amountCost     = 0;
        $totalCost      = 0;
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $consultFee     = (float) ($data['consultation_fee'] ?? 0);

        foreach ($cartItems as $cartItem) {
            $item = $itemsMap->get($cartItem['item_id']);
            if (!$item) continue;

            $unitUsed  = $cartItem['unit_used'] ?? 'unit';
            $price     = (float) $cartItem['price'];
            $qty       = (int) $cartItem['qty'];
            $baseQty   = $item->toBaseUnits($qty, $unitUsed);
            $buyPrice  = (float) $item->buy_price;

            $amountCost += ($price * $qty);
            $totalCost  += ($buyPrice * $baseQty);
        }

        $amountCost = round($amountCost, 2);
        $totalCost  = round($totalCost, 2);
        $profitMade = round(($amountCost + $consultFee - $discountAmount) - $totalCost, 2);

        $taxAmount     = 0;
        $taxPercentage = 0;
        if ($settings->is_tax_enabled && (float) $settings->tax_percentage > 0) {
            $taxPercentage   = (float) $settings->tax_percentage;
            $taxableSubtotal = max(0, $amountCost + $consultFee - $discountAmount);
            $taxAmount       = round(($taxableSubtotal * $taxPercentage) / 100, 2);
        }

        $finalTotal = round($amountCost + $consultFee - $discountAmount + $taxAmount, 2);

        // 4. Atomic Database Reconciliation
        return DB::transaction(function () use (
            $data, $offlineSaleId, $user, $branch, $settings, $cartItems, $itemsMap,
            $purchaseType, $customerId, $amountCost, $finalTotal, $profitMade,
            $discountAmount, $consultFee, $taxAmount, $taxPercentage
        ) {
            $hasConflict = false;
            $conflictsToRecord = [];

            // Server-assigned official receipt ID (keeps sequential integrity)
            $receiptId     = SaleService::generateReceiptId();
            $paymentMethod = $data['payment_method'] ?? 'Cash';
            $isDebt        = (bool) ($data['is_debt'] ?? false);
            $amountPaid    = round((float) ($data['amount_paid'] ?? $finalTotal), 2);
            $changeBal     = max(0, round($amountPaid - $finalTotal, 2));
            $clientCreated = !empty($data['created_at']) ? date('Y-m-d H:i:s', strtotime($data['created_at'])) : now();

            // Create Sale record
            $sale = Sale::create([
                'branch_id'         => $branch->id,
                'customer_id'       => $customerId,
                'receipt_id'        => $receiptId,
                'offline_sale_id'   => $offlineSaleId,
                'is_offline_sale'   => true,
                'synced_at'         => now(),
                'has_conflict'      => false, // Updated if conflicts occur
                'items_order_count' => count($cartItems),
                'consultation_fee'  => $consultFee,
                'payment_method'    => $paymentMethod,
                'bank_transfer'     => (float) ($data['bank_transfer'] ?? 0),
                'cash'              => (float) ($data['cash'] ?? 0),
                'amount_cost'       => $amountCost,
                'amount_paid'       => $amountPaid,
                'change_bal'        => $changeBal,
                'purchase_type'     => $purchaseType,
                'profit_made'       => $profitMade,
                'sale_discount_id'  => $data['sale_discount_id'] ?? null,
                'discount_amount'   => $discountAmount,
                'tax_amount'        => $taxAmount,
                'tax_percentage'    => $taxPercentage,
                'final_total'       => $finalTotal,
                'is_debt'           => $isDebt,
                'user_id'           => $user->id,
                'created_at'        => $clientCreated,
                'updated_at'        => now(),
            ]);

            // Process line items & check conflicts
            foreach ($cartItems as $cartItem) {
                $item = $itemsMap->get($cartItem['item_id']);
                if (!$item) continue;

                $qty      = (int) $cartItem['qty'];
                $unitUsed = $cartItem['unit_used'] ?? 'unit';
                $price    = (float) $cartItem['price'];
                $baseQty  = $item->toBaseUnits($qty, $unitUsed);
                $imei     = !empty($cartItem['imei_or_device_id']) ? trim($cartItem['imei_or_device_id']) : null;

                $saleOrder = SaleOrder::create([
                    'sale_id'             => $sale->id,
                    'item_id'             => $item->id,
                    'item_name'           => $item->item_name,
                    'imei_or_device_id'   => $imei,
                    'selling_price'       => $price,
                    'total_selling_price' => $price * $qty,
                    'qty'                 => $qty,
                    'unit_used'           => $unitUsed,
                    'purchase_type'       => $purchaseType,
                    'user_id'             => $user->id,
                    'sort_date'           => $clientCreated,
                ]);

                // ── IMEI Conflict Check & Allocation ─────────────────────────
                if ($item->is_imei_tracked && $imei) {
                    $deviceUnit = ItemDeviceUnit::where('branch_id', $branch->id)
                        ->where('item_id', $item->id)
                        ->where('imei_or_device_id', $imei)
                        ->first();

                    if (!$deviceUnit || $deviceUnit->status !== 'in_stock') {
                        // Conflict: Already sold by another cashier before sync!
                        $hasConflict = true;
                        $conflictsToRecord[] = [
                            'branch_id'             => $branch->id,
                            'sale_id'               => $sale->id,
                            'offline_sale_id'       => $offlineSaleId,
                            'item_id'               => $item->id,
                            'item_name'             => $item->item_name,
                            'conflict_type'         => 'imei_already_sold',
                            'requested_qty'         => 1,
                            'available_qty_at_sync' => 0,
                            'imei_or_device_id'     => $imei,
                            'status'                => 'pending_review',
                            'resolution_notes'      => "Device serial '{$imei}' was already marked sold by sale #{$deviceUnit?->sale_id} prior to offline sync.",
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                    } else {
                        // Mark device unit as sold
                        $deviceUnit->update([
                            'status'        => 'sold',
                            'sale_id'       => $sale->id,
                            'sale_order_id' => $saleOrder->id,
                            'sold_at'       => $clientCreated,
                        ]);
                    }
                }

                // ── Stock Shortfall Conflict Check & Deduction ───────────────
                if ($settings->is_qty_deduction) {
                    $currentStock = $item->front_store_qty;

                    if ($currentStock < $baseQty) {
                        // Conflict: Physical sale occurred but stock is insufficient
                        $hasConflict = true;
                        $conflictsToRecord[] = [
                            'branch_id'             => $branch->id,
                            'sale_id'               => $sale->id,
                            'offline_sale_id'       => $offlineSaleId,
                            'item_id'               => $item->id,
                            'item_name'             => $item->item_name,
                            'conflict_type'         => 'stock_shortfall',
                            'requested_qty'         => $baseQty,
                            'available_qty_at_sync' => max(0, $currentStock),
                            'imei_or_device_id'     => $imei,
                            'status'                => 'pending_review',
                            'resolution_notes'      => "Offline sale required {$baseQty} base units, but only {$currentStock} was available in front store at sync.",
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];

                        // Clamp front store stock to 0 rather than negative
                        $item->update(['front_store_qty' => 0]);
                    } else {
                        // Deduct stock normally
                        InventoryService::deduct($item, $baseQty, $receiptId, $user);
                    }
                }
            }

            // Insert logged conflicts if any
            if (!empty($conflictsToRecord)) {
                StockConflict::insert($conflictsToRecord);
                $sale->update(['has_conflict' => true]);
            }

            ActivityLogger::sale(
                "Synced offline sale #{$sale->receipt_id} (Client Ref: {$offlineSaleId}) - Total: " . number_format($finalTotal, 2) . ($hasConflict ? " [WITH CONFLICTS]" : ""),
                $branch->id,
                ['user_id' => $user->id, 'receipt_id' => $sale->receipt_id]
            );

            return [
                'offline_sale_id' => $offlineSaleId,
                'status'          => 'synced',
                'sale_id'         => $sale->id,
                'receipt_id'      => $sale->receipt_id,
                'has_conflict'    => $hasConflict,
                'conflicts_count' => count($conflictsToRecord),
            ];
        });
    }
}
