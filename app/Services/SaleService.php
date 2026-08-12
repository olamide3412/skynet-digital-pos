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
        $settings = PosSettings::current();
        $branch = current_branch();

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

        // Validate stock & expiry
        foreach ($cartItems as $cartItem) {
            $item = $itemsMap->get($cartItem['item_id']);
            if (!$item) {
                throw new \InvalidArgumentException("Item ID {$cartItem['item_id']} not found.");
            }

            $unitUsed = $cartItem['unit_used'] ?? 'unit';
            $baseQty = $item->toBaseUnits((int) $cartItem['qty'], $unitUsed);

            if ($settings->is_qty_deduction && $item->front_store_qty < $baseQty) {
                $stockFormatted = $item->formatBaseUnits($item->front_store_qty);
                throw new \InvalidArgumentException("Insufficient stock for '{$item->item_name}'. Available: {$stockFormatted}.");
            }

            if ($settings->is_check_expiration && $item->isExpired()) {
                throw new \InvalidArgumentException("Item '{$item->item_name}' is expired.");
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
            $price = (float) $cartItem['price'];
            $qty = (int) $cartItem['qty'];
            $baseQty = $item->toBaseUnits($qty, $unitUsed);
            $buyPrice = (float) $item->buy_price;

            $lineTotal = $price * $qty;
            $lineCost = $buyPrice * $baseQty;
            $amountCost += $lineTotal;
            $totalCost += $lineCost;
        }

        $profitMade = ($amountCost + $consultFee - $discountAmount) - $totalCost;

        // Tax calculation on post-discount subtotal
        $taxAmount = 0;
        $taxPercentage = 0;
        if ($settings->is_tax_enabled && (float) $settings->tax_percentage > 0) {
            $taxPercentage = (float) $settings->tax_percentage;
            $taxableSubtotal = max(0, $amountCost + $consultFee - $discountAmount);
            $taxAmount = round(($taxableSubtotal * $taxPercentage) / 100, 2);
        }

        $finalTotal = $amountCost + $consultFee - $discountAmount + $taxAmount;

        return DB::transaction(function () use ($data, $user, $branch, $settings, $cartItems, $itemsMap, $purchaseType, $customerId, $amountCost, $finalTotal, $profitMade, $discountAmount, $consultFee, $taxAmount, $taxPercentage) {
            $receiptId = static::generateReceiptId();
            $paymentMethod = $data['payment_method'] ?? 'Cash';
            $isDebt = (bool) ($data['is_debt'] ?? false);
            $amountPaid = (float) ($data['amount_paid'] ?? 0);
            $changeBal = max(0, $amountPaid - $finalTotal);

            // Create Sale
            $sale = Sale::create([
                'branch_id' => $branch?->id,
                'customer_id' => $customerId,
                'receipt_id' => $receiptId,
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
                $price = (float) $cartItem['price'];
                $baseQty = $item->toBaseUnits($qty, $unitUsed);

                SaleOrder::create([
                    'sale_id' => $sale->id,
                    'item_id' => $item->id,
                    'item_name' => $item->item_name,
                    'selling_price' => $price,
                    'total_selling_price' => $price * $qty,
                    'qty' => $qty,
                    'unit_used' => $unitUsed,
                    'purchase_type' => $purchaseType,
                    'user_id' => $user->id,
                    'sort_date' => now(),
                ]);

                // Deduct stock in base units
                if ($settings->is_qty_deduction) {
                    InventoryService::deduct($item, $baseQty, $receiptId, $user);
                }

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

            return $sale->load('saleOrders');
        });
    }
}
