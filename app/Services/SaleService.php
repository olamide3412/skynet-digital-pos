<?php

namespace App\Services;

use App\Models\DebtPayment;
use App\Models\Item;
use App\Models\MostSaleItem;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaleService
{
    /**
     * Generate a unique receipt ID: RC + YYYYMMDD + 4-digit sequence.
     * Lock-free and collision-safe.
     */
    public static function generateReceiptId(): string
    {
        $date     = now()->format('Ymd');
        $prefix   = 'RC' . $date;
        $maxId    = Sale::max('id') ?? 0;
        $sequence = $maxId + 1;

        do {
            $candidate = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            $exists    = Sale::where('receipt_id', $candidate)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidate;
    }

    /**
     * Process a complete sale transaction.
     *
     * @param  array  $data  Validated cart payload
     * @param  User   $user
     * @return Sale
     */
    public static function process(array $data, User $user): Sale
    {
        $branch = current_branch() ?? $user->branch;
        $resolvedBranchId = $branch?->id ?? $user->branch_id;
        $settings = $resolvedBranchId ? PosSettings::forBranch($resolvedBranchId) : PosSettings::current();

        $cartItems = $data['items'];
        $purchaseType = $data['purchase_type'] ?? 'Consumer';
        $customerId = $data['customer_id'] ?? null;

        if (empty($cartItems)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        // Pre-load items (scoped to current branch for safety)
        $itemIds = array_column($cartItems, 'item_id');
        $itemsMap = Item::whereIn('id', $itemIds)
            ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
            ->lockForUpdate()->get()->keyBy('id');

        // Validate stock & expiry (aggregating total base units per item for multi-row carts)
        $requiredBaseUnitsByItem = [];
        foreach ($cartItems as $cartItem) {
            $item = $itemsMap->get($cartItem['item_id']);
            if (!$item) {
                throw new \InvalidArgumentException("Item ID {$cartItem['item_id']} not found.");
            }

            $unitUsed = $cartItem['unit_used'] ?? 'unit';
            $baseQty = $item->toBaseUnits((int) $cartItem['qty'], $unitUsed);
            $requiredBaseUnitsByItem[$item->id] = ($requiredBaseUnitsByItem[$item->id] ?? 0) + $baseQty;

            if ($settings->is_check_expiration && $item->isExpired()) {
                throw new \InvalidArgumentException("Item '{$item->item_name}' is expired.");
            }

            if ($item->is_imei_tracked) {
                $imei = trim($cartItem['imei_or_device_id'] ?? '');
                if (empty($imei)) {
                    throw new \InvalidArgumentException("Please assign an IMEI/Serial number to '{$item->item_name}'.");
                }

                $branchId = $branch?->id ?? $item->branch_id;
                $deviceUnit = \App\Models\ItemDeviceUnit::where('branch_id', $branchId)
                    ->where('item_id', $item->id)
                    ->where('imei_or_device_id', $imei)
                    ->where('status', 'in_stock')
                    ->first();

                if (!$deviceUnit) {
                    throw new \InvalidArgumentException("Device with IMEI '{$imei}' for '{$item->item_name}' is not in stock or already sold.");
                }
            }
        }

        if ($settings->is_qty_deduction) {
            foreach ($requiredBaseUnitsByItem as $itemId => $totalRequiredBase) {
                $item = $itemsMap->get($itemId);
                if ($item && $item->front_store_qty < $totalRequiredBase) {
                    $stockFormatted = $item->formatBaseUnits($item->front_store_qty);
                    throw new \InvalidArgumentException("Insufficient stock for '{$item->item_name}'. Total required: {$totalRequiredBase}, Available: {$stockFormatted}.");
                }
            }
        }

        // Calculate totals & tax
        $amountCost = 0;
        $totalCost = 0;
        $discountAmount = (float) ($data['discount_amount'] ?? 0);
        $consultFee = (float) ($data['consultation_fee'] ?? 0);

        foreach ($cartItems as $cartItem) {
            $item = $itemsMap->get($cartItem['item_id']);
            $unitUsed = $cartItem['unit_used'] ?? 'unit';
            $price = (float) ($cartItem['price'] ?? $cartItem['unit_price'] ?? 0);
            $qty = (int) $cartItem['qty'];
            $baseQty = $item->toBaseUnits($qty, $unitUsed);
            $buyPrice = (float) $item->buy_price;

            $lineTotal = $price * $qty;
            $lineCost = $buyPrice * $baseQty;
            $amountCost += $lineTotal;
            $totalCost += $lineCost;
        }

        $amountCost = round($amountCost, 2);
        $totalCost  = round($totalCost, 2);
        $profitMade = round(($amountCost + $consultFee - $discountAmount) - $totalCost, 2);

        // Tax calculation on post-discount subtotal
        $taxAmount = 0;
        $taxPercentage = 0;
        if ($settings->is_tax_enabled && (float) $settings->tax_percentage > 0) {
            $taxPercentage = (float) $settings->tax_percentage;
            $taxableSubtotal = max(0, $amountCost + $consultFee - $discountAmount);
            $taxAmount = round(($taxableSubtotal * $taxPercentage) / 100, 2);
        }

        $finalTotal = round($amountCost + $consultFee - $discountAmount + $taxAmount, 2);

        $resolvedBranchId = $branch?->id ?? $itemsMap->first()?->branch_id;
        $activeTillSession = \App\Services\TillSessionService::getActiveSession($user->id, $resolvedBranchId);
        if ($settings->require_till_opening && !$activeTillSession && empty($data['till_session_id'])) {
            throw new \InvalidArgumentException('An active till session is required to complete sales. Please open your till first.');
        }
        $tillSessionId = $data['till_session_id'] ?? $activeTillSession?->id;

        return DB::transaction(function () use ($data, $user, $branch, $settings, $cartItems, $itemsMap, $purchaseType, $customerId, $amountCost, $finalTotal, $profitMade, $discountAmount, $consultFee, $taxAmount, $taxPercentage, $resolvedBranchId, $tillSessionId) {
            $receiptId = static::generateReceiptId();
            $paymentMethod = $data['payment_method'] ?? 'Cash';
            $isDebt = (bool) ($data['is_debt'] ?? false);
            $amountPaid = round((float) ($data['amount_paid'] ?? 0), 2);
            $changeBal = max(0, round($amountPaid - $finalTotal, 2));

            // Create Sale
            $sale = Sale::create([
                'branch_id' => $resolvedBranchId,
                'customer_id' => $customerId,
                'receipt_id' => $receiptId,
                'till_session_id' => $tillSessionId,
                'items_order_count' => count($cartItems),
                'consultation_fee' => $consultFee,
                'payment_method' => $paymentMethod,
                'bank_transfer' => (float) ($data['bank_transfer'] ?? 0),
                'cash' => (float) ($data['cash'] ?? 0),
                'amount_cost' => $amountCost,
                'amount_paid' => $amountPaid,
                'change_bal' => $changeBal,
                'purchase_type' => $purchaseType,
                'profit_made' => $profitMade,
                'sale_discount_id' => $data['sale_discount_id'] ?? null,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'tax_percentage' => $taxPercentage,
                'final_total' => $finalTotal,
                'is_debt' => $isDebt,
                'user_id' => $user->id,
            ]);

            // Insert line items & deduct stock
            $today = now()->toDateString();
            foreach ($cartItems as $cartItem) {
                $item = $itemsMap->get($cartItem['item_id']);
                $qty = (int) $cartItem['qty'];
                $unitUsed = $cartItem['unit_used'] ?? 'unit';
                $price = (float) ($cartItem['price'] ?? $cartItem['unit_price'] ?? 0);
                $baseQty = $item->toBaseUnits($qty, $unitUsed);
                $imei = $cartItem['imei_or_device_id'] ?? null;

                $buyPrice = (float) ($item->buy_price ?? 0);
                $unitCostSnapshot = $qty > 0 ? round(($buyPrice * $baseQty) / $qty, 2) : $buyPrice;

                $saleOrder = SaleOrder::create([
                    'sale_id'            => $sale->id,
                    'item_id'            => $item->id,
                    'item_name'          => $item->item_name,
                    'imei_or_device_id'  => $imei,
                    'selling_price'      => $price,
                    'total_selling_price'=> $price * $qty,
                    'qty'                => $qty,
                    'unit_used'          => $unitUsed,
                    'purchase_type'      => $purchaseType,
                    'unit_cost_snapshot' => $unitCostSnapshot,
                    'user_id'            => $user->id,
                    'sort_date'          => now(),
                ]);

                // Mark IMEI device unit as sold
                if ($item->is_imei_tracked && $imei) {
                    \App\Models\ItemDeviceUnit::where('branch_id', $resolvedBranchId)
                        ->where('item_id', $item->id)
                        ->where('imei_or_device_id', $imei)
                        ->where('status', 'in_stock')
                        ->limit(1)
                        ->update([
                            'status'        => 'sold',
                            'sale_id'       => $sale->id,
                            'sale_order_id' => $saleOrder->id,
                            'sold_at'       => now(),
                        ]);
                }

                // Deduct stock in base units
                if ($settings->is_qty_deduction) {
                    InventoryService::deduct($item, $baseQty, $receiptId, $user);
                }

                // Silent FEFO batch deduction (non-blocking background side-effect per §4.1)
                \App\Services\BatchTrackingService::deductFefo($item, $baseQty, 'sale', $receiptId, $user);

                // Track most-sold items
                $mostSale = \App\Models\MostSaleItem::firstOrNew([
                    'branch_id' => $branch?->id,
                    'user_id' => $user->id,
                    'item_id' => $item->id,
                    'date_created_at' => $today,
                ]);
                $mostSale->qty += $qty;
                $mostSale->save();
            }

            // Handle debt
            if ($isDebt && $customerId) {
                $debtAmt = $finalTotal - $amountPaid;
                if ($debtAmt > 0) {
                    $customer = PosCustomer::find($customerId);
                    $balBefore = (float) ($customer?->debt_bal ?? 0);
                    $balAfter = $balBefore + $debtAmt;

                    DebtPayment::create([
                        'branch_id'      => $branch?->id,
                        'customer_id'    => $customerId,
                        'user_id'        => $user->id,
                        'sale_id'        => $sale->id,
                        'source'         => 'pos',
                        'amount'         => $debtAmt,
                        'balance_before' => $balBefore,
                        'balance_after'  => $balAfter,
                        'type'           => 'debit',
                        'narration'      => "Debt from sale #{$receiptId}",
                    ]);

                    if ($customer) {
                        $customer->increment('debt_bal', $debtAmt);
                    }
                }
            }

            \App\Services\ActivityLogger::sale(
                "Completed sale #{$receiptId} totaling ₦" . number_format($finalTotal, 2) . " via " . ucfirst($paymentMethod),
                $branch?->id,
                [
                    'receipt_id'     => $receiptId,
                    'grand_total'    => $finalTotal,
                    'payment_method' => $paymentMethod,
                    'item_count'     => count($cartItems),
                    'customer_id'    => $customerId,
                ]
            );

            if ($branch) {
                QuickAccessService::clearBranchCache($branch->id);
            }

            // Double-entry accounting post (passive, non-blocking §2, §6)
            \App\Modules\Accounting\Services\SalePostingService::postSale($sale);

            return $sale->load('saleOrders');
        });
    }
}
