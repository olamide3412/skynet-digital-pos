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
     */
    public static function generateReceiptId(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'RC' . $date;

        $lastSale = Sale::where('receipt_id', 'like', $prefix . '%')
            ->orderByDesc('receipt_id')
            ->lockForUpdate()
            ->first();

        $sequence = $lastSale
            ? ((int) substr($lastSale->receipt_id, -4)) + 1
            : 1;

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
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

        $cartItems    = $data['items'];
        $purchaseType = $data['purchase_type'] ?? 'Consumer';
        $customerId   = $data['customer_id'] ?? null;

        if (empty($cartItems)) {
            throw new \InvalidArgumentException('Cart is empty.');
        }

        // Pre-load items
        $itemIds   = array_column($cartItems, 'item_id');
        $itemsMap  = Item::whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

        // Validate stock & expiry
        foreach ($cartItems as $cartItem) {
            $item = $itemsMap->get($cartItem['item_id']);
            if (!$item) {
                throw new \InvalidArgumentException("Item ID {$cartItem['item_id']} not found.");
            }

            if ($settings->is_qty_deduction && $item->qty < $cartItem['qty']) {
                throw new \InvalidArgumentException("Insufficient stock for '{$item->item_name}'. Available: {$item->qty}.");
            }

            if ($settings->is_check_expiration && $item->isExpired()) {
                throw new \InvalidArgumentException("Item '{$item->item_name}' is expired.");
            }

            if ($item->price_locked && isset($cartItem['price'])) {
                $expectedPrice = $purchaseType === 'Wholesale' ? $item->wholesale_price : $item->price;
                if ((float) $cartItem['price'] !== (float) $expectedPrice && !RoleService::canEditPrice()) {
                    throw new \InvalidArgumentException("Price for '{$item->item_name}' is locked.");
                }
            }
        }

        // Calculate totals
        $amountCost      = 0;
        $finalTotal      = 0;
        $profitMade      = 0;
        $discountAmount  = (float) ($data['discount_amount'] ?? 0);
        $consultFee      = (float) ($data['consultation_fee'] ?? 0);

        foreach ($cartItems as $cartItem) {
            $item     = $itemsMap->get($cartItem['item_id']);
            $price    = (float) $cartItem['price'];
            $qty      = (int) $cartItem['qty'];
            $buyPrice = (float) $item->buy_price;

            $amountCost += $price * $qty;
            $profitMade += ($price - $buyPrice) * $qty;
        }

        $finalTotal = $amountCost + $consultFee - $discountAmount;

        return DB::transaction(function () use (
            $data, $user, $settings, $cartItems, $itemsMap,
            $purchaseType, $customerId, $amountCost, $finalTotal,
            $profitMade, $discountAmount, $consultFee
        ) {
            $receiptId  = static::generateReceiptId();
            $isDebt     = (bool) ($data['is_debt'] ?? false);
            $amountPaid = (float) ($data['amount_paid'] ?? 0);
            $changeBal  = max(0, $amountPaid - $finalTotal);

            // Create Sale
            $sale = Sale::create([
                'customer_id'        => $customerId,
                'receipt_id'         => $receiptId,
                'items_order_count'  => count($cartItems),
                'consultation_fee'   => $consultFee,
                'payment_method'     => $data['payment_method'] ?? 'Cash',
                'bank_transfer'      => (float) ($data['bank_transfer'] ?? 0),
                'cash'               => (float) ($data['cash'] ?? 0),
                'amount_cost'        => $amountCost,
                'amount_paid'        => $amountPaid,
                'change_bal'         => $changeBal,
                'purchase_type'      => $purchaseType,
                'profit_made'        => $profitMade,
                'sale_discount_id'   => $data['sale_discount_id'] ?? null,
                'discount_amount'    => $discountAmount,
                'final_total'        => $finalTotal,
                'is_debt'            => $isDebt,
                'user_id'            => $user->id,
            ]);

            // Insert line items & deduct stock
            $today = now()->toDateString();
            foreach ($cartItems as $cartItem) {
                $item  = $itemsMap->get($cartItem['item_id']);
                $qty   = (int) $cartItem['qty'];
                $price = (float) $cartItem['price'];

                SaleOrder::create([
                    'sale_id'             => $sale->id,
                    'item_id'             => $item->id,
                    'item_name'           => $item->item_name,
                    'selling_price'       => $price,
                    'total_selling_price' => $price * $qty,
                    'qty'                 => $qty,
                    'purchase_type'       => $purchaseType,
                    'user_id'             => $user->id,
                    'sort_date'           => now(),
                ]);

                // Deduct stock
                if ($settings->is_qty_deduction) {
                    InventoryService::deduct($item->id, $qty, $receiptId, $user);
                }

                // Track most-sold items
                $mostSale = \App\Models\MostSaleItem::firstOrNew([
                    'user_id'         => $user->id,
                    'item_id'         => $item->id,
                    'date_created_at' => $today,
                ]);
                $mostSale->qty += $qty;
                $mostSale->save();
            }

            // Handle debt
            if ($isDebt && $customerId) {
                $debtAmt = $finalTotal - $amountPaid;
                if ($debtAmt > 0) {
                    DebtPayment::create([
                        'customer_id' => $customerId,
                        'user_id'     => $user->id,
                        'amount'      => $debtAmt,
                        'type'        => 'debit',
                        'narration'   => "Debt from sale #{$receiptId}",
                    ]);

                    PosCustomer::where('id', $customerId)
                        ->increment('debt_bal', $debtAmt);
                }
            }

            return $sale->load('saleOrders');
        });
    }
}
