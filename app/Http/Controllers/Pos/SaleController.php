<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StoreSaleRequest;
use App\Models\Sale;
use App\Services\RoleService;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    protected function buildFilteredSalesQuery(Request $request, $branch, $selectedUserId)
    {
        return Sale::where('branch_id', $branch->id)
            ->with(['user', 'customer', 'saleOrders'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('receipt_id', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%")
                               ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                               ->orWhere('full_name', 'like', "%{$search}%")
                               ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->payment_method, fn ($q, $pm) => $q->where('payment_method', $pm))
            ->when($request->from,           fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,             fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($selectedUserId,          fn ($q) => $q->where('user_id', $selectedUserId))
            ->latest();
    }

    public function index(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = RoleService::canViewAllReports();

        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        $salesQuery = $this->buildFilteredSalesQuery($request, $branch, $selectedUserId);

        $allSales = (clone $salesQuery)->get(['final_total', 'discount_amount', 'profit_made']);
        $summary = [
            'total_sales'    => $allSales->count(),
            'total_revenue'  => $allSales->sum('final_total'),
            'total_discount' => $allSales->sum('discount_amount'),
            'total_profit'   => $allSales->sum('profit_made'),
        ];

        $users = $canSeeAll
            ? \App\Models\User::where('branch_id', $branch->id)
                ->get(['id', 'name', 'full_name', 'username'])
            : [];

        return Inertia::render('Sales/Index', [
            'sales'     => $salesQuery->paginate(20)->withQueryString(),
            'summary'   => $summary,
            'users'     => $users,
            'canSeeAll' => $canSeeAll,
            'filters'   => [
                'search'         => $request->search ?? '',
                'payment_method' => $request->payment_method ?? '',
                'from'           => $request->from ?? '',
                'to'             => $request->to ?? '',
                'user_id'        => $selectedUserId ?? '',
            ],
            'settings'  => \App\Models\PosSettings::current(),
        ]);
    }

    /**
     * Export sales history spreadsheet (Excel .xlsx or CSV .csv) based on active filters.
     */
    public function export(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = RoleService::canViewAllReports();
        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $sales = $this->buildFilteredSalesQuery($request, $branch, $selectedUserId)->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales History');

        $headers = [
            'Receipt #',
            'Date & Time',
            'Customer Name',
            'Customer Phone',
            'Cashier / Staff',
            'Payment Method',
            'Items Count',
            'Cost Price (₦)',
            'Discount (₦)',
            'Tax (₦)',
            'Total Paid (₦)',
            'Gross Profit (₦)',
            'Cash (₦)',
            'Bank Transfer (₦)',
            'Debt / Credit (₦)',
        ];

        // Write Headers
        foreach ($headers as $colIdx => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        // Header Styling
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        // Data Rows
        $rowNum = 2;
        foreach ($sales as $sale) {
            $staffName = $sale->user?->full_name ?: ($sale->user?->name ?: ($sale->user?->username ?: '-'));

            $sheet->setCellValue("A{$rowNum}", $sale->receipt_id);
            $sheet->setCellValue("B{$rowNum}", $sale->created_at ? $sale->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue("C{$rowNum}", $sale->customer?->name ?? 'Walk-in Customer');
            $sheet->setCellValue("D{$rowNum}", $sale->customer?->phone ?? '-');
            $sheet->setCellValue("E{$rowNum}", $staffName);
            $sheet->setCellValue("F{$rowNum}", $sale->payment_method);
            $sheet->setCellValue("G{$rowNum}", (int) $sale->items_order_count);
            $sheet->setCellValue("H{$rowNum}", (float) $sale->amount_cost);
            $sheet->setCellValue("I{$rowNum}", (float) $sale->discount_amount);
            $sheet->setCellValue("J{$rowNum}", (float) $sale->tax_amount);
            $sheet->setCellValue("K{$rowNum}", (float) $sale->final_total);
            $sheet->setCellValue("L{$rowNum}", (float) $sale->profit_made);
            $sheet->setCellValue("M{$rowNum}", (float) $sale->cash);
            $sheet->setCellValue("N{$rowNum}", (float) $sale->bank_transfer);
            $sheet->setCellValue("O{$rowNum}", $sale->is_debt ? (float) $sale->final_total : 0);

            $rowNum++;
        }

        // Summary row
        if ($sales->isNotEmpty()) {
            $sheet->setCellValue("A{$rowNum}", 'TOTAL');
            $sheet->setCellValue("G{$rowNum}", "=SUM(G2:G" . ($rowNum - 1) . ")");
            $sheet->setCellValue("H{$rowNum}", "=SUM(H2:H" . ($rowNum - 1) . ")");
            $sheet->setCellValue("I{$rowNum}", "=SUM(I2:I" . ($rowNum - 1) . ")");
            $sheet->setCellValue("J{$rowNum}", "=SUM(J2:J" . ($rowNum - 1) . ")");
            $sheet->setCellValue("K{$rowNum}", "=SUM(K2:K" . ($rowNum - 1) . ")");
            $sheet->setCellValue("L{$rowNum}", "=SUM(L2:L" . ($rowNum - 1) . ")");
            $sheet->setCellValue("M{$rowNum}", "=SUM(M2:M" . ($rowNum - 1) . ")");
            $sheet->setCellValue("N{$rowNum}", "=SUM(N2:N" . ($rowNum - 1) . ")");
            $sheet->setCellValue("O{$rowNum}", "=SUM(O2:O" . ($rowNum - 1) . ")");

            $totalRange = "A{$rowNum}:{$lastCol}{$rowNum}";
            $sheet->getStyle($totalRange)->getFont()->setBold(true);
            $sheet->getStyle($totalRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2E8F0');
        }

        // Auto-fit column widths
        foreach (range(1, count($headers)) as $colIdx) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $branchSlug = \Illuminate\Support\Str::slug($branch->name ?? 'branch');
        $dateStr = date('Ymd_His');
        $fileName = "sales_history_{$branchSlug}_{$dateStr}.{$format}";

        return response()->streamDownload(function () use ($spreadsheet, $format) {
            $writer = $format === 'csv'
                ? new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet)
                : new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            if ($format === 'csv') {
                $writer->setUseBOM(true);
            }
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    public function store(StoreSaleRequest $request)
    {
        try {
            $sale = SaleService::process($request->validated(), Auth::user());
            $sale->load(['user', 'customer', 'saleOrders']);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['sale' => $sale, 'message' => 'Sale successful']);
            }

            return back()->with([
                'success' => true,
                'receipt_id' => $sale->receipt_id,
                'sale_id'    => $sale->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->withErrors(['sale' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            $msg = config('app.debug') ? $e->getMessage() : 'Sale processing failed. Please try again.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => $msg], 500);
            }
            return back()->withErrors(['sale' => $msg]);
        }
    }

    public function show($branchParam, Sale $sale)
    {
        $sale->load(['saleOrders.item', 'customer', 'user', 'saleDiscount']);

        return Inertia::render('Sales/Show', [
            'sale'     => $sale,
            'settings' => \App\Models\PosSettings::current(),
        ]);
    }

    public function destroy($branchParam, Sale $sale)
    {
        if (!RoleService::canDeleteSale()) {
            abort(403, 'Insufficient permissions to delete a sale.');
        }

        DB::transaction(function () use ($sale) {
            $sale->saleOrders()->delete();
            $sale->delete();
        });

        return redirect()->route('pos.sales.index')
            ->with('success', "Sale #{$sale->receipt_id} deleted.");
    }

    public function searchReceipt(Request $request)
    {
        $branch = current_branch();
        $query  = trim($request->get('query', ''));

        if (!$query) {
            return response()->json(['message' => 'Please enter a receipt number.'], 422);
        }

        $sale = Sale::where('branch_id', $branch->id)
            ->where(function ($q) use ($query) {
                $q->where('receipt_id', $query)
                  ->orWhere('receipt_id', 'like', '%' . $query)
                  ->orWhere('id', $query);
            })
            ->with(['saleOrders.item', 'customer', 'user'])
            ->first();

        if (!$sale) {
            return response()->json(['message' => 'Sale not found for receipt: ' . $query], 404);
        }

        return response()->json($sale);
    }
}
