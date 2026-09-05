<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Item;
use App\Models\SaleOrder;
use App\Models\SaleReturnItem;
use App\Models\PosCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportController extends Controller
{
    private function parseDateRange(Request $request, $defaultRange = 'today'): array
    {
        $fromInput = $request->input('from') ?: $request->input('start_date');
        $toInput   = $request->input('to') ?: $request->input('end_date');

        if ($fromInput && $toInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::parse($toInput)->endOfDay();
        } elseif ($fromInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::today()->endOfDay();
        } else {
            if ($defaultRange === 'month') {
                $startDate = Carbon::now()->startOfMonth()->startOfDay();
                $endDate   = Carbon::now()->endOfDay();
            } else {
                $startDate = Carbon::today()->startOfDay();
                $endDate   = Carbon::today()->endOfDay();
            }
        }

        return [$startDate, $endDate];
    }

    private function streamExcelOrCsv(Spreadsheet $spreadsheet, string $filenamePrefix, string $format = 'xlsx')
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new XlsxWriter($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
    public function index(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = \App\Services\RoleService::canViewAllReports();
        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        // Determine Start & End Dates (Default to current month if not specified)
        $fromInput = $request->input('from');
        $toInput   = $request->input('to');

        if ($fromInput && $toInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::parse($toInput)->endOfDay();
        } elseif ($fromInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::today()->endOfDay();
        } else {
            $startDate = Carbon::now()->startOfMonth()->startOfDay();
            $endDate   = Carbon::now()->endOfDay();
        }

        // Base query for sales within the date range & staff scope
        $salesQuery = Sale::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId));

        // 1. Dynamic Sales & Profit Trend
        $diffInDays = $startDate->diffInDays($endDate);
        $salesTrend = [];

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
            // Single Day -> Hourly Breakdown
            $hourlyTrend = (clone $salesQuery)
                ->select(
                    DB::raw('HOUR(created_at) as hour'),
                    DB::raw('SUM(final_total) as revenue'),
                    DB::raw('SUM(profit_made) as profit'),
                    DB::raw('COUNT(id) as count')
                )
                ->groupBy(DB::raw('HOUR(created_at)'))
                ->orderBy('hour', 'asc')
                ->get();

            for ($h = 8; $h <= 21; $h++) {
                $match = $hourlyTrend->firstWhere('hour', $h);
                $label = sprintf('%02d:00', $h);
                $salesTrend[] = [
                    'day'     => $label,
                    'revenue' => (float) ($match->revenue ?? 0),
                    'profit'  => (float) ($match->profit ?? 0),
                    'count'   => (int) ($match->count ?? 0),
                ];
            }
        } elseif ($diffInDays <= 31) {
            // Up to 31 Days -> Daily Breakdown
            $dailyTrend = (clone $salesQuery)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(final_total) as revenue'),
                    DB::raw('SUM(profit_made) as profit'),
                    DB::raw('COUNT(id) as count')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date', 'asc')
                ->get();

            $curr = $startDate->copy();
            while ($curr->lte($endDate)) {
                $d = $curr->format('Y-m-d');
                $match = $dailyTrend->firstWhere('date', $d);
                $salesTrend[] = [
                    'day'     => $curr->format('D, M j'),
                    'revenue' => (float) ($match->revenue ?? 0),
                    'profit'  => (float) ($match->profit ?? 0),
                    'count'   => (int) ($match->count ?? 0),
                ];
                $curr->addDay();
            }
        } else {
            // More than 31 Days -> Monthly Breakdown
            $monthlyTrend = (clone $salesQuery)
                ->select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month_key"),
                    DB::raw('SUM(final_total) as revenue'),
                    DB::raw('SUM(profit_made) as profit'),
                    DB::raw('COUNT(id) as count')
                )
                ->groupBy('month_key')
                ->orderBy('month_key', 'asc')
                ->get();

            $curr = $startDate->copy()->startOfMonth();
            while ($curr->lte($endDate)) {
                $mKey = $curr->format('Y-m');
                $match = $monthlyTrend->firstWhere('month_key', $mKey);
                $salesTrend[] = [
                    'day'     => $curr->format('M Y'),
                    'revenue' => (float) ($match->revenue ?? 0),
                    'profit'  => (float) ($match->profit ?? 0),
                    'count'   => (int) ($match->count ?? 0),
                ];
                $curr->addMonth();
            }
        }

        // 2. Payment Method Breakdown in Period
        $periodSales = (clone $salesQuery)->get(['cash', 'bank_transfer', 'is_debt', 'final_total', 'profit_made', 'discount_amount']);

        $cashSum = (float) $periodSales->sum('cash');
        $bankSum = (float) $periodSales->sum('bank_transfer');
        $debtSum = (float) $periodSales->where('is_debt', true)->sum('final_total');

        $paymentDistribution = [
            'Cash'          => $cashSum,
            'Bank Transfer' => $bankSum,
            'Customer Debt' => $debtSum,
        ];

        // 3. Top 5 Best Selling Items in Period
        $topItems = SaleOrder::with('item')
            ->whereHas('sale', function ($q) use ($branch, $startDate, $endDate, $selectedUserId) {
                $q->where('branch_id', $branch->id)
                  ->whereBetween('created_at', [$startDate, $endDate])
                  ->when($selectedUserId, fn ($sq) => $sq->where('user_id', $selectedUserId));
            })
            ->select('item_id', 'item_name', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total_selling_price) as total_revenue'))
            ->groupBy('item_id', 'item_name')
            ->orderBy('total_revenue', 'desc')
            ->take(5)
            ->get();

        // 4. Returns summary in Period
        $periodReturns = SaleReturnItem::whereHas('item', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->get(['qty', 'refund_amount']);

        $returnsSummary = [
            'count'        => $periodReturns->count(),
            'total_qty'    => (int) $periodReturns->sum('qty'),
            'refund_worth' => (float) $periodReturns->sum('refund_amount'),
        ];

        // 5. Key Period Stats
        $periodStats = [
            'revenue'          => (float) $periodSales->sum('final_total'),
            'profit'           => (float) $periodSales->sum('profit_made'),
            'discount'         => (float) $periodSales->sum('discount_amount'),
            'sales_count'      => $periodSales->count(),
            'returns_worth'    => (float) $returnsSummary['refund_worth'],
            'outstanding_debt' => (float) PosCustomer::when($branch, fn ($q) => $q->where('branch_id', $branch->id))->where('debt_bal', '>', 0)->sum('debt_bal'),
        ];

        $users = $canSeeAll
            ? User::where('branch_id', $branch->id)->select('id', 'name', 'username', 'full_name')->get()
            : [];

        return Inertia::render('Reports/Index', [
            'salesTrend'          => $salesTrend,
            'paymentDistribution' => $paymentDistribution,
            'topItems'            => $topItems,
            'returnsSummary'      => $returnsSummary,
            'monthlyStats'        => $periodStats,
            'periodStats'         => $periodStats,
            'users'               => $users,
            'canSeeAll'           => $canSeeAll,
            'filters'             => [
                'from'    => $startDate->format('Y-m-d'),
                'to'      => $endDate->format('Y-m-d'),
                'user_id' => $request->user_id ?? '',
            ],
        ]);
    }

    public function returns(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = \App\Services\RoleService::canViewAllReports();

        [$startDate, $endDate] = $this->parseDateRange($request, 'today');
        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        $baseQuery = SaleReturnItem::with(['sale.customer', 'item', 'user'])
            ->whereHas('item', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('item_name', 'like', $term)
                        ->orWhere('return_reason', 'like', $term)
                        ->orWhereHas('sale', fn ($s) => $s->where('receipt_id', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
                });
            });

        $allItems = (clone $baseQuery)->get();

        $totalBaseUnits = 0;
        foreach ($allItems as $rItem) {
            $u = strtolower($rItem->unit_used ?? 'unit');
            if ($rItem->unit_multiplier) {
                $totalBaseUnits += ((int) $rItem->qty) * (int) $rItem->unit_multiplier;
            } elseif ($rItem->item) {
                $totalBaseUnits += $rItem->item->toBaseUnits((int) $rItem->qty, $u);
            } else {
                $totalBaseUnits += (int) $rItem->qty;
            }
        }

        $summary = [
            'total_returns'      => $allItems->count(),
            'total_items_qty'    => (int) $allItems->sum('qty'),
            'total_base_units'   => $totalBaseUnits,
            'total_refund_worth' => (float) $allItems->sum('refund_amount'),
        ];

        $returns = (clone $baseQuery)
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $users = $canSeeAll
            ? User::where('branch_id', $branch->id)->get(['id', 'name', 'full_name', 'username'])
            : [];

        return Inertia::render('Reports/Returns', [
            'returns'   => $returns,
            'summary'   => $summary,
            'users'     => $users,
            'canSeeAll' => $canSeeAll,
            'filters'   => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'from'       => $startDate->format('Y-m-d'),
                'to'         => $endDate->format('Y-m-d'),
                'user_id'    => $selectedUserId ?? '',
                'search'     => $request->get('search', ''),
            ]
        ]);
    }

    public function returnsExport(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = \App\Services\RoleService::canViewAllReports();

        [$startDate, $endDate] = $this->parseDateRange($request, 'today');
        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        $returns = SaleReturnItem::with(['sale.customer', 'item', 'user'])
            ->whereHas('item', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('item_name', 'like', $term)
                        ->orWhere('return_reason', 'like', $term)
                        ->orWhereHas('sale', fn ($s) => $s->where('receipt_id', 'like', $term)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)));
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Returned Items Report');

        $headers = [
            '#',
            'Return Date & Time',
            'Original Receipt ID',
            'Item Name',
            'Barcode / SKU',
            'Returned Qty',
            'Unit Used',
            'Restocked Base Units',
            'Refund Worth (₦)',
            'Return Reason',
            'Cashier / Staff',
            'Customer Name',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($returns as $idx => $r) {
            $staffName = $r->user?->full_name ?: ($r->user?->name ?: ($r->user?->username ?: '-'));
            $custName  = $r->sale?->customer?->name ?: 'Walk-in / Customer';
            $receiptId = $r->sale?->receipt_id ?: 'Direct Return';
            $barcode   = $r->item?->barcode_number ?: '-';

            $unitMultiplier = 1;
            if ($r->unit_multiplier) {
                $unitMultiplier = (int) $r->unit_multiplier;
            } elseif ($r->item) {
                $u = strtolower($r->unit_used ?? 'unit');
                $unitMultiplier = $r->item->toBaseUnits(1, $u);
            }
            $baseUnits = ((int) $r->qty) * $unitMultiplier;

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $r->created_at ? $r->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue([$col++, $rowNum], $receiptId);
            $sheet->setCellValue([$col++, $rowNum], $r->item_name ?: ($r->item?->item_name ?? '-'));
            $sheet->setCellValue([$col++, $rowNum], $barcode);
            $sheet->setCellValue([$col++, $rowNum], (int) $r->qty);
            $sheet->setCellValue([$col++, $rowNum], $r->unit_used ?: 'unit');
            $sheet->setCellValue([$col++, $rowNum], (int) $baseUnits);
            $sheet->setCellValue([$col++, $rowNum], (float) $r->refund_amount);
            $sheet->setCellValue([$col++, $rowNum], $r->return_reason ?: 'No reason provided');
            $sheet->setCellValue([$col++, $rowNum], $staffName);
            $sheet->setCellValue([$col++, $rowNum], $custName);

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return $this->streamExcelOrCsv($spreadsheet, 'returned_items_' . $branch->slug, $request->get('format', 'xlsx'));
    }

    public function dailySales(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = \App\Services\RoleService::canViewAllReports();

        [$startDate, $endDate] = $this->parseDateRange($request, 'today');
        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        // Summary computed on full dataset (no pagination)
        $allSales = Sale::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('receipt_id', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhere('phone', 'like', $term))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('full_name', 'like', $term));
                });
            })
            ->get(['final_total', 'discount_amount', 'profit_made']);

        $allReturns = SaleReturnItem::whereHas('item', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->get(['qty', 'refund_amount']);

        $summary = [
            'total_sales'        => $allSales->count(),
            'total_revenue'      => (float) $allSales->sum('final_total'),
            'total_discount'     => (float) $allSales->sum('discount_amount'),
            'total_profit'       => (float) $allSales->sum('profit_made'),
            'total_tax'          => 0,
            'total_return_count' => $allReturns->count(),
            'total_return_qty'   => (int) $allReturns->sum('qty'),
            'total_return_worth' => (float) $allReturns->sum('refund_amount'),
        ];

        // Paginated rows (25 per page)
        $sales = Sale::with(['customer', 'user', 'saleOrders'])
            ->where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('receipt_id', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhere('phone', 'like', $term))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('full_name', 'like', $term));
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        $users = $canSeeAll
            ? User::where('branch_id', $branch->id)
                ->get(['id', 'name', 'full_name', 'username'])
            : [];

        return Inertia::render('Reports/DailySales', [
            'sales'     => $sales,
            'summary'   => $summary,
            'users'     => $users,
            'canSeeAll' => $canSeeAll,
            'filters'   => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'from'       => $startDate->format('Y-m-d'),
                'to'         => $endDate->format('Y-m-d'),
                'user_id'    => $selectedUserId ?? '',
                'search'     => $request->get('search', ''),
            ]
        ]);
    }

    public function dailySalesExport(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = \App\Services\RoleService::canViewAllReports();
        $canSeeProfit = \App\Services\RoleService::canViewProfitLoss();

        [$startDate, $endDate] = $this->parseDateRange($request, 'today');
        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        $sales = Sale::with(['customer', 'user', 'saleOrders'])
            ->where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('receipt_id', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhere('phone', 'like', $term))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('full_name', 'like', $term));
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Report');

        $headers = [
            '#',
            'Receipt ID',
            'Date & Time',
            'Customer Name',
            'Customer Phone',
            'Cashier / Staff',
            'Total Items Sold',
            'Payment Method',
            'Subtotal (₦)',
            'Discount (₦)',
            'Total Amount (₦)',
        ];

        if ($canSeeProfit) {
            $headers[] = 'Profit (₦)';
        }
        $headers[] = 'Status / Type';

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($sales as $idx => $s) {
            $staffName = $s->user?->full_name ?: ($s->user?->name ?: ($s->user?->username ?: '-'));
            $custName  = $s->customer?->name ?: 'Walk-in Customer';
            $custPhone = $s->customer?->phone ?: '-';
            $itemCount = $s->saleOrders ? $s->saleOrders->sum('qty') : 0;

            $payMethod = 'Cash';
            if ($s->is_debt) {
                $payMethod = 'Debt / Credit';
            } elseif ((float) $s->cash > 0 && (float) $s->bank_transfer > 0) {
                $payMethod = 'Split (Cash + Bank)';
            } elseif ((float) $s->bank_transfer > 0) {
                $payMethod = 'Bank Transfer';
            }

            $status = $s->is_debt ? 'Unpaid Debt' : 'Completed';

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $s->receipt_id ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $s->created_at ? $s->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue([$col++, $rowNum], $custName);
            $sheet->setCellValue([$col++, $rowNum], $custPhone);
            $sheet->setCellValue([$col++, $rowNum], $staffName);
            $sheet->setCellValue([$col++, $rowNum], (int) $itemCount);
            $sheet->setCellValue([$col++, $rowNum], $payMethod);
            $sheet->setCellValue([$col++, $rowNum], (float) ($s->amount_cost ?: $s->final_total));
            $sheet->setCellValue([$col++, $rowNum], (float) $s->discount_amount);
            $sheet->setCellValue([$col++, $rowNum], (float) $s->final_total);

            if ($canSeeProfit) {
                $sheet->setCellValue([$col++, $rowNum], (float) $s->profit_made);
            }
            $sheet->setCellValue([$col++, $rowNum], $status);

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return $this->streamExcelOrCsv($spreadsheet, 'sales_report_' . $branch->slug, $request->get('format', 'xlsx'));
    }

    public function profitLoss(Request $request)
    {
        $branch = current_branch();
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate   = $request->end_date   ? Carbon::parse($request->end_date)->endOfDay()     : Carbon::today()->endOfDay();

        $saleItems = \App\Models\SaleOrder::with(['sale', 'item'])
            ->whereHas('sale', function ($q) use ($startDate, $endDate, $branch) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->when($branch, fn ($bQ) => $bQ->where('branch_id', $branch->id));
            })
            ->get();

        $totalRevenue = 0;
        $totalCost    = 0;

        foreach ($saleItems as $orderItem) {
            $totalRevenue += (float) $orderItem->total_selling_price;
            if ($orderItem->item) {
                $baseQty = $orderItem->item->toBaseUnits((int) $orderItem->qty, $orderItem->unit_used ?? 'unit');
                $totalCost += ((float) $orderItem->item->buy_price * $baseQty);
            }
        }

        // Subtotal discounts subtracted from total revenue
        $salesQuery = Sale::whereBetween('created_at', [$startDate, $endDate])
            ->when($branch, fn ($bQ) => $bQ->where('branch_id', $branch->id));

        $totalDiscounts = (float) $salesQuery->sum('discount_amount');
        $netRevenue     = max(0, $totalRevenue - $totalDiscounts);
        $grossProfit    = $netRevenue - $totalCost;

        $summary = [
            'total_revenue'   => $netRevenue,
            'gross_revenue'   => $totalRevenue,
            'total_discounts' => $totalDiscounts,
            'total_cost'      => $totalCost,
            'gross_profit'    => $grossProfit,
            'profit_margin'   => $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0,
        ];

        return Inertia::render('Reports/ProfitLoss', [
            'summary' => $summary,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d')
            ]
        ]);
    }

    public function lowStock(Request $request)
    {
        $branch = current_branch();
        $defaultThreshold = (int) ($branch->settings['out_of_stock'] ?? 10);
        $threshold = $request->filled('threshold') ? (int) $request->threshold : $defaultThreshold;

        $query = Item::with('category:id,name')
            ->where('branch_id', $branch->id)
            ->where(DB::raw('(front_store_qty + back_store_qty)'), '<=', $threshold);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('item_name', 'like', $term)
                  ->orWhere('barcode_number', 'like', $term);
            });
        }

        $items = $query->orderBy(DB::raw('(front_store_qty + back_store_qty)'), 'asc')
            ->paginate(25)
            ->through(fn ($item) => array_merge($item->toArray(), [
                'qty'            => $item->total_qty,
                'alert_quantity' => $threshold,
                'category_name'  => $item->category?->name ?? 'Uncategorized',
            ]))
            ->withQueryString();

        $categories = \App\Models\Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Reports/LowStock', [
            'items'      => $items,
            'categories' => $categories,
            'filters'    => [
                'threshold'   => $threshold,
                'category_id' => $request->get('category_id', ''),
                'search'      => $request->get('search', ''),
            ]
        ]);
    }

    public function lowStockExport(Request $request)
    {
        $branch = current_branch();
        $defaultThreshold = (int) ($branch->settings['out_of_stock'] ?? 10);
        $threshold = $request->filled('threshold') ? (int) $request->threshold : $defaultThreshold;

        $items = Item::with('category:id,name')
            ->where('branch_id', $branch->id)
            ->where(DB::raw('(front_store_qty + back_store_qty)'), '<=', $threshold)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('item_name', 'like', $term)
                        ->orWhere('barcode_number', 'like', $term);
                });
            })
            ->orderBy(DB::raw('(front_store_qty + back_store_qty)'), 'asc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Low Stock Alerts');

        $headers = [
            '#',
            'Barcode / SKU',
            'Item Name',
            'Category',
            'Front Store Qty',
            'Back Store Qty',
            'Total In-Stock Qty',
            'Alert Threshold',
            'Unit',
            'Cost Price (₦)',
            'Selling Price (₦)',
            'Stock Status',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($items as $idx => $item) {
            $totalStock = (int) $item->front_store_qty + (int) $item->back_store_qty;
            $status = $totalStock <= 0 ? 'CRITICAL / OUT OF STOCK' : 'LOW STOCK';

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $item->barcode_number ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $item->item_name);
            $sheet->setCellValue([$col++, $rowNum], $item->category?->name ?: 'Uncategorized');
            $sheet->setCellValue([$col++, $rowNum], (int) $item->front_store_qty);
            $sheet->setCellValue([$col++, $rowNum], (int) $item->back_store_qty);
            $sheet->setCellValue([$col++, $rowNum], $totalStock);
            $sheet->setCellValue([$col++, $rowNum], $threshold);
            $sheet->setCellValue([$col++, $rowNum], $item->unit_label ?: ($item->unit ?: 'unit'));
            $sheet->setCellValue([$col++, $rowNum], (float) $item->buy_price);
            $sheet->setCellValue([$col++, $rowNum], (float) $item->unit_price);
            $sheet->setCellValue([$col++, $rowNum], $status);

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return $this->streamExcelOrCsv($spreadsheet, 'low_stock_alerts_' . $branch->slug, $request->get('format', 'xlsx'));
    }

    public function customerDebt(Request $request)
    {
        $branch = current_branch();

        // Only customers who have made purchases or debts in this branch
        $branchCustomerIds = Sale::where('branch_id', $branch->id)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique();

        $query = PosCustomer::whereIn('id', $branchCustomerIds)
            ->where('debt_bal', '>', 0);

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        if ($request->filled('min_debt')) {
            $query->where('debt_bal', '>=', (float) $request->min_debt);
        }

        if ($request->filled('watchlist')) {
            if ($request->watchlist === '1') {
                $query->where('watch_list', true);
            } elseif ($request->watchlist === '0') {
                $query->where('watch_list', false);
            }
        }

        $totalDebt = (clone $query)->sum('debt_bal');

        $customers = $query->orderBy('debt_bal', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Reports/CustomerDebt', [
            'customers'  => $customers,
            'total_debt' => (float) $totalDebt,
            'filters'    => [
                'search'    => $request->get('search', ''),
                'min_debt'  => $request->get('min_debt', ''),
                'watchlist' => $request->get('watchlist', ''),
            ]
        ]);
    }

    public function customerDebtExport(Request $request)
    {
        $branch = current_branch();

        $branchCustomerIds = Sale::where('branch_id', $branch->id)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique();

        $customers = PosCustomer::whereIn('id', $branchCustomerIds)
            ->where('debt_bal', '>', 0)
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . trim($request->search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($request->filled('min_debt'), fn ($q) => $q->where('debt_bal', '>=', (float) $request->min_debt))
            ->when($request->filled('watchlist'), function ($q) use ($request) {
                if ($request->watchlist === '1') $q->where('watch_list', true);
                elseif ($request->watchlist === '0') $q->where('watch_list', false);
            })
            ->orderBy('debt_bal', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customer Debts Report');

        $headers = [
            '#',
            'Customer Name',
            'Phone Number',
            'Email Address',
            'Total Outstanding Debt (₦)',
            'Watchlist Status',
            'Registered / Customer Since',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($customers as $idx => $c) {
            $watchlist = $c->watch_list ? 'YES (Watchlist)' : 'No';

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $c->name);
            $sheet->setCellValue([$col++, $rowNum], $c->phone ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $c->email ?: '-');
            $sheet->setCellValue([$col++, $rowNum], (float) $c->debt_bal);
            $sheet->setCellValue([$col++, $rowNum], $watchlist);
            $sheet->setCellValue([$col++, $rowNum], $c->created_at ? $c->created_at->format('Y-m-d H:i:s') : '-');

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        return $this->streamExcelOrCsv($spreadsheet, 'customer_debts_' . $branch->slug, $request->get('format', 'xlsx'));
    }

    public function endOfDay(Request $request)
    {
        $branch    = current_branch();
        $startDate = Carbon::today()->startOfDay();
        $endDate   = Carbon::today()->endOfDay();

        $canSeeAll   = \App\Services\RoleService::canViewAllReports();
        $currentUser = auth()->user();

        if ($canSeeAll) {
            $selectedUserId = $request->get('user_id') ?: null;
        } else {
            $selectedUserId = $currentUser->id;
        }

        // ── Summary on full dataset (before pagination) ────────────────────────
        $allSalesToday = Sale::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->get(['final_total', 'discount_amount', 'cash', 'bank_transfer', 'is_debt']);

        $debtRecovered = \App\Models\DebtPayment::whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->where('branch_id', $branch->id)
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->sum('amount');

        $allReturnsToday = SaleReturnItem::whereHas('item', fn ($q) => $q->where('branch_id', $branch->id))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->get(['qty', 'refund_amount']);

        $summary = [
            'total_sales'        => $allSalesToday->count(),
            'total_revenue'      => (float) $allSalesToday->sum('final_total'),
            'total_discount'     => (float) $allSalesToday->sum('discount_amount'),
            'cash_collected'     => (float) $allSalesToday->sum('cash'),
            'bank_collected'     => (float) $allSalesToday->sum('bank_transfer'),
            'debt_recorded'      => (float) $allSalesToday->where('is_debt', true)->sum('final_total'),
            'debt_recovered'     => (float) $debtRecovered,
            'total_return_count' => $allReturnsToday->count(),
            'total_return_qty'   => (int) $allReturnsToday->sum('qty'),
            'total_return_worth' => (float) $allReturnsToday->sum('refund_amount'),
        ];

        // Item level sales breakdown for selected user / all users today
        $itemBreakdown = \App\Models\SaleOrder::with('item')
            ->whereHas('sale', function ($q) use ($startDate, $endDate, $branch, $selectedUserId) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->where('branch_id', $branch->id)
                  ->when($selectedUserId, fn ($sQ) => $sQ->where('user_id', $selectedUserId));
            })
            ->select('item_id', 'item_name', 'unit_used',
                DB::raw('SUM(qty) as total_qty'),
                DB::raw('SUM(total_selling_price) as total_amount')
            )
            ->groupBy('item_id', 'item_name', 'unit_used')
            ->orderBy('total_amount', 'desc')
            ->get();

        // ── Paginated sales ────────────────────────────────────────────────────
        $sales = Sale::with(['customer', 'user'])
            ->where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'sales_page')
            ->withQueryString();

        // ── Paginated debt payments ────────────────────────────────────────────
        $debtPayments = \App\Models\DebtPayment::with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->where('branch_id', $branch->id)
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'debt_page')
            ->withQueryString();

        $users = $canSeeAll
            ? User::where('branch_id', $branch->id)
                ->get(['id', 'name', 'full_name', 'username'])
            : [];

        return Inertia::render('Reports/EndOfDay', [
            'sales'         => $sales,
            'debtPayments'  => $debtPayments,
            'summary'       => $summary,
            'itemBreakdown' => $itemBreakdown,
            'users'         => $users,
            'canSeeAll'     => $canSeeAll,
            'filters'       => [
                'user_id' => $canSeeAll ? ($selectedUserId ?? '') : $currentUser->id,
            ],
        ]);
    }

    public function standardReport(Request $request)
    {
        return redirect()->route('pos.reports.index');
    }
}
