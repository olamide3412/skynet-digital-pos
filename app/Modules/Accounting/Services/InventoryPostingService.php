<?php

namespace App\Modules\Accounting\Services;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Item;
use App\Models\PosSettings;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class InventoryPostingService
{
    /**
     * Post journal entry for a received Purchase Order (Rule 8).
     * Purchases are treated as paid at receipt (Debit Inventory 1200 / Credit Cash 1000 or Bank 1010).
     */
    public static function postPurchaseOrderReceipt(
        PurchaseOrder $purchase,
        float $receivedCost,
        ?int $userId = null,
        ?string $receiptRef = null
    ): ?JournalEntry {
        try {
            $receivedCost = round($receivedCost, 2);
            if ($receivedCost <= 0) {
                return null;
            }

            $branchId = $purchase->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!self::ensureBranchAccountingReady($branch, $settings)) {
                return null;
            }

            $txDate = now();
            if ($txDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            $accounts = Account::where('branch_id', $branchId)
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $inventoryAcc = $accounts->get('1200'); // Inventory
            $cashAcc      = $accounts->get('1000'); // Cash on Hand
            $bankAcc      = $accounts->get('1010'); // Bank

            if (!$inventoryAcc || !$cashAcc) {
                Log::warning("Accounting postPurchaseOrderReceipt skipped: essential accounts (1200, 1000) not found for branch {$branchId}");
                return null;
            }

            // By default credit Cash on Hand (or Bank if specified)
            $creditAcc = $cashAcc;

            $vendorName = $purchase->vendor?->name ?? 'Vendor';
            $memo = "PO #{$purchase->po_number} Receipt - {$vendorName}";

            $lines = [
                [
                    'account_id' => $inventoryAcc->id,
                    'debit'      => $receivedCost,
                    'credit'     => 0.0,
                    'memo'       => "Inventory received: {$memo}",
                ],
                [
                    'account_id' => $creditAcc->id,
                    'debit'      => 0.0,
                    'credit'     => $receivedCost,
                    'memo'       => "Payment for receipt: {$memo}",
                ],
            ];

            $event = 'purchase_receipt' . ($receiptRef ? '_' . $receiptRef : '');

            return PostingService::post([
                'event'       => $event,
                'source'      => $purchase,
                'branch_id'   => $branchId,
                'created_by'  => $userId,
                'entry_date'  => $txDate,
                'description' => "Purchase Order Received: {$purchase->po_number}",
                'lines'       => $lines,
            ]);
        } catch (\Throwable $e) {
            Log::error("Accounting postPurchaseOrderReceipt failed for PO #{$purchase->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Post stock adjustment (Rules 11 & 12).
     * Decrease / shrinkage: Dr Inventory Shrinkage (5100) / Cr Inventory (1200)
     * Increase / surplus: Dr Inventory (1200) / Cr Inventory Shrinkage (5100)
     */
    public static function postStockAdjustment(
        Item $item,
        string $type,
        int $qty,
        float $cost,
        string $reason,
        ?int $userId = null,
        ?string $referenceId = null
    ): ?JournalEntry {
        try {
            $totalCost = round($qty * $cost, 2);
            if ($totalCost <= 0) {
                return null;
            }

            $branchId = $item->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!self::ensureBranchAccountingReady($branch, $settings)) {
                return null;
            }

            $txDate = now();
            if ($txDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            $accounts = Account::where('branch_id', $branchId)
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $inventoryAcc = $accounts->get('1200'); // Inventory
            $shrinkageAcc = $accounts->get('5100'); // Inventory Shrinkage / Expiry Write-off

            if (!$inventoryAcc || !$shrinkageAcc) {
                Log::warning("Accounting postStockAdjustment skipped: accounts (1200, 5100) not found for branch {$branchId}");
                return null;
            }

            $isDecrease = in_array(strtolower($type), ['subtract', 'subtraction', 'decrease', 'shrinkage', 'expiry', 'damage']);

            if ($isDecrease) {
                // Rule 11: Dr Shrinkage / Cr Inventory
                $lines = [
                    [
                        'account_id' => $shrinkageAcc->id,
                        'debit'      => $totalCost,
                        'credit'     => 0.0,
                        'memo'       => "Stock shrinkage: {$item->item_name} (-{$qty}) - {$reason}",
                    ],
                    [
                        'account_id' => $inventoryAcc->id,
                        'debit'      => 0.0,
                        'credit'     => $totalCost,
                        'memo'       => "Stock decrease: {$item->item_name} (-{$qty})",
                    ],
                ];
                $desc = "Stock Adjustment (Decrease): {$item->item_name} (-{$qty})";
            } else {
                // Rule 12: Dr Inventory / Cr Shrinkage
                $lines = [
                    [
                        'account_id' => $inventoryAcc->id,
                        'debit'      => $totalCost,
                        'credit'     => 0.0,
                        'memo'       => "Stock increase: {$item->item_name} (+{$qty})",
                    ],
                    [
                        'account_id' => $shrinkageAcc->id,
                        'debit'      => 0.0,
                        'credit'     => $totalCost,
                        'memo'       => "Stock surplus: {$item->item_name} (+{$qty}) - {$reason}",
                    ],
                ];
                $desc = "Stock Adjustment (Increase): {$item->item_name} (+{$qty})";
            }

            $event = 'stock_adj_' . ($referenceId ?: time());

            return PostingService::post([
                'event'       => $event,
                'source'      => $item,
                'branch_id'   => $branchId,
                'created_by'  => $userId,
                'entry_date'  => $txDate,
                'description' => $desc,
                'lines'       => $lines,
            ]);
        } catch (\Throwable $e) {
            Log::error("Accounting postStockAdjustment failed for Item #{$item->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Post stock transfer between branches (Rules 13 & 14).
     * Sending branch: Dr Inter-Branch Receivable (1300) / Cr Inventory (1200)
     * Receiving branch: Dr Inventory (1200) / Cr Inter-Branch Payable (2200)
     */
    public static function postStockTransfer(
        int $fromBranchId,
        int $toBranchId,
        float $totalCost,
        string $reference,
        ?int $userId = null
    ): array {
        $results = ['out' => null, 'in' => null];
        $totalCost = round($totalCost, 2);
        if ($totalCost <= 0) {
            return $results;
        }

        // 1. Sending Branch (Rule 13)
        try {
            $fromBranch = Branch::with('organization')->find($fromBranchId);
            if ($fromBranch && (!$fromBranch->organization || $fromBranch->organization->hasFeature('accounting'))) {
                $fromSettings = PosSettings::forBranch($fromBranchId);
                if (self::ensureBranchAccountingReady($fromBranch, $fromSettings)) {
                    $accounts = Account::where('branch_id', $fromBranchId)
                        ->where('is_active', true)
                        ->get()
                        ->keyBy('code');

                    $interReceivable = $accounts->get('1300'); // Inter-Branch Receivable
                    $inventory       = $accounts->get('1200'); // Inventory

                    if ($interReceivable && $inventory) {
                        $lines = [
                            [
                                'account_id' => $interReceivable->id,
                                'debit'      => $totalCost,
                                'credit'     => 0.0,
                                'memo'       => "Transfer out to Branch #{$toBranchId} - Ref: {$reference}",
                            ],
                            [
                                'account_id' => $inventory->id,
                                'debit'      => 0.0,
                                'credit'     => $totalCost,
                                'memo'       => "Stock dispatched - Ref: {$reference}",
                            ],
                        ];

                        $results['out'] = PostingService::post([
                            'event'          => 'transfer_out_' . $reference,
                            'branch_id'      => $fromBranchId,
                            'created_by'     => $userId,
                            'entry_date'     => now(),
                            'description'    => "Stock Transfer Out to Branch #{$toBranchId} ({$reference})",
                            'lines'          => $lines,
                            'source_type'    => 'stock_transfer',
                            'source_id'      => (int) crc32($reference),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Accounting postStockTransfer (out) failed for ref {$reference}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        // 2. Receiving Branch (Rule 14)
        try {
            $toBranch = Branch::with('organization')->find($toBranchId);
            if ($toBranch && (!$toBranch->organization || $toBranch->organization->hasFeature('accounting'))) {
                $toSettings = PosSettings::forBranch($toBranchId);
                if (self::ensureBranchAccountingReady($toBranch, $toSettings)) {
                    $accounts = Account::where('branch_id', $toBranchId)
                        ->where('is_active', true)
                        ->get()
                        ->keyBy('code');

                    $inventory    = $accounts->get('1200'); // Inventory
                    $interPayable = $accounts->get('2200'); // Inter-Branch Payable

                    if ($inventory && $interPayable) {
                        $lines = [
                            [
                                'account_id' => $inventory->id,
                                'debit'      => $totalCost,
                                'credit'     => 0.0,
                                'memo'       => "Stock received from Branch #{$fromBranchId} - Ref: {$reference}",
                            ],
                            [
                                'account_id' => $interPayable->id,
                                'debit'      => 0.0,
                                'credit'     => $totalCost,
                                'memo'       => "Inter-branch payable to Branch #{$fromBranchId} - Ref: {$reference}",
                            ],
                        ];

                        $results['in'] = PostingService::post([
                            'event'          => 'transfer_in_' . $reference,
                            'branch_id'      => $toBranchId,
                            'created_by'     => $userId,
                            'entry_date'     => now(),
                            'description'    => "Stock Transfer In from Branch #{$fromBranchId} ({$reference})",
                            'lines'          => $lines,
                            'source_type'    => 'stock_transfer',
                            'source_id'      => (int) crc32($reference),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error("Accounting postStockTransfer (in) failed for ref {$reference}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return $results;
    }

    /**
     * Post journal entry for an operating expense (Rule 10).
     * Debit Expense category account (6xxx) / Credit Cash on Hand (1000) or Bank (1010).
     */
    public static function postExpense(Expense $expense, ?int $userId = null): ?JournalEntry
    {
        try {
            $amount = round((float) $expense->amount, 2);
            if ($amount <= 0) {
                return null;
            }

            $branchId = $expense->branch_id;
            if (!$branchId) {
                return null;
            }

            $branch = Branch::with('organization')->find($branchId);
            if (!$branch || ($branch->organization && !$branch->organization->hasFeature('accounting'))) {
                return null;
            }

            $settings = PosSettings::forBranch($branchId);
            if (!self::ensureBranchAccountingReady($branch, $settings)) {
                return null;
            }

            $txDate = $expense->expense_date ? Carbon::parse($expense->expense_date) : now();
            if ($txDate->lt($settings->accounting_started_at->startOfDay())) {
                return null;
            }

            // Ensure category is loaded
            if (!$expense->relationLoaded('category')) {
                $expense->load('category');
            }

            $category = $expense->category;

            // Resolve expense account
            $expenseAcc = null;
            if ($category && $category->account_id) {
                $expenseAcc = Account::where('branch_id', $branchId)->find($category->account_id);
            }

            if (!$expenseAcc && $category) {
                $expenseAcc = Account::where('branch_id', $branchId)
                    ->where('name', 'Expense: ' . $category->name)
                    ->first();
            }

            if (!$expenseAcc) {
                // Look for any 6xxx operating expense account for this branch
                $expenseAcc = Account::where('branch_id', $branchId)
                    ->where('type', 'expense')
                    ->where('code', 'like', '6%')
                    ->orderBy('code')
                    ->first();
            }

            if (!$expenseAcc) {
                // Fallback to any expense account
                $expenseAcc = Account::where('branch_id', $branchId)
                    ->where('type', 'expense')
                    ->first();
            }

            $cashAcc = Account::where('branch_id', $branchId)
                ->where('code', '1000')
                ->first();

            if (!$expenseAcc || !$cashAcc) {
                Log::warning("Accounting postExpense skipped: missing expense or cash account for branch {$branchId}");
                return null;
            }

            $categoryName = $category?->name ?? 'Operating Expense';
            $memo = $expense->description ? "{$categoryName}: {$expense->description}" : $categoryName;

            $lines = [
                [
                    'account_id' => $expenseAcc->id,
                    'debit'      => $amount,
                    'credit'     => 0.0,
                    'memo'       => $memo,
                ],
                [
                    'account_id' => $cashAcc->id,
                    'debit'      => 0.0,
                    'credit'     => $amount,
                    'memo'       => "Payment for expense: {$memo}",
                ],
            ];

            return PostingService::post([
                'event'       => 'expense_recorded',
                'source'      => $expense,
                'branch_id'   => $branchId,
                'created_by'  => $userId ?: $expense->logged_by,
                'entry_date'  => $txDate,
                'description' => "Expense: {$categoryName}",
                'lines'       => $lines,
            ]);
        } catch (\Throwable $e) {
            Log::error("Accounting postExpense failed for Expense #{$expense->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Reverse posted expense entry upon update or deletion.
     */
    public static function reverseExpense(Expense $expense, ?string $reason = null, ?User $user = null): ?JournalEntry
    {
        try {
            $existingEntry = JournalEntry::where('source_type', Expense::class)
                ->where('source_id', $expense->id)
                ->where('event', 'expense_recorded')
                ->whereNull('reversal_of_id')
                ->whereDoesntHave('reversedBy')
                ->first();

            if (!$existingEntry) {
                return null;
            }

            return PostingService::reverseEntry(
                $existingEntry,
                $reason ?: "Expense #{$expense->id} updated or deleted",
                $user
            );
        } catch (\Throwable $e) {
            Log::error("Accounting reverseExpense failed for Expense #{$expense->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Post cash drop / bank deposit (Rule 17).
     * Debit Bank (1010) / Credit Cash on Hand (1000).
     */
    public static function postCashDrop(
        Branch $branch,
        float $amount,
        ?string $reference = null,
        ?int $userId = null
    ): ?JournalEntry {
        try {
            $amount = round($amount, 2);
            if ($amount <= 0) {
                return null;
            }

            if ($branch->organization && !$branch->organization->hasFeature('accounting')) {
                return null;
            }

            $settings = PosSettings::forBranch($branch->id);
            if (!self::ensureBranchAccountingReady($branch, $settings)) {
                return null;
            }

            $accounts = Account::where('branch_id', $branch->id)
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $bankAcc = $accounts->get('1010');
            $cashAcc = $accounts->get('1000');

            if (!$bankAcc || !$cashAcc) {
                return null;
            }

            $memo = $reference ? "Cash drop / deposit - {$reference}" : "Cash drop to bank";

            $lines = [
                [
                    'account_id' => $bankAcc->id,
                    'debit'      => $amount,
                    'credit'     => 0.0,
                    'memo'       => $memo,
                ],
                [
                    'account_id' => $cashAcc->id,
                    'debit'      => 0.0,
                    'credit'     => $amount,
                    'memo'       => $memo,
                ],
            ];

            $event = 'cash_drop_' . ($reference ?: time());

            return PostingService::post([
                'event'       => $event,
                'branch_id'   => $branch->id,
                'created_by'  => $userId,
                'entry_date'  => now(),
                'description' => "Cash drop / bank deposit",
                'lines'       => $lines,
            ]);
        } catch (\Throwable $e) {
            Log::error("Accounting postCashDrop failed for branch {$branch->id}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    private static function ensureBranchAccountingReady(Branch $branch, PosSettings &$settings): bool
    {
        if (!$settings->accounting_started_at) {
            if ($branch->organization && $branch->organization->hasFeature('accounting')) {
                AccountSetupService::activateBranch($branch);
                $settings = PosSettings::forBranch($branch->id);
                return true;
            }
            return false;
        }
        return true;
    }
}
