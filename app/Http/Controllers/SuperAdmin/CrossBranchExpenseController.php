<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrossBranchExpenseController extends Controller
{
    /**
     * Cross-branch operating expense comparison dashboard for Super Admin.
     */
    public function index(Request $request): Response
    {
        $preset = $request->input('preset', 'month');
        [$startDate, $endDate] = $this->resolveDateRange($request, $preset);

        $selectedBranchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->category_id : null;

        // Base Query
        $baseQuery = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($selectedBranchId) {
            $baseQuery->where('branch_id', $selectedBranchId);
        }
        if ($selectedCategoryId) {
            $baseQuery->where('expense_category_id', $selectedCategoryId);
        }

        $totalExpenses = (float) (clone $baseQuery)->sum('amount');
        $totalCount    = (int) (clone $baseQuery)->count();

        // 1. Branch Comparison Breakdown
        $branchBreakdown = Branch::where('is_active', true)
            ->get()
            ->map(function ($b) use ($startDate, $endDate, $selectedCategoryId, $totalExpenses) {
                $q = Expense::where('branch_id', $b->id)
                    ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

                if ($selectedCategoryId) {
                    $q->where('expense_category_id', $selectedCategoryId);
                }

                $sum = (float) $q->sum('amount');
                $count = (int) $q->count();
                $pct = $totalExpenses > 0 ? round(($sum / $totalExpenses) * 100, 1) : 0;

                return [
                    'id'           => $b->id,
                    'name'         => $b->name,
                    'slug'         => $b->slug,
                    'total_amount' => $sum,
                    'count'        => $count,
                    'percentage'   => $pct,
                ];
            })
            ->sortByDesc('total_amount')
            ->values();

        // 2. Global Category Breakdown
        $categoryBreakdown = Expense::whereBetween('expenses.expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($selectedBranchId, fn ($q) => $q->where('expenses.branch_id', $selectedBranchId))
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.id as category_id',
                'expense_categories.name as category_name',
                DB::raw('COUNT(expenses.id) as count'),
                DB::raw('SUM(expenses.amount) as total_amount')
            )
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderBy('total_amount', 'desc')
            ->get()
            ->map(function ($row) use ($totalExpenses) {
                $catTotal = (float) $row->total_amount;
                $pct = $totalExpenses > 0 ? round(($catTotal / $totalExpenses) * 100, 1) : 0;
                return [
                    'category_id'   => $row->category_id,
                    'category_name' => $row->category_name,
                    'count'         => (int) $row->count,
                    'total_amount'  => $catTotal,
                    'percentage'    => $pct,
                ];
            });

        $allBranches = Branch::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);
        $allCategories = ExpenseCategory::orderBy('name')->get(['id', 'name', 'branch_id']);

        return Inertia::render('SuperAdmin/Reports/Expenses', [
            'totalExpenses'     => $totalExpenses,
            'totalCount'        => $totalCount,
            'branchBreakdown'   => $branchBreakdown,
            'categoryBreakdown' => $categoryBreakdown,
            'branches'          => $allBranches,
            'categories'        => $allCategories,
            'filters'           => [
                'preset'      => $preset,
                'start_date'  => $startDate->toDateString(),
                'end_date'    => $endDate->toDateString(),
                'branch_id'   => $selectedBranchId,
                'category_id' => $selectedCategoryId,
            ],
        ]);
    }

    /**
     * Export cross-branch expenses comparison to Excel.
     */
    public function export(Request $request): StreamedResponse
    {
        $preset = $request->input('preset', 'month');
        [$startDate, $endDate] = $this->resolveDateRange($request, $preset);

        $selectedBranchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $selectedCategoryId = $request->filled('category_id') ? (int) $request->category_id : null;

        $expenses = Expense::whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($selectedBranchId, fn ($q) => $q->where('branch_id', $selectedBranchId))
            ->when($selectedCategoryId, fn ($q) => $q->where('expense_category_id', $selectedCategoryId))
            ->with(['branch:id,name', 'category:id,name', 'user:id,name,username'])
            ->orderBy('expense_date', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('All Branches Expenses');

        $headers = [
            'ID', 'Branch', 'Date', 'Category', 'Amount', 'Description', 'Logged By', 'Created At'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($expenses as $expense) {
            $sheet->fromArray([
                $expense->id,
                $expense->branch?->name ?? 'N/A',
                $expense->expense_date ? Carbon::parse($expense->expense_date)->format('Y-m-d') : '',
                $expense->category?->name ?? 'Uncategorized',
                (float) $expense->amount,
                $expense->description ?? '',
                $expense->user?->name ?? $expense->user?->username ?? 'System',
                $expense->created_at ? $expense->created_at->format('Y-m-d H:i') : '',
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'cross_branch_expenses_' . date('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    private function resolveDateRange(Request $request, string $preset): array
    {
        $today = Carbon::today();

        if ($request->filled('start_date') && $request->filled('end_date') && $preset === 'custom') {
            return [
                Carbon::parse($request->input('start_date'))->startOfDay(),
                Carbon::parse($request->input('end_date'))->endOfDay(),
            ];
        }

        switch ($preset) {
            case 'today':
                return [$today->copy()->startOfDay(), $today->copy()->endOfDay()];
            case 'yesterday':
                $y = $today->copy()->subDay();
                return [$y->startOfDay(), $y->endOfDay()];
            case 'week':
                return [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()];
            case 'month':
            default:
                return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()];
        }
    }
}
