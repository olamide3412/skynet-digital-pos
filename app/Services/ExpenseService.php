<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRevision;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    /**
     * Log a new expense for a branch.
     */
    public static function logExpense(array $data, int $branchId, User $user, ?UploadedFile $receipt = null): Expense
    {
        return DB::transaction(function () use ($data, $branchId, $user, $receipt) {
            $receiptPath = null;
            if ($receipt) {
                $receiptPath = $receipt->store('expenses/receipts', 'public');
            }

            $expenseDate = !empty($data['expense_date'])
                ? Carbon::parse($data['expense_date'])->toDateString()
                : Carbon::today()->toDateString();

            $expense = Expense::create([
                'branch_id'           => $branchId,
                'expense_category_id' => (int) $data['expense_category_id'],
                'amount'              => (float) $data['amount'],
                'expense_date'        => $expenseDate,
                'description'         => !empty($data['description']) ? trim($data['description']) : null,
                'receipt_path'        => $receiptPath,
                'logged_by'           => $user->id,
            ]);

            // Record initial revision creation
            ExpenseRevision::create([
                'expense_id' => $expense->id,
                'user_id'    => $user->id,
                'action'     => 'created',
                'changes'    => [
                    'amount'              => $expense->amount,
                    'expense_category_id' => $expense->expense_category_id,
                    'expense_date'        => $expense->expense_date,
                    'description'         => $expense->description,
                ],
                'reason'     => 'Initial creation',
            ]);

            ActivityLogger::log(
                'expense',
                "Logged operating expense of " . number_format($expense->amount, 2) . " under category #{$expense->expense_category_id}",
                $branchId,
                ['expense_id' => $expense->id, 'amount' => $expense->amount, 'user_id' => $user->id]
            );

            // Passive double-entry accounting posting (Rule 10)
            try {
                \App\Modules\Accounting\Services\InventoryPostingService::postExpense($expense, $user->id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Accounting expense posting error: ' . $e->getMessage());
            }

            return $expense;
        });
    }

    /**
     * Update an existing expense record and track audit changes.
     */
    public static function updateExpense(
        Expense $expense,
        array $data,
        User $user,
        ?UploadedFile $receipt = null,
        ?string $reason = null
    ): Expense {
        return DB::transaction(function () use ($expense, $data, $user, $receipt, $reason) {
            $oldData = [
                'amount'              => (float) $expense->amount,
                'expense_category_id' => (int) $expense->expense_category_id,
                'expense_date'        => $expense->expense_date ? Carbon::parse($expense->expense_date)->toDateString() : null,
                'description'         => $expense->description,
                'receipt_path'        => $expense->receipt_path,
            ];

            $updatePayload = [];

            if (isset($data['amount'])) {
                $updatePayload['amount'] = (float) $data['amount'];
            }
            if (isset($data['expense_category_id'])) {
                $updatePayload['expense_category_id'] = (int) $data['expense_category_id'];
            }
            if (isset($data['expense_date'])) {
                $updatePayload['expense_date'] = Carbon::parse($data['expense_date'])->toDateString();
            }
            if (array_key_exists('description', $data)) {
                $updatePayload['description'] = !empty($data['description']) ? trim($data['description']) : null;
            }

            if ($receipt) {
                // Delete old receipt file if replacing
                if ($expense->receipt_path) {
                    Storage::disk('public')->delete($expense->receipt_path);
                }
                $updatePayload['receipt_path'] = $receipt->store('expenses/receipts', 'public');
            }

            $expense->update($updatePayload);

            $newData = [
                'amount'              => (float) $expense->amount,
                'expense_category_id' => (int) $expense->expense_category_id,
                'expense_date'        => $expense->expense_date ? Carbon::parse($expense->expense_date)->toDateString() : null,
                'description'         => $expense->description,
                'receipt_path'        => $expense->receipt_path,
            ];

            // Record changes
            ExpenseRevision::create([
                'expense_id' => $expense->id,
                'user_id'    => $user->id,
                'action'     => 'updated',
                'changes'    => [
                    'before' => $oldData,
                    'after'  => $newData,
                ],
                'reason'     => $reason ?: 'Modified expense record',
            ]);

            ActivityLogger::log(
                'expense',
                "Updated operating expense #{$expense->id} (Amount: " . number_format($expense->amount, 2) . ")",
                $expense->branch_id,
                ['expense_id' => $expense->id, 'reason' => $reason]
            );

            // Reversing entry + new corrected entry (§6)
            try {
                \App\Modules\Accounting\Services\InventoryPostingService::reverseExpense($expense, $reason, $user);
                \App\Modules\Accounting\Services\InventoryPostingService::postExpense($expense->fresh(['category']), $user->id);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Accounting expense update reversal/posting error: ' . $e->getMessage());
            }

            return $expense;
        });
    }

    /**
     * Delete an expense and capture final revision.
     */
    public static function deleteExpense(Expense $expense, User $user, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($expense, $user, $reason) {
            $branchId = $expense->branch_id;
            $amount = $expense->amount;

            ExpenseRevision::create([
                'expense_id' => $expense->id,
                'user_id'    => $user->id,
                'action'     => 'deleted',
                'changes'    => [
                    'amount'              => $expense->amount,
                    'expense_category_id' => $expense->expense_category_id,
                    'expense_date'        => $expense->expense_date,
                    'description'         => $expense->description,
                ],
                'reason'     => $reason ?: 'Deleted expense record',
            ]);

            if ($expense->receipt_path) {
                Storage::disk('public')->delete($expense->receipt_path);
            }

            // Post reversing entry before deletion (§6)
            try {
                \App\Modules\Accounting\Services\InventoryPostingService::reverseExpense($expense, $reason ?: 'Deleted expense record', $user);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Accounting expense deletion reversal error: ' . $e->getMessage());
            }

            $deleted = $expense->delete();

            ActivityLogger::log(
                'expense',
                "Deleted operating expense #{$expense->id} (" . number_format($amount, 2) . ")",
                $branchId,
                ['expense_id' => $expense->id, 'reason' => $reason]
            );

            return $deleted;
        });
    }

    /**
     * Generate summary metrics and category breakdown for a branch in a given date range.
     */
    public static function getSummary(
        int $branchId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $categoryId = null,
        ?string $search = null
    ): array {
        $baseQuery = Expense::where('branch_id', $branchId)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($categoryId) {
            $baseQuery->where('expense_category_id', $categoryId);
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $totalAmount = (float) (clone $baseQuery)->sum('amount');
        $totalCount  = (int) (clone $baseQuery)->count();

        // Days in selected range
        $daysCount = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $dailyAverage = round($totalAmount / $daysCount, 2);

        // Breakdown by category
        $categoryBreakdownQuery = Expense::where('expenses.branch_id', $branchId)
            ->whereBetween('expenses.expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.id as category_id',
                'expense_categories.name as category_name',
                DB::raw('COUNT(expenses.id) as count'),
                DB::raw('SUM(expenses.amount) as total_amount')
            )
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderBy('total_amount', 'desc');

        if ($categoryId) {
            $categoryBreakdownQuery->where('expenses.expense_category_id', $categoryId);
        }

        $categories = $categoryBreakdownQuery->get()->map(function ($row) use ($totalAmount) {
            $catTotal = (float) $row->total_amount;
            $pct = $totalAmount > 0 ? round(($catTotal / $totalAmount) * 100, 1) : 0;
            return [
                'category_id'   => $row->category_id,
                'category_name' => $row->category_name,
                'count'         => (int) $row->count,
                'total_amount'  => $catTotal,
                'percentage'    => $pct,
            ];
        });

        $topCategory = $categories->first();

        return [
            'total_amount'        => $totalAmount,
            'total_count'         => $totalCount,
            'daily_average'       => $dailyAverage,
            'top_category_name'   => $topCategory ? $topCategory['category_name'] : 'N/A',
            'top_category_amount' => $topCategory ? $topCategory['total_amount'] : 0,
            'category_breakdown'  => $categories,
        ];
    }
}
