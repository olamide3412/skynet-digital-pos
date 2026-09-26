<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    /**
     * Store a newly created expense.
     */
    public function store(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount'              => 'required|numeric|min:0.01',
            'expense_date'        => 'required|date',
            'description'         => 'nullable|string|max:1000',
            'receipt'             => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5120', // 5MB
        ]);

        $receiptFile = $request->file('receipt');
        $expense = ExpenseService::logExpense($validated, $branch->id, Auth::user(), $receiptFile);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'expense' => $expense->load('category', 'user'),
                'message' => 'Expense recorded successfully.',
            ]);
        }

        return back()->with('success', 'Operating expense recorded successfully.');
    }

    /**
     * Update an existing expense.
     */
    public function update(Request $request, $branchParam, Expense $expense)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $expense->branch_id !== $branch->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount'              => 'required|numeric|min:0.01',
            'expense_date'        => 'required|date',
            'description'         => 'nullable|string|max:1000',
            'receipt'             => 'nullable|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
            'reason'              => 'nullable|string|max:255',
        ]);

        $receiptFile = $request->file('receipt');
        $reason = $validated['reason'] ?? 'Updated by user';
        unset($validated['reason']);

        $updated = ExpenseService::updateExpense($expense, $validated, Auth::user(), $receiptFile, $reason);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'expense' => $updated->load('category', 'user'),
                'message' => 'Expense updated successfully.',
            ]);
        }

        return back()->with('success', 'Expense updated successfully.');
    }

    /**
     * Delete an expense record.
     */
    public function destroy(Request $request, $branchParam, Expense $expense)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $expense->branch_id !== $branch->id) {
            abort(403, 'Unauthorized action.');
        }

        $reason = $request->input('reason', 'Deleted by user');
        ExpenseService::deleteExpense($expense, Auth::user(), $reason);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Expense deleted successfully.',
            ]);
        }

        return back()->with('success', 'Expense deleted successfully.');
    }
}
