<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReportController extends Controller
{
    /**
     * Dedicated Branch Expense Report dashboard.
     */
    public function index(Request $request, $branchParam = null): Response
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $preset = $request->input('preset', 'month');
        [$startDate, $endDate] = $this->resolveDateRange($request, $preset);

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $search = trim($request->input('search', ''));

        // Query paginated expenses
        $expensesQuery = Expense::where('branch_id', $branch->id)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['category', 'user:id,name,username'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        if ($categoryId) {
            $expensesQuery->where('expense_category_id', $categoryId);
        }

        if ($search) {
            $expensesQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $expenses = $expensesQuery->paginate(20)->withQueryString();

        // Calculate summary KPIs & Category Breakdown
        $summary = ExpenseService::getSummary($branch->id, $startDate, $endDate, $categoryId, $search);

        // Fetch categories for filter dropdown
        $categories = ExpenseCategory::forBranch($branch->id)->get();

        return Inertia::render('Reports/Expenses', [
            'expenses'   => $expenses,
            'summary'    => $summary,
            'categories' => $categories,
            'filters'    => [
                'preset'      => $preset,
                'start_date'  => $startDate->toDateString(),
                'end_date'    => $endDate->toDateString(),
                'category_id' => $categoryId,
                'search'      => $search,
            ],
        ]);
    }

    /**
     * Export expenses report to Excel spreadsheet.
     */
    public function export(Request $request, $branchParam = null): StreamedResponse
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $preset = $request->input('preset', 'month');
        [$startDate, $endDate] = $this->resolveDateRange($request, $preset);

        $categoryId = $request->filled('category_id') ? (int) $request->category_id : null;
        $search = trim($request->input('search', ''));

        $expensesQuery = Expense::where('branch_id', $branch->id)
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['category', 'user:id,name,username'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        if ($categoryId) {
            $expensesQuery->where('expense_category_id', $categoryId);
        }

        if ($search) {
            $expensesQuery->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('category', fn ($cq) => $cq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
            });
        }

        $expenses = $expensesQuery->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Branch Expenses');

        $headers = [
            'ID', 'Date', 'Category', 'Amount', 'Description', 'Logged By', 'Receipt Available', 'Created At'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($expenses as $expense) {
            $sheet->fromArray([
                $expense->id,
                $expense->expense_date ? Carbon::parse($expense->expense_date)->format('Y-m-d') : '',
                $expense->category?->name ?? 'Uncategorized',
                (float) $expense->amount,
                $expense->description ?? '',
                $expense->user?->name ?? $expense->user?->username ?? 'System',
                $expense->receipt_path ? 'Yes' : 'No',
                $expense->created_at ? $expense->created_at->format('Y-m-d H:i') : '',
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'expenses_' . $branch->slug . '_' . date('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Resolve date bounds based on preset or explicit inputs.
     */
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
