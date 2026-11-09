<?php

namespace App\Modules\Laundry\Services;

use App\Models\Branch;
use App\Models\DebtPayment;
use App\Models\PosCustomer;
use App\Models\TillSession;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryDiscount;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryOrderStatusHistory;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Modules\Laundry\Services\LaundryDiscountService;
use App\Services\RoleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaundryOrderService
{
    /**
     * Create a new laundry order in a single atomic database transaction.
     */
    public function createOrder(Branch $branch, User $user, array $data): LaundryOrder
    {
        return DB::transaction(function () use ($branch, $user, $data) {
            $laundrySettings = LaundrySetting::forBranch($branch->id);
            $posSettings = $branch->getSettings();

            // 1. Resolve Customer & Snapshots (enforce branch isolation)
            $customer = PosCustomer::where('id', $data['customer_id'])
                ->where('branch_id', $branch->id)
                ->firstOrFail();

            // 2. Race-Safe Order Number Generation
            $prefix = $laundrySettings->order_prefix ?: 'LDY-';
            $lastOrder = LaundryOrder::where('branch_id', $branch->id)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $lastNum = 0;
            if ($lastOrder && preg_match('/(\d+)$/', $lastOrder->order_number, $matches)) {
                $lastNum = (int) $matches[1];
            }

            $nextNum = $lastNum + 1;
            $orderNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            while (LaundryOrder::where('branch_id', $branch->id)->where('order_number', $orderNumber)->exists()) {
                $nextNum++;
                $orderNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            }

            // 3. Compute Item Lines and Subtotal
            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages(['items' => 'At least one service line is required.']);
            }

            $subtotal = 0;
            $preparedLines = [];
            $orderIsExpress = !empty($data['is_express']) || (($data['speed_tier'] ?? '') === 'express');
            $defaultExpressMultiplier = (float) ($laundrySettings->default_express_multiplier ?? 1.50);

            foreach ($itemsData as $idx => $line) {
                $service = null;
                if (!empty($line['service_id'])) {
                    $service = LaundryService::where('branch_id', $branch->id)->find($line['service_id']);
                }

                $serviceName = $service ? $service->name : ($line['service_name'] ?? 'Custom Service');
                $pricingType = $service ? $service->pricing_type : ($line['pricing_type'] ?? 'per_piece');
                $quantity    = (float) ($line['quantity'] ?? 1);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages(["items.{$idx}.quantity" => 'Quantity/Weight must be greater than zero.']);
                }

                // Check express tier for this item (line-level override takes precedence over global order flag)
                $lineIsExpress = isset($line['is_express']) ? (bool) $line['is_express'] : $orderIsExpress;
                $lineSpeedTier = $lineIsExpress ? 'express' : 'standard';

                // Resolve service discount and base price
                if ($service) {
                    $baseCatalogPrice = $lineIsExpress
                        ? $service->resolveExpressPrice($defaultExpressMultiplier)
                        : (float) $service->price;
                } else {
                    $rawBase = isset($line['original_price']) ? (float) $line['original_price'] : (isset($line['unit_price']) ? (float) $line['unit_price'] : 0);
                    $baseCatalogPrice = $lineIsExpress ? round($rawBase * $defaultExpressMultiplier, 2) : $rawBase;
                }

                $originalPrice = $baseCatalogPrice;
                $unitPrice = isset($line['unit_price']) && $line['unit_price'] !== '' ? (float) $line['unit_price'] : $originalPrice;
                $serviceDiscountId = $line['laundry_discount_id'] ?? null;
                $lineDiscountAmount = isset($line['discount_amount']) ? (float) $line['discount_amount'] : 0;

                if ($service) {
                    $canOverridePrice = (bool) $laundrySettings->allow_price_override && (
                        $user->isSuperAdmin() ||
                        $user->isBranchAdmin() ||
                        RoleService::can('laundry.override_price') ||
                        RoleService::can('canEditPrice')
                    );

                    if (!$canOverridePrice) {
                        // Enforce catalog price or system discount when price override is disabled or user lacks permission
                        $discountInfo = LaundryDiscountService::resolveServiceDiscount($service);
                        if ($discountInfo['has_discount']) {
                            // Apply discount amount from base catalogue or standard promo
                            $discAmt = (float) $discountInfo['discount_amount'];
                            $unitPrice = max(0, round($originalPrice - $discAmt, 2));
                            $serviceDiscountId = $discountInfo['discount_id'];
                            $lineDiscountAmount = $discAmt;
                        } else {
                            $unitPrice = $originalPrice;
                            $serviceDiscountId = null;
                            $lineDiscountAmount = 0;
                        }
                    } elseif (!isset($line['unit_price']) || $line['unit_price'] === '') {
                        $discountInfo = LaundryDiscountService::resolveServiceDiscount($service);
                        if ($discountInfo['has_discount']) {
                            $discAmt = (float) $discountInfo['discount_amount'];
                            $unitPrice = max(0, round($originalPrice - $discAmt, 2));
                            $serviceDiscountId = $discountInfo['discount_id'];
                            $lineDiscountAmount = $discAmt;
                        } else {
                            $unitPrice = $originalPrice;
                        }
                    }
                }

                $lineTotal = round($unitPrice * $quantity, 2);
                $subtotal += $lineTotal;

                $preparedLines[] = [
                    'service_id'          => $service?->id,
                    'service_name'        => $serviceName,
                    'pricing_type'        => $pricingType,
                    'is_express'          => $lineIsExpress,
                    'speed_tier'          => $lineSpeedTier,
                    'original_price'      => $originalPrice,
                    'unit_price'          => $unitPrice,
                    'quantity'            => $quantity,
                    'discount_amount'     => $lineDiscountAmount,
                    'laundry_discount_id' => $serviceDiscountId,
                    'line_total'          => $lineTotal,
                    'note'                => $line['note'] ?? null,
                ];
            }

            // If any individual item was set to express, elevate order to express
            if (!$orderIsExpress && collect($preparedLines)->contains('is_express', true)) {
                $orderIsExpress = true;
            }

            // 4. Calculate Order-Level Discounts & Taxes
            $discountAmount = 0;
            $discountType = $data['discount_type'] ?? null;
            $generalDiscountId = $data['laundry_discount_id'] ?? null;

            // If general promo discount ID supplied, load it to verify/calculate
            if ($generalDiscountId) {
                $genDiscount = LaundryDiscount::forBranch($branch->id)->general()->find($generalDiscountId);
                if ($genDiscount && $genDiscount->isActiveNow()) {
                    $discountType = $genDiscount->discount_type;
                    $discountAmount = $genDiscount->computeDiscount($subtotal);
                } else {
                    $generalDiscountId = null;
                }
            } elseif (!empty($data['discount_amount']) && (float) $data['discount_amount'] > 0) {
                // Manual discount
                $rawDiscount = (float) $data['discount_amount'];
                if ($discountType === 'percentage') {
                    $discountAmount = round($subtotal * (min(100, $rawDiscount) / 100), 2);
                } else {
                    $discountAmount = min($subtotal, round($rawDiscount, 2));
                }
            } elseif (!array_key_exists('laundry_discount_id', $data) && !array_key_exists('discount_amount', $data)) {
                // Auto-apply active general branch discount if available and not explicitly submitted
                $activeGenPromo = LaundryDiscountService::getActiveGeneralDiscount($branch->id);
                if ($activeGenPromo) {
                    $generalDiscountId = $activeGenPromo->id;
                    $discountType = $activeGenPromo->discount_type;
                    $discountAmount = $activeGenPromo->computeDiscount($subtotal);
                }
            }

            $taxableSubtotal = max(0, $subtotal - $discountAmount);
            $taxAmount = 0;
            if (!empty($posSettings->is_tax_enabled) && (float) $posSettings->tax_percentage > 0) {
                $taxAmount = round($taxableSubtotal * ((float) $posSettings->tax_percentage / 100), 2);
            }

            $total = round($taxableSubtotal + $taxAmount, 2);

            // 5. Due Date Calculation
            if (!empty($data['due_at'])) {
                $dueAt = Carbon::parse($data['due_at']);
            } elseif ($orderIsExpress) {
                $dueAt = Carbon::now()->addHours($laundrySettings->express_turnaround_hours ?: 24);
            } else {
                $dueAt = Carbon::now()->addDays($laundrySettings->default_turnaround_days ?: 2);
            }

            // 6. Create Order Header
            $order = LaundryOrder::create([
                'branch_id'           => $branch->id,
                'order_number'        => $orderNumber,
                'customer_id'         => $customer->id,
                'customer_name'       => $customer->name,
                'customer_phone'      => $customer->phone ?? '',
                'status'              => 'received',
                'is_express'          => $orderIsExpress,
                'speed_tier'          => $orderIsExpress ? 'express' : 'standard',
                'received_at'         => Carbon::now(),
                'due_at'              => $dueAt,
                'created_by'          => $user->id,
                'subtotal'            => $subtotal,
                'discount_amount'     => $discountAmount,
                'discount_type'       => $discountType,
                'laundry_discount_id' => $generalDiscountId,
                'tax_amount'          => $taxAmount,
                'total'               => $total,
                'amount_paid'         => 0,
                'balance'             => $total,
                'note'                => $data['note'] ?? null,
            ]);

            // 7. Insert Item Lines
            foreach ($preparedLines as $pLine) {
                $order->items()->create($pLine);
            }

            // 8. Record Status History
            $order->statusHistory()->create([
                'from_status' => null,
                'to_status'   => 'received',
                'changed_by'  => $user->id,
                'changed_at'  => Carbon::now(),
                'note'        => 'Order intake created',
            ]);

            // 9. Process Deposit Payment if Provided
            $deposit = (float) ($data['deposit_amount'] ?? 0);
            if ($deposit > 0) {
                $paymentMethod = $data['payment_method'] ?? 'Cash';
                $this->recordPayment($order, $user, [
                    'amount'    => $deposit,
                    'method'    => $paymentMethod,
                    'reference' => $data['payment_reference'] ?? null,
                    'note'      => 'Intake deposit payment',
                ]);
            }

            return $order->fresh(['items', 'payments', 'customer', 'statusHistory']);
        });
    }

    /**
     * Record a payment (deposit or balance settlement) against an order.
     */
    public function recordPayment(LaundryOrder $order, User $user, array $data, bool $syncDebtLedger = true): LaundryPayment
    {
        return DB::transaction(function () use ($order, $user, $data, $syncDebtLedger) {
            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            $currentBalance = (float) $order->balance;
            if ($amount > $currentBalance + 0.001) {
                throw ValidationException::withMessages(['amount' => "Payment amount (₦" . number_format($amount, 2) . ") cannot exceed the order balance (₦" . number_format($currentBalance, 2) . ")."]);
            }

            // Check Till Session Requirement
            $branch = $order->relationLoaded('branch') ? $order->branch : Branch::find($order->branch_id);
            $posSettings = $branch ? $branch->getSettings() : null;
            $tillSessionId = null;

            $activeTill = TillSession::where('branch_id', $order->branch_id)
                ->where('status', 'open')
                ->where(function ($q) use ($user) {
                    $q->where('cashier_id', $user->id)
                      ->orWhereNull('cashier_id');
                })
                ->latest('id')
                ->first();

            // Till session requirement applies only to counter/POS transactions, not automated debt ledger settlements
            if ($syncDebtLedger && !empty($posSettings->require_till_opening) && !$activeTill) {
                throw ValidationException::withMessages(['till' => 'An active till session is required to record cash payments. Please open a till first.']);
            }

            if ($activeTill) {
                $tillSessionId = $activeTill->id;
            }

            $method = $data['method'] ?? 'Cash';
            $reference = $data['reference'] ?? 'LPAY-' . strtoupper(substr(uniqid(), -6));

            $payment = LaundryPayment::create([
                'order_id'        => $order->id,
                'branch_id'       => $order->branch_id,
                'type'            => 'payment',
                'amount'          => $amount,
                'method'          => $method,
                'till_session_id' => $tillSessionId,
                'recorded_by'     => $user->id,
                'paid_at'         => Carbon::now(),
                'reference'       => $reference,
                'note'            => $data['note'] ?? null,
            ]);

            // Update order financial aggregates
            $totalPaid = (float) $order->payments()->where('type', 'payment')->sum('amount')
                       - (float) $order->payments()->where('type', 'refund')->sum('amount');
            $newBalance = max(0, round((float) $order->total - $totalPaid, 2));

            $order->update([
                'amount_paid' => round($totalPaid, 2),
                'balance'     => $newBalance,
            ]);

            // Sync with Customer Debt Ledger if this order had previously moved balance to customer debt
            if ($syncDebtLedger && $order->customer_id) {
                $hasPriorDebt = $order->status === 'collected'
                    || DebtPayment::where('laundry_order_id', $order->id)->where('type', 'debit')->exists();

                if ($hasPriorDebt) {
                    $customer = $order->customer;
                    if ($customer && (float) $customer->debt_bal > 0) {
                        $currentDebt = (float) $customer->debt_bal;
                        $creditAmount = min($amount, $currentDebt);

                        if ($creditAmount > 0) {
                            $balAfter = round(max(0, $currentDebt - $creditAmount), 2);

                            $debtPayment = DebtPayment::create([
                                'branch_id'        => $order->branch_id,
                                'customer_id'      => $customer->id,
                                'user_id'          => $user->id,
                                'laundry_order_id' => $order->id,
                                'source'           => 'laundry',
                                'amount'           => $creditAmount,
                                'type'             => 'credit',
                                'narration'        => "Payment received for collected laundry order #{$order->order_number} ({$method})",
                                'balance_before'   => $currentDebt,
                                'balance_after'    => $balAfter,
                            ]);

                            $customer->update(['debt_bal' => $balAfter]);

                            \App\Modules\Accounting\Services\SalePostingService::postDebtPayment($debtPayment);
                        }
                    }
                }
            }

            return $payment;
        });
    }

    /**
     * Update order workflow status with validation and debt posting on unpaid collection.
     */
    public function updateStatus(LaundryOrder $order, string $newStatus, User $user, ?string $note = null, ?string $collectedByName = null): LaundryOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $user, $note, $collectedByName) {
            $oldStatus = $order->status;
            if ($oldStatus === $newStatus) {
                return $order;
            }

            if ($oldStatus === 'collected') {
                throw ValidationException::withMessages(['status' => 'An order that has already been collected cannot be changed.']);
            }

            if ($oldStatus === 'cancelled') {
                throw ValidationException::withMessages(['status' => 'A cancelled order cannot be reactivated.']);
            }

            $validStatuses = ['received', 'in_progress', 'ready', 'collected', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                throw ValidationException::withMessages(['status' => 'Invalid status transition requested.']);
            }

            $orderUpdates = ['status' => $newStatus];

            // ── Handling 'collected' Transition ──────────────────────────────
            if ($newStatus === 'collected') {
                $balance = (float) $order->balance;
                if ($balance > 0.01) {
                    $laundrySettings = LaundrySetting::forBranch($order->branch_id);
                    $canReleaseUnpaid = $user->isSuperAdmin()
                        || $user->isBranchAdmin()
                        || $user->hasPermissionTo('laundry.release_unpaid')
                        || (bool) $laundrySettings->allow_unpaid_pickup;

                    if (!$canReleaseUnpaid) {
                        throw ValidationException::withMessages([
                            'status' => "Cannot release garments: Order has an outstanding balance of ₦" . number_format($balance, 2) . ". Full payment or supervisor release permission required.",
                        ]);
                    }

                    // Post outstanding balance to customer's debt ledger
                    $customer = $order->customer ?: ($order->customer_id ? PosCustomer::find($order->customer_id) : null);
                    if (!$customer && $order->customer_phone) {
                        $customer = PosCustomer::where('branch_id', $order->branch_id)
                            ->where('phone', $order->customer_phone)
                            ->first();
                        if (!$customer && $order->customer_name) {
                            $customer = PosCustomer::create([
                                'branch_id' => $order->branch_id,
                                'name'      => $order->customer_name,
                                'phone'     => $order->customer_phone,
                                'debt_bal'  => 0,
                            ]);
                        }
                        if ($customer) {
                            $order->update(['customer_id' => $customer->id]);
                        }
                    }

                    if ($customer) {
                        $balanceBefore = (float) $customer->debt_bal;
                        $balanceAfter  = round($balanceBefore + $balance, 2);

                        DebtPayment::create([
                            'branch_id'        => $order->branch_id,
                            'customer_id'      => $customer->id,
                            'user_id'          => $user->id,
                            'laundry_order_id' => $order->id,
                            'source'           => 'laundry',
                            'amount'           => $balance,
                            'type'             => 'debit',
                            'narration'        => "Unpaid balance released on laundry order #{$order->order_number}",
                            'balance_before'   => $balanceBefore,
                            'balance_after'    => $balanceAfter,
                        ]);

                        $customer->update(['debt_bal' => $balanceAfter]);
                    }
                }

                $orderUpdates['collected_at']          = Carbon::now();
                $orderUpdates['collected_by_user_id']  = $user->id;
                $orderUpdates['collected_by_name']     = $collectedByName ?: ($order->customer_name ?: $user->name);
            }

            // ── Handling 'cancelled' Transition (Automatic Refund Handling) ──
            if ($newStatus === 'cancelled') {
                $amountPaid = (float) $order->amount_paid;
                if ($amountPaid > 0) {
                    // Record full refund
                    $this->recordRefund($order, $user, [
                        'amount'    => $amountPaid,
                        'method'    => 'Cash',
                        'reference' => 'LREF-' . strtoupper(substr(uniqid(), -6)),
                        'note'      => $note ?: 'Refund on order cancellation',
                    ]);
                }
            }

            $order->update($orderUpdates);

            // Record status change history
            $order->statusHistory()->create([
                'from_status' => $oldStatus,
                'to_status'   => $newStatus,
                'changed_by'  => $user->id,
                'changed_at'  => Carbon::now(),
                'note'        => $note,
            ]);

            return $order->fresh(['items', 'payments', 'statusHistory']);
        });
    }

    /**
     * Record a refund against an order payment.
     */
    public function recordRefund(LaundryOrder $order, User $user, array $data): LaundryPayment
    {
        return DB::transaction(function () use ($order, $user, $data) {
            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Refund amount must be greater than zero.']);
            }

            $paidAmount = (float) $order->amount_paid;
            if ($amount > $paidAmount + 0.001) {
                throw ValidationException::withMessages(['amount' => "Refund amount cannot exceed amount paid (₦" . number_format($paidAmount, 2) . ")."]);
            }

            $activeTill = TillSession::where('branch_id', $order->branch_id)
                ->where('status', 'open')
                ->latest('id')
                ->first();

            $refund = LaundryPayment::create([
                'order_id'        => $order->id,
                'branch_id'       => $order->branch_id,
                'type'            => 'refund',
                'amount'          => $amount,
                'method'          => $data['method'] ?? 'Cash',
                'till_session_id' => $activeTill?->id,
                'recorded_by'     => $user->id,
                'paid_at'         => Carbon::now(),
                'reference'       => $data['reference'] ?? 'LREF-' . strtoupper(substr(uniqid(), -6)),
                'note'            => $data['note'] ?? 'Refund processed',
            ]);

            // Recompute order totals
            $totalPaid = (float) $order->payments()->where('type', 'payment')->sum('amount')
                       - (float) $order->payments()->where('type', 'refund')->sum('amount');
            $newBalance = max(0, round((float) $order->total - $totalPaid, 2));

            $order->update([
                'amount_paid' => round($totalPaid, 2),
                'balance'     => $newBalance,
            ]);

            return $refund;
        });
    }

    /**
     * Normalize customer phone number to international WhatsApp format.
     */
    public static function normalizeWhatsAppPhone(?string $phone, string $defaultCountryCode = '+234'): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $clean = preg_replace('/[^\d+]/', '', trim($phone));
        $countryDigits = preg_replace('/[^\d]/', '', $defaultCountryCode);

        // Starts with +
        if (str_starts_with($clean, '+')) {
            return ltrim($clean, '+');
        }

        // Starts with single 0 (local e.g. 08012345678)
        if (str_starts_with($clean, '0')) {
            return $countryDigits . substr($clean, 1);
        }

        // Already has country digits without + (e.g. 2348012345678)
        if (str_starts_with($clean, $countryDigits)) {
            return $clean;
        }

        return $countryDigits . $clean;
    }

    /**
     * Generate click-to-chat WhatsApp link using customer's whatsapp_number or phone.
     */
    public static function generateWhatsAppLink(LaundryOrder $order, LaundrySetting $settings): ?string
    {
        $targetPhone = $order->customer?->whatsapp_number ?: $order->customer_phone;
        if (empty($targetPhone)) {
            return null;
        }

        $normalizedPhone = static::normalizeWhatsAppPhone($targetPhone, $settings->default_country_code ?: '+234');
        if (empty($normalizedPhone)) {
            return null;
        }

        $branchName = $order->branch?->name ?: 'our store';
        $msg = str_replace(
            ['{customer_name}', '{order_number}', '{branch_name}', '{balance}'],
            [$order->customer_name, $order->order_number, $branchName, '₦' . number_format($order->balance, 2)],
            $settings->whatsapp_template ?: "Hello {customer_name}, your laundry order #{order_number} is ready for pickup at {branch_name}! Total due: {balance}. Thank you for choosing us."
        );

        return "https://wa.me/{$normalizedPhone}?text=" . urlencode($msg);
    }
}
