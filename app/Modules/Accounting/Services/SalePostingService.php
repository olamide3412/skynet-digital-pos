<?php

namespace App\Modules\Accounting\Services;

use App\Models\Branch;
use App\Models\DebtPayment;
use App\Models\PosSettings;
use App\Models\Sale;
use App\Models\SaleReturnItem;
use App\Models\TillSession;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalePostingService
{
    /**
     * Post journal entry for a completed sale.
     * Safe passive execution: never throws or blocks the sale checkout flow.
     */
    public static function postSale(Sale $sale): ?JournalEntry
    {
        try {
            $branchId = $sale->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!$settings->accounting_started_at) {
                return null;
            }

            $saleDate = $sale->created_at ? Carbon::parse($sale->created_at) : now();
            if ($saleDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            // Ensure sale orders are loaded
            if (!$sale->relationLoaded('saleOrders')) {
                $sale->load('saleOrders.item');
            }

            // Resolve system accounts
            $accounts = Account::where('branch_id', $branchId)
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $cashAcc       = $accounts->get('1000'); // Cash on Hand
            $bankAcc       = $accounts->get('1010'); // Bank
            $cardAcc       = $accounts->get('1020'); // POS / Card Clearing
            $arAcc         = $accounts->get('1100'); // Accounts Receivable
            $inventoryAcc  = $accounts->get('1200'); // Inventory
            $vatAcc        = $accounts->get('2100'); // VAT / Tax Payable
            $revenueAcc    = $accounts->get('4000'); // Sales Revenue
            $discountAcc   = $accounts->get('4200'); // Sales Discounts
            $cogsAcc       = $accounts->get('5000'); // Cost of Goods Sold

            if (!$revenueAcc || !$cashAcc) {
                Log::warning("Accounting postSale skipped: essential accounts (4000, 1000) not found for branch {$branchId}");
                return null;
            }

            $lines = [];

            $finalTotal     = round((float) $sale->final_total, 2);
            $discountAmount = round((float) ($sale->discount_amount ?? 0), 2);
            $taxAmount      = round((float) ($sale->tax_amount ?? 0), 2);
            $isVatEnabled   = (bool) ($settings->is_accounting_vat_enabled ?? false);

            // 1. Revenue & VAT Credits
            if ($isVatEnabled && $vatAcc && $taxAmount > 0) {
                $vatPayable = $taxAmount;
                $netRevenue = round(($finalTotal + $discountAmount) - $vatPayable, 2);

                $lines[] = [
                    'account_id' => $vatAcc->id,
                    'debit'      => 0.0,
                    'credit'     => $vatPayable,
                    'memo'       => 'VAT Output on sale',
                ];
            } else {
                $netRevenue = round($finalTotal + $discountAmount, 2);
            }

            if ($netRevenue > 0) {
                $lines[] = [
                    'account_id' => $revenueAcc->id,
                    'debit'      => 0.0,
                    'credit'     => $netRevenue,
                    'memo'       => 'Sales Revenue',
                ];
            }

            // 2. Discount Contra-Revenue Debit
            if ($discountAmount > 0 && $discountAcc) {
                $lines[] = [
                    'account_id' => $discountAcc->id,
                    'debit'      => $discountAmount,
                    'credit'     => 0.0,
                    'memo'       => 'Sales Discount',
                ];
            }

            // 3. Payment / Receivable Debits
            $paymentMethod = strtolower($sale->payment_method ?? 'cash');
            $isDebt        = (bool) ($sale->is_debt ?? false);
            $amountPaid    = round((float) ($sale->amount_paid ?? 0), 2);

            if ($isDebt) {
                $unpaid = max(0, round($finalTotal - $amountPaid, 2));
                if ($amountPaid > 0) {
                    $lines[] = [
                        'account_id'  => $cashAcc->id,
                        'debit'       => $amountPaid,
                        'credit'      => 0.0,
                        'memo'        => 'Upfront payment on credit sale',
                        'customer_id' => $sale->customer_id,
                    ];
                }
                if ($unpaid > 0 && $arAcc) {
                    $lines[] = [
                        'account_id'  => $arAcc->id,
                        'debit'       => $unpaid,
                        'credit'      => 0.0,
                        'memo'        => "Customer receivable #{$sale->receipt_id}",
                        'customer_id' => $sale->customer_id,
                    ];
                }
            } else {
                if (str_contains($paymentMethod, 'bank') || str_contains($paymentMethod, 'transfer')) {
                    $targetAcc = $bankAcc ?: $cashAcc;
                } elseif (str_contains($paymentMethod, 'card') || str_contains($paymentMethod, 'pos')) {
                    $targetAcc = $cardAcc ?: $cashAcc;
                } elseif ($paymentMethod === 'split') {
                    $cashPart = round((float) ($sale->cash ?? 0), 2);
                    $bankPart = round((float) ($sale->bank_transfer ?? 0), 2);
                    $sumParts = round($cashPart + $bankPart, 2);

                    if ($sumParts != $finalTotal) {
                        $cashPart = max(0, round($finalTotal - $bankPart, 2));
                    }

                    if ($cashPart > 0) {
                        $lines[] = ['account_id' => $cashAcc->id, 'debit' => $cashPart, 'credit' => 0.0, 'memo' => 'Cash portion'];
                    }
                    if ($bankPart > 0 && $bankAcc) {
                        $lines[] = ['account_id' => $bankAcc->id, 'debit' => $bankPart, 'credit' => 0.0, 'memo' => 'Bank portion'];
                    }
                    $targetAcc = null;
                } else {
                    $targetAcc = $cashAcc;
                }

                if ($targetAcc && $finalTotal > 0) {
                    $lines[] = [
                        'account_id'  => $targetAcc->id,
                        'debit'       => $finalTotal,
                        'credit'      => 0.0,
                        'memo'        => "Payment received via {$sale->payment_method}",
                        'customer_id' => $sale->customer_id,
                    ];
                }
            }

            // 4. Cost of Goods Sold & Inventory (Rule 2)
            $totalCogs = 0.0;
            foreach ($sale->saleOrders as $order) {
                $unitCost = (float) ($order->unit_cost_snapshot ?? ($order->item?->buy_price ?? 0));
                $lineCost = round($unitCost * (int) $order->qty, 2);
                $totalCogs += $lineCost;
            }
            $totalCogs = round($totalCogs, 2);

            if ($totalCogs > 0 && $cogsAcc && $inventoryAcc) {
                $lines[] = [
                    'account_id' => $cogsAcc->id,
                    'debit'      => $totalCogs,
                    'credit'     => 0.0,
                    'memo'       => 'Cost of Goods Sold',
                ];
                $lines[] = [
                    'account_id' => $inventoryAcc->id,
                    'debit'      => 0.0,
                    'credit'     => $totalCogs,
                    'memo'       => 'Inventory deduction',
                ];
            }

            return PostingService::post(
                'sale_completed',
                $sale,
                $lines,
                "Sale #{$sale->receipt_id}",
                $saleDate,
                $branchId,
                $sale->user_id
            );
        } catch (\Throwable $e) {
            Log::error("Accounting postSale failed for Sale #{$sale->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Post customer debt payment (Rule 5).
     */
    public static function postDebtPayment(DebtPayment $payment): ?JournalEntry
    {
        try {
            if ($payment->type !== 'credit' || (float) $payment->amount <= 0) {
                return null;
            }

            $branchId = $payment->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!$settings->accounting_started_at) {
                return null;
            }

            $paymentDate = $payment->created_at ? Carbon::parse($payment->created_at) : now();
            if ($paymentDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            $accounts = Account::where('branch_id', $branchId)->where('is_active', true)->get()->keyBy('code');
            $cashAcc = $accounts->get('1000');
            $arAcc   = $accounts->get('1100');

            if (!$cashAcc || !$arAcc) {
                return null;
            }

            $amount = round((float) $payment->amount, 2);
            $lines = [
                [
                    'account_id'  => $cashAcc->id,
                    'debit'       => $amount,
                    'credit'      => 0.0,
                    'memo'        => "Customer debt payment ({$payment->narration})",
                    'customer_id' => $payment->customer_id,
                ],
                [
                    'account_id'  => $arAcc->id,
                    'debit'       => 0.0,
                    'credit'      => $amount,
                    'memo'        => 'Receivable cleared',
                    'customer_id' => $payment->customer_id,
                ],
            ];

            return PostingService::post(
                'debt_payment',
                $payment,
                $lines,
                "Customer Debt Payment #{$payment->id}",
                $paymentDate,
                $branchId,
                $payment->user_id
            );
        } catch (\Throwable $e) {
            Log::error("Accounting postDebtPayment failed: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Post sale return (Rules 6 & 7).
     */
    public static function postSaleReturn(SaleReturnItem $returnItem): ?JournalEntry
    {
        try {
            $branch = current_branch();
            $branchId = $branch?->id ?? $returnItem->sale?->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!$settings->accounting_started_at) {
                return null;
            }

            $returnDate = $returnItem->created_at ? Carbon::parse($returnItem->created_at) : now();
            if ($returnDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            $accounts = Account::where('branch_id', $branchId)->where('is_active', true)->get()->keyBy('code');
            $cashAcc       = $accounts->get('1000'); // Cash on Hand
            $returnsAcc    = $accounts->get('4100'); // Sales Returns
            $inventoryAcc  = $accounts->get('1200'); // Inventory
            $cogsAcc       = $accounts->get('5000'); // COGS
            $vatAcc        = $accounts->get('2100'); // VAT

            if (!$cashAcc || !$returnsAcc) {
                return null;
            }

            $refundAmount = round((float) $returnItem->refund_amount, 2);
            if ($refundAmount <= 0) {
                return null;
            }

            $isVatEnabled = (bool) ($settings->is_accounting_vat_enabled ?? false);
            $lines = [];

            // Revenue and VAT reversal
            if ($isVatEnabled && $vatAcc && (float) $settings->tax_percentage > 0) {
                $rate = (float) $settings->tax_percentage;
                $vatPortion = round(($refundAmount * $rate) / (100 + $rate), 2);
                $netReturn = round($refundAmount - $vatPortion, 2);

                $lines[] = ['account_id' => $returnsAcc->id, 'debit' => $netReturn,  'credit' => 0.0, 'memo' => 'Return contra-revenue'];
                $lines[] = ['account_id' => $vatAcc->id,     'debit' => $vatPortion, 'credit' => 0.0, 'memo' => 'VAT Output relieved'];
            } else {
                $lines[] = ['account_id' => $returnsAcc->id, 'debit' => $refundAmount, 'credit' => 0.0, 'memo' => 'Return contra-revenue'];
            }

            $lines[] = ['account_id' => $cashAcc->id, 'debit' => 0.0, 'credit' => $refundAmount, 'memo' => 'Refund paid'];

            // Restocked Cost Side (Rule 7)
            $buyPrice = (float) ($returnItem->item?->buy_price ?? 0);
            $restockedCost = round($buyPrice * (int) $returnItem->qty, 2);

            if ($restockedCost > 0 && $inventoryAcc && $cogsAcc) {
                $lines[] = ['account_id' => $inventoryAcc->id, 'debit' => $restockedCost, 'credit' => 0.0, 'memo' => 'Restocked inventory'];
                $lines[] = ['account_id' => $cogsAcc->id,      'debit' => 0.0, 'credit' => $restockedCost, 'memo' => 'COGS reduction'];
            }

            return PostingService::post(
                'sale_return',
                $returnItem,
                $lines,
                "Return item #{$returnItem->id} ({$returnItem->item_name})",
                $returnDate,
                $branchId,
                $returnItem->user_id
            );
        } catch (\Throwable $e) {
            Log::error("Accounting postSaleReturn failed: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    /**
     * Post till session cash variance on closure (Rules 15 & 16).
     */
    public static function postTillClose(TillSession $session): ?JournalEntry
    {
        try {
            $variance = round((float) $session->variance, 2);
            if ($variance == 0.0) {
                return null;
            }

            $branchId = $session->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!$settings->accounting_started_at) {
                return null;
            }

            $closedDate = $session->closed_at ? Carbon::parse($session->closed_at) : now();
            if ($closedDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            $accounts = Account::where('branch_id', $branchId)->where('is_active', true)->get()->keyBy('code');
            $cashAcc      = $accounts->get('1000'); // Cash on Hand
            $overShortAcc = $accounts->get('5200'); // Cash Over / Short

            if (!$cashAcc || !$overShortAcc) {
                return null;
            }

            $absVariance = abs($variance);
            $lines = [];

            if ($variance < 0) {
                // Shortage: Debit Cash Over/Short, Credit Cash
                $lines[] = ['account_id' => $overShortAcc->id, 'debit' => $absVariance, 'credit' => 0.0, 'memo' => 'Till cash shortage'];
                $lines[] = ['account_id' => $cashAcc->id,      'debit' => 0.0, 'credit' => $absVariance, 'memo' => 'Till cash shortage'];
                $event = 'till_shortage';
                $desc = "Till Session #{$session->id} Shortage: -{$absVariance}";
            } else {
                // Surplus: Debit Cash, Credit Cash Over/Short
                $lines[] = ['account_id' => $cashAcc->id,      'debit' => $absVariance, 'credit' => 0.0, 'memo' => 'Till cash surplus'];
                $lines[] = ['account_id' => $overShortAcc->id, 'debit' => 0.0, 'credit' => $absVariance, 'memo' => 'Till cash surplus'];
                $event = 'till_surplus';
                $desc = "Till Session #{$session->id} Surplus: +{$absVariance}";
            }

            return PostingService::post(
                $event,
                $session,
                $lines,
                $desc,
                $closedDate,
                $branchId,
                $session->closed_by
            );
        } catch (\Throwable $e) {
            Log::error("Accounting postTillClose failed: " . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
}
