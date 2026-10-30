<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrgReportController extends Controller
{
    /**
     * Resolve the active organization for the current user.
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = Auth::guard('web')->user();

        if (!$user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            if ($request->has('org_id')) {
                return Organization::find($request->get('org_id')) ?: Organization::first();
            }
            return $user->organization ?: Organization::first();
        }

        return $user->organization;
    }

    /**
     * Parse date range from request with support for quick presets.
     */
    protected function parseDateRange(Request $request): array
    {
        $preset = $request->input('preset');
        $fromInput = $request->input('from');
        $toInput   = $request->input('to');

        if ($preset) {
            switch ($preset) {
                case 'today':
                    return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
                case 'yesterday':
                    return [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()];
                case 'week':
                    return [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfDay()];
                case 'month':
                    return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfDay()];
                case 'last_month':
                    return [
                        Carbon::now()->subMonth()->startOfMonth()->startOfDay(),
                        Carbon::now()->subMonth()->endOfMonth()->endOfDay(),
                    ];
                case '30_days':
                    return [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay()];
                case 'all':
                    return [Carbon::create(2020, 1, 1)->startOfDay(), Carbon::now()->endOfDay()];
            }
        }

        if ($fromInput && $toInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::parse($toInput)->endOfDay();
        } elseif ($fromInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::today()->endOfDay();
        } else {
            // Default to current month
            $startDate = Carbon::now()->startOfMonth()->startOfDay();
            $endDate   = Carbon::now()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    /**
     * Display Organization-level Sales & Financial Reports.
     * Consolidates transactions, P&L, branch comparison, expenses, and peak hourly insights.
     */
    public function index(Request $request): Response
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        // All branches belonging to this organization
        $orgBranches = Branch::where('organization_id', $org->id)
            ->orderBy('name')
            ->get();

        $allBranchIds = $orgBranches->pluck('id')->all();

        // Optional filter: specific branch within organization
        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId && in_array((int)$selectedBranchId, $allBranchIds)) {
            $targetBranchIds = [(int)$selectedBranchId];
            $selectedBranchId = (int)$selectedBranchId;
        } else {
            $targetBranchIds = $allBranchIds;
            $selectedBranchId = null;
        }

        // ── 1. Date Range Resolution ──────────────────────────────────────────
        [$startDate, $endDate] = $this->parseDateRange($request);

        // ── 2. Scoped Sales Base Query ────────────────────────────────────────
        $salesQuery = Sale::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('created_at', [$startDate, $endDate]);

        // ── 3. High-level KPIs & P&L Summary ──────────────────────────────────
        $totalRevenue   = (float) (clone $salesQuery)->sum('final_total');
        $totalOrders    = (int)   (clone $salesQuery)->count();
        $totalGrossProfit = (float) (clone $salesQuery)->sum('profit_made');
        $totalDiscounts = (float) (clone $salesQuery)->sum('discount_amount');
        $avgBasketSize  = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
        $grossMarginPct = $totalRevenue > 0 ? round(($totalGrossProfit / $totalRevenue) * 100, 1) : 0;

        // Operating Expenses in period
        $expensesQuery = Expense::whereIn('expenses.branch_id', $targetBranchIds)
            ->whereBetween('expenses.expense_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

        $totalExpenses = (float) (clone $expensesQuery)->sum('expenses.amount');
        $netProfit     = $totalGrossProfit - $totalExpenses;
        $netMarginPct  = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

        // Returns in period
        $returnsQuery = SaleReturnItem::whereHas('sale', fn($q) => $q->whereIn('branch_id', $targetBranchIds))
            ->whereBetween('created_at', [$startDate, $endDate]);
        $returnsCount = (int) (clone $returnsQuery)->count();
        $returnsWorth = (float) (clone $returnsQuery)->sum('refund_amount');

        // ── 4. Payment Method Breakdown ───────────────────────────────────────
        $paymentsBreakdown = (clone $salesQuery)
            ->select('payment_method', DB::raw('SUM(final_total) as total_amount'), DB::raw('COUNT(id) as count'))
            ->groupBy('payment_method')
            ->get()
            ->map(fn($p) => [
                'method'    => $p->payment_method ?: 'Other',
                'amount'    => (float) $p->total_amount,
                'count'     => (int) $p->count,
                'share_pct' => $totalRevenue > 0 ? round(($p->total_amount / $totalRevenue) * 100, 1) : 0,
            ]);

        // ── 5. Expense Breakdown by Category ──────────────────────────────────
        $expensesBreakdown = (clone $expensesQuery)
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->select(
                DB::raw('COALESCE(expense_categories.name, "Uncategorized") as category_name'),
                DB::raw('SUM(expenses.amount) as total_amount'),
                DB::raw('COUNT(expenses.id) as count')
            )
            ->groupBy('expense_categories.name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn($e) => [
                'category'  => $e->category_name,
                'amount'    => (float) $e->total_amount,
                'count'     => (int) $e->count,
                'share_pct' => $totalExpenses > 0 ? round(($e->total_amount / $totalExpenses) * 100, 1) : 0,
            ]);

        // ── 6. Trend Over Time (Daily / Hourly) ───────────────────────────────
        $diffInDays = $startDate->diffInDays($endDate);
        $salesTrend = [];

        if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
            // Single day -> Hourly (08:00 - 22:00)
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

            for ($h = 8; $h <= 22; $h++) {
                $match = $hourlyTrend->firstWhere('hour', $h);
                $salesTrend[] = [
                    'label'   => sprintf('%02d:00', $h),
                    'revenue' => (float) ($match->revenue ?? 0),
                    'profit'  => (float) ($match->profit ?? 0),
                    'count'   => (int)   ($match->count ?? 0),
                ];
            }
        } else {
            // Multi-day -> Daily Breakdown
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

            $period = Carbon::parse($startDate)->toPeriod($endDate);
            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                $match = $dailyTrend->firstWhere('date', $dateStr);
                $salesTrend[] = [
                    'label'   => $date->format('M j'),
                    'revenue' => (float) ($match->revenue ?? 0),
                    'profit'  => (float) ($match->profit ?? 0),
                    'count'   => (int)   ($match->count ?? 0),
                ];
            }
        }

        // ── 7. Hourly Peak Trading Pattern (Customer Traffic Analysis) ────────
        // Aggregates across the entire period to show what times of day generate the most revenue
        $peakHoursData = [];
        $hourlyAggregate = (clone $salesQuery)
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('SUM(final_total) as revenue'),
                DB::raw('COUNT(id) as orders_count')
            )
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->get();

        for ($h = 8; $h <= 22; $h++) {
            $match = $hourlyAggregate->firstWhere('hour', $h);
            $peakHoursData[] = [
                'hour'    => sprintf('%02d:00', $h),
                'revenue' => (float) ($match->revenue ?? 0),
                'orders'  => (int)   ($match->orders_count ?? 0),
            ];
        }

        // ── 8. Branch Performance Comparison & Leaderboard ───────────────────
        $branchComparison = [];
        if (!empty($allBranchIds)) {
            // Sales by branch
            $branchSales = Sale::whereIn('branch_id', $allBranchIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    'branch_id',
                    DB::raw('SUM(final_total) as total_revenue'),
                    DB::raw('SUM(profit_made) as total_profit'),
                    DB::raw('COUNT(id) as total_orders')
                )
                ->groupBy('branch_id')
                ->get()
                ->keyBy('branch_id');

            // Expenses by branch
            $branchExpenses = Expense::whereIn('branch_id', $allBranchIds)
                ->whereBetween('expense_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->select(
                    'branch_id',
                    DB::raw('SUM(amount) as total_expense')
                )
                ->groupBy('branch_id')
                ->get()
                ->keyBy('branch_id');

            $allBranchesRevenue = $branchSales->sum('total_revenue') ?: $totalRevenue;

            foreach ($orgBranches as $branch) {
                $bSale = $branchSales->get($branch->id);
                $bExp  = $branchExpenses->get($branch->id);

                $rev     = (float) ($bSale->total_revenue ?? 0);
                $profit  = (float) ($bSale->total_profit ?? 0);
                $orders  = (int)   ($bSale->total_orders ?? 0);
                $exp     = (float) ($bExp->total_expense ?? 0);
                $netP    = $profit - $exp;
                $share   = $allBranchesRevenue > 0 ? round(($rev / $allBranchesRevenue) * 100, 1) : 0;
                $basket  = $orders > 0 ? round($rev / $orders, 2) : 0;

                $branchComparison[] = [
                    'branch_id'   => $branch->id,
                    'branch_name' => $branch->name,
                    'slug'        => $branch->slug,
                    'revenue'     => $rev,
                    'gross_profit'=> $profit,
                    'expenses'    => $exp,
                    'net_profit'  => $netP,
                    'orders'      => $orders,
                    'avg_basket'  => $basket,
                    'share_pct'   => $share,
                ];
            }

            usort($branchComparison, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        }

        // ── 9. Top Selling Products ──────────────────────────────────────────
        $topItems = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
            ->whereIn('sales.branch_id', $targetBranchIds)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'sale_orders.item_id',
                'sale_orders.item_name',
                DB::raw('SUM(sale_orders.qty) as total_qty_sold'),
                DB::raw('SUM(sale_orders.total_selling_price) as total_revenue')
            )
            ->groupBy('sale_orders.item_id', 'sale_orders.item_name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'name'     => $item->item_name ?: 'Unnamed Item',
                'qty_sold' => (int) $item->total_qty_sold,
                'revenue'  => (float) $item->total_revenue,
            ]);

        // ── 10. Paginated Transaction Records ────────────────────────────────
        $search = $request->input('search');

        $salesPaginatedQuery = (clone $salesQuery)
            ->with(['branch:id,name,slug', 'user:id,name,username', 'customer:id,name'])
            ->when($search, function ($q) use ($search) {
                $term = '%' . trim($search) . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('receipt_id', 'like', $term)
                        ->orWhereHas('customer', fn($c) => $c->where('name', 'like', $term))
                        ->orWhereHas('user', fn($u) => $u->where('name', 'like', $term)->orWhere('username', 'like', $term));
                });
            })
            ->latest('id');

        $sales = $salesPaginatedQuery->paginate(15)
            ->withQueryString()
            ->through(fn(Sale $s) => [
                'id'             => $s->id,
                'receipt_number' => $s->receipt_id ?? "REC-{$s->id}",
                'branch_name'    => $s->branch?->name ?? 'Unknown Branch',
                'cashier_name'   => $s->user?->name ?? ($s->user?->username ?? 'Staff'),
                'customer_name'  => $s->customer?->name ?? 'Walk-in Customer',
                'final_total'    => (float) $s->final_total,
                'profit_made'    => (float) $s->profit_made,
                'payment_method' => $s->payment_method ?: 'Cash',
                'is_debt'        => (bool) $s->is_debt,
                'created_at'     => $s->created_at?->format('d M Y, h:i A'),
            ]);

        return Inertia::render('Org/Reports/Index', [
            'organization' => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
            'branches' => $orgBranches,
            'filters'  => [
                'branch_id' => $selectedBranchId,
                'from'      => $startDate->format('Y-m-d'),
                'to'        => $endDate->format('Y-m-d'),
                'search'    => $search ?: '',
            ],
            'kpis' => [
                'total_revenue'      => $totalRevenue,
                'total_orders'       => $totalOrders,
                'total_gross_profit' => $totalGrossProfit,
                'gross_margin_pct'   => $grossMarginPct,
                'total_expenses'     => $totalExpenses,
                'net_profit'         => $netProfit,
                'net_margin_pct'     => $netMarginPct,
                'total_discounts'    => $totalDiscounts,
                'avg_basket_size'    => $avgBasketSize,
                'returns_count'      => $returnsCount,
                'returns_worth'      => $returnsWorth,
            ],
            'payments_breakdown' => $paymentsBreakdown,
            'expenses_breakdown' => $expensesBreakdown,
            'sales_trend'        => $salesTrend,
            'peak_hours'         => $peakHoursData,
            'branch_comparison'  => $branchComparison,
            'top_items'          => $topItems,
            'sales'              => $sales,
        ]);
    }

    /**
     * Display Organization-level End of Day (Z-Report / Daily Reconciliation).
     * Provides financial reconciliation, cash drawer balance, cashier shift totals, and branch close-outs.
     */
    public function endOfDay(Request $request): Response
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        // All branches belonging to this organization
        $orgBranches = Branch::where('organization_id', $org->id)
            ->with(['settings'])
            ->orderBy('name')
            ->get();
        $allBranchIds = $orgBranches->pluck('id')->all();

        // Selected branch filter (optional)
        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId && in_array((int)$selectedBranchId, $allBranchIds)) {
            $targetBranchIds = [(int)$selectedBranchId];
            $selectedBranchId = (int)$selectedBranchId;
        } else {
            $targetBranchIds = $allBranchIds;
            $selectedBranchId = null;
        }

        // Selected Date (defaults to Today)
        $dateInput = $request->input('date');
        $targetDate = $dateInput ? Carbon::parse($dateInput) : Carbon::today();
        $startDate  = $targetDate->copy()->startOfDay();
        $endDate    = $targetDate->copy()->endOfDay();

        // ── 1. Daily POS Sales Summary ────────────────────────────────────────
        $daySales = Sale::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get(['id', 'branch_id', 'user_id', 'final_total', 'discount_amount', 'profit_made', 'cash', 'bank_transfer', 'is_debt', 'payment_method']);

        $posSalesCount       = $daySales->count();
        $posSalesRevenue     = (float) $daySales->sum('final_total');
        $posDiscounts        = (float) $daySales->sum('discount_amount');
        $cashCollectedSales  = (float) $daySales->sum('cash');
        $bankTransferSales   = (float) $daySales->sum('bank_transfer');
        $debtRecordedSales   = (float) $daySales->where('is_debt', true)->sum('final_total');
        $posGrossProfit      = (float) $daySales->sum('profit_made');

        // ── 2. Daily Laundry Activity Summary ─────────────────────────────────
        $dayLaundryPayments = LaundryPayment::with(['recorder:id,name,username,branch_id', 'order:id,order_number,branch_id'])
            ->whereIn('branch_id', $targetBranchIds)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $dayLaundryOrders = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('received_at', [$startDate, $endDate])
            ->get();

        $laundryOrdersCount     = $dayLaundryOrders->count();
        $laundryDiscounts       = (float) $dayLaundryOrders->sum('discount_amount');
        $laundryOrdersTotal     = (float) $dayLaundryOrders->sum('total');
        $laundryDebtRecorded    = (float) $dayLaundryOrders->where('status', '!=', 'cancelled')->sum('balance');

        $laundryPaymentsOnly    = $dayLaundryPayments->where('type', 'payment');
        $laundryRefundsOnly     = $dayLaundryPayments->where('type', 'refund');

        $laundryGrossCollected  = (float) $laundryPaymentsOnly->sum('amount');
        $laundryRefundsTotal    = (float) $laundryRefundsOnly->sum('amount');
        $laundryNetCollections  = max(0.0, round($laundryGrossCollected - $laundryRefundsTotal, 2));

        $cashCollectedLaundry   = (float) $laundryPaymentsOnly->filter(function ($p) {
            $m = strtolower($p->method ?? '');
            return str_contains($m, 'cash');
        })->sum('amount');

        $bankTransferLaundry    = (float) $laundryPaymentsOnly->filter(function ($p) {
            $m = strtolower($p->method ?? '');
            return !str_contains($m, 'cash');
        })->sum('amount');

        $laundryCashRefunds     = (float) $laundryRefundsOnly->filter(function ($p) {
            $m = strtolower($p->method ?? '');
            return empty($m) || str_contains($m, 'cash');
        })->sum('amount');

        // ── 3. Debt Collections (Debt payments received today) ────────────────
        $debtPayments = DebtPayment::with(['customer:id,name', 'user:id,name', 'branch:id,name'])
            ->whereIn('branch_id', $targetBranchIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->get();

        $debtRecoveredTotal = (float) $debtPayments->sum('amount');

        // ── 4. Customer Returns / Refunds Today ───────────────────────────────
        $returnsToday = SaleReturnItem::with(['sale:id,receipt_id,branch_id', 'item:id,item_name', 'user:id,name'])
            ->whereHas('sale', fn($q) => $q->whereIn('branch_id', $targetBranchIds))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $posReturnsCount   = $returnsToday->count();
        $posReturnsWorth   = (float) $returnsToday->sum('refund_amount');
        $totalReturnsCount = $posReturnsCount + $laundryRefundsOnly->count();
        $totalReturnsWorth = $posReturnsWorth + $laundryRefundsTotal;

        // ── 5. Daily Operating Expenses ───────────────────────────────────────
        $expensesToday = Expense::with(['category:id,name', 'user:id,name', 'branch:id,name'])
            ->whereIn('branch_id', $targetBranchIds)
            ->whereBetween('expense_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get();

        $expensesTotal = (float) $expensesToday->sum('amount');

        // ── 6. Unified Cash Drawer Reconciliation Calculations ────────────────
        $totalCashSales      = $cashCollectedSales + $cashCollectedLaundry;
        $totalBankSales      = $bankTransferSales + $bankTransferLaundry;
        $totalCashInflow     = $totalCashSales + $debtRecoveredTotal;
        $totalCashOutflow    = ($posReturnsWorth + $laundryCashRefunds) + $expensesTotal;
        $netCashPosition     = $totalCashInflow - $totalCashOutflow;

        $totalRevenueTakings = $posSalesRevenue + $laundryNetCollections;
        $totalOrdersCount    = $posSalesCount + $laundryOrdersCount;
        $totalDiscounts      = $posDiscounts + $laundryDiscounts;
        $totalDebtRecorded   = $debtRecordedSales + $laundryDebtRecorded;
        $netDailyTakings     = ($totalRevenueTakings + $debtRecoveredTotal) - ($totalReturnsWorth + $expensesTotal);

        $reconciliation = [
            'total_sales_revenue'   => round($totalRevenueTakings, 2),
            'total_sales_count'     => $totalOrdersCount,
            'total_discounts'       => round($totalDiscounts, 2),
            'cash_sales'            => round($totalCashSales, 2),
            'bank_transfer_sales'   => round($totalBankSales, 2),
            'debt_recorded'         => round($totalDebtRecorded, 2),
            'debt_recovered'        => round($debtRecoveredTotal, 2),
            'returns_count'         => $totalReturnsCount,
            'returns_worth'         => round($totalReturnsWorth, 2),
            'expenses_total'        => round($expensesTotal, 2),
            'total_cash_inflow'     => round($totalCashInflow, 2),
            'total_cash_outflow'    => round($totalCashOutflow, 2),
            'net_cash_position'     => round($netCashPosition, 2),
            'net_daily_takings'     => round($netDailyTakings, 2),
            'gross_profit'          => round($posGrossProfit + $laundryNetCollections, 2),
            'net_profit'            => round(($posGrossProfit + $laundryNetCollections) - $expensesTotal, 2),
            // Explicit sub-breakdowns for transparent UI/Print
            'pos_revenue'           => round($posSalesRevenue, 2),
            'laundry_revenue'       => round($laundryNetCollections, 2),
            'pos_cash'              => round($cashCollectedSales, 2),
            'laundry_cash'          => round($cashCollectedLaundry, 2),
            'pos_bank'              => round($bankTransferSales, 2),
            'laundry_bank'          => round($bankTransferLaundry, 2),
            'pos_orders_count'      => $posSalesCount,
            'laundry_orders_count'  => $laundryOrdersCount,
            'pos_returns'           => round($posReturnsWorth, 2),
            'laundry_refunds'       => round($laundryRefundsTotal, 2),
        ];

        // ── 7. Branch-by-Branch Breakdown (With Operating Mode Awareness) ─────
        $branchBreakdown = [];
        foreach ($orgBranches as $branch) {
            $isPos = $branch->posEnabled();
            $isLaundry = $branch->laundryEnabled();
            $opMode = ($isPos && $isLaundry) ? 'both' : ($isLaundry ? 'laundry_only' : 'pos_only');

            // POS for branch
            $bSales = $daySales->where('branch_id', $branch->id);
            $bDebts = $debtPayments->where('branch_id', $branch->id);
            $bRet   = $returnsToday->filter(fn($r) => $r->sale?->branch_id === $branch->id);
            $bExp   = $expensesToday->where('branch_id', $branch->id);

            $bPosRev  = (float) $bSales->sum('final_total');
            $bPosCash = (float) $bSales->sum('cash');
            $bPosBank = (float) $bSales->sum('bank_transfer');
            $bDebtRec = (float) $bDebts->sum('amount');
            $bRetW    = (float) $bRet->sum('refund_amount');
            $bExpW    = (float) $bExp->sum('amount');

            // Laundry for branch
            $bLdyOrders  = $dayLaundryOrders->where('branch_id', $branch->id);
            $bLdyPay     = $dayLaundryPayments->where('branch_id', $branch->id);
            $bLdyPayOnly = $bLdyPay->where('type', 'payment');
            $bLdyRefOnly = $bLdyPay->where('type', 'refund');

            $bLdyGross   = (float) $bLdyPayOnly->sum('amount');
            $bLdyRef     = (float) $bLdyRefOnly->sum('amount');
            $bLdyNet     = max(0.0, round($bLdyGross - $bLdyRef, 2));

            $bLdyCash    = (float) $bLdyPayOnly->filter(fn($p) => str_contains(strtolower($p->method ?? ''), 'cash'))->sum('amount');
            $bLdyBank    = (float) $bLdyPayOnly->filter(fn($p) => !str_contains(strtolower($p->method ?? ''), 'cash'))->sum('amount');
            $bLdyCashRef = (float) $bLdyRefOnly->filter(fn($p) => empty($p->method) || str_contains(strtolower($p->method ?? ''), 'cash'))->sum('amount');

            // Combined Branch Metrics
            $bTotalRev     = round($bPosRev + $bLdyNet, 2);
            $bTotalCash    = round($bPosCash + $bLdyCash, 2);
            $bTotalBank    = round($bPosBank + $bLdyBank, 2);
            $bTotalReturns = round($bRetW + $bLdyRef, 2);
            $bNetCash      = round(($bTotalCash + $bDebtRec) - ($bRetW + $bLdyCashRef + $bExpW), 2);
            $bOrdersCount  = $bSales->count() + $bLdyOrders->count();

            $branchBreakdown[] = [
                'branch_id'            => $branch->id,
                'branch_name'          => $branch->name,
                'slug'                 => $branch->slug,
                'operating_mode'       => $opMode,
                'sales_count'          => $bOrdersCount,
                'pos_orders_count'     => $bSales->count(),
                'laundry_orders_count' => $bLdyOrders->count(),
                'revenue'              => $bTotalRev,
                'pos_revenue'          => $bPosRev,
                'laundry_revenue'      => $bLdyNet,
                'cash'                 => $bTotalCash,
                'bank'                 => $bTotalBank,
                'debt_recovered'       => $bDebtRec,
                'returns'              => $bTotalReturns,
                'expenses'             => $bExpW,
                'net_cash'             => $bNetCash,
                'is_active'            => $selectedBranchId ? ($selectedBranchId === $branch->id) : true,
            ];
        }

        // ── 8. Unified Cashier & Laundry Staff Shift Performance ──────────────
        $staffMap = [];

        // Aggregate POS Cashiers
        foreach ($daySales->groupBy('user_id') as $uId => $cSales) {
            $uId = (int) $uId;
            if (!isset($staffMap[$uId])) {
                $staffMap[$uId] = [
                    'user_id'     => $uId,
                    'pos_orders'  => 0,
                    'ldy_orders'  => 0,
                    'cash'        => 0.0,
                    'bank'        => 0.0,
                    'total_sales' => 0.0,
                ];
            }
            $staffMap[$uId]['pos_orders']  += $cSales->count();
            $staffMap[$uId]['cash']        += (float) $cSales->sum('cash');
            $staffMap[$uId]['bank']        += (float) $cSales->sum('bank_transfer');
            $staffMap[$uId]['total_sales'] += (float) $cSales->sum('final_total');
        }

        // Aggregate Laundry Staff
        foreach ($laundryPaymentsOnly->groupBy('recorded_by') as $uId => $lPays) {
            $uId = (int) $uId;
            if (!$uId) continue;
            if (!isset($staffMap[$uId])) {
                $staffMap[$uId] = [
                    'user_id'     => $uId,
                    'pos_orders'  => 0,
                    'ldy_orders'  => 0,
                    'cash'        => 0.0,
                    'bank'        => 0.0,
                    'total_sales' => 0.0,
                ];
            }
            $lCash = (float) $lPays->filter(fn($p) => str_contains(strtolower($p->method ?? ''), 'cash'))->sum('amount');
            $lBank = (float) $lPays->filter(fn($p) => !str_contains(strtolower($p->method ?? ''), 'cash'))->sum('amount');
            $staffMap[$uId]['ldy_orders']  += $lPays->count();
            $staffMap[$uId]['cash']        += $lCash;
            $staffMap[$uId]['bank']        += $lBank;
            $staffMap[$uId]['total_sales'] += (float) $lPays->sum('amount');
        }

        $allStaffUserIds = array_keys($staffMap);
        $staffUsers = User::whereIn('id', $allStaffUserIds)->get(['id', 'name', 'username', 'branch_id'])->keyBy('id');

        $cashierStats = [];
        foreach ($staffMap as $uId => $data) {
            $u = $staffUsers->get($uId);
            $b = $u ? $orgBranches->firstWhere('id', $u->branch_id) : null;

            $hasPos = $data['pos_orders'] > 0;
            $hasLdy = $data['ldy_orders'] > 0;
            $roleLabel = ($hasPos && $hasLdy) ? 'Retail & Laundry' : ($hasLdy ? 'Laundry Staff' : 'Retail Cashier');

            $cashierStats[] = [
                'user_id'      => $uId,
                'name'         => $u ? ($u->name ?: $u->username) : 'Staff #' . $uId,
                'branch_name'  => $b ? $b->name : 'N/A',
                'role_label'   => $roleLabel,
                'orders_count' => $data['pos_orders'] + $data['ldy_orders'],
                'pos_orders'   => $data['pos_orders'],
                'ldy_orders'   => $data['ldy_orders'],
                'total_sales'  => round($data['total_sales'], 2),
                'cash'         => round($data['cash'], 2),
                'bank'         => round($data['bank'], 2),
            ];
        }

        usort($cashierStats, fn($a, $b) => $b['total_sales'] <=> $a['total_sales']);

        // ── 9. Hourly Trading for Selected Day ────────────────────────────────
        $hourlySales = Sale::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('SUM(final_total) as revenue'),
                DB::raw('COUNT(id) as count')
            )
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->get();

        $hourlyLaundry = LaundryPayment::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->where('type', 'payment')
            ->select(
                DB::raw('HOUR(paid_at) as hour'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(id) as count')
            )
            ->groupBy(DB::raw('HOUR(paid_at)'))
            ->get();

        $hourlyDistribution = [];
        for ($h = 8; $h <= 22; $h++) {
            $posMatch = $hourlySales->firstWhere('hour', $h);
            $ldyMatch = $hourlyLaundry->firstWhere('hour', $h);
            $hRev = (float) ($posMatch->revenue ?? 0) + (float) ($ldyMatch->revenue ?? 0);
            $hCount = (int) ($posMatch->count ?? 0) + (int) ($ldyMatch->count ?? 0);
            $hourlyDistribution[] = [
                'hour'    => sprintf('%02d:00', $h),
                'revenue' => round($hRev, 2),
                'orders'  => $hCount,
            ];
        }

        // ── 10. Items and Services Breakdown Today ────────────────────────────
        $itemBreakdown = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
            ->whereIn('sales.branch_id', $targetBranchIds)
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->select(
                'sale_orders.item_name',
                'sale_orders.unit_used',
                DB::raw('SUM(sale_orders.qty) as total_qty'),
                DB::raw('SUM(sale_orders.total_selling_price) as total_amount')
            )
            ->groupBy('sale_orders.item_name', 'sale_orders.unit_used')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'type'         => 'product',
                'name'         => $item->item_name ?: 'Unnamed Item',
                'unit'         => $item->unit_used ?: 'unit',
                'qty'          => (int) $item->total_qty,
                'total_amount' => (float) $item->total_amount,
            ]);

        $serviceBreakdown = LaundryOrderItem::join('laundry_orders', 'laundry_orders.id', '=', 'laundry_order_items.order_id')
            ->whereIn('laundry_orders.branch_id', $targetBranchIds)
            ->whereBetween('laundry_orders.received_at', [$startDate, $endDate])
            ->select(
                'laundry_order_items.service_name',
                'laundry_order_items.pricing_type',
                DB::raw('SUM(laundry_order_items.quantity) as total_qty'),
                DB::raw('SUM(laundry_order_items.line_total) as total_amount')
            )
            ->groupBy('laundry_order_items.service_name', 'laundry_order_items.pricing_type')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get()
            ->map(fn($svc) => [
                'type'         => 'service',
                'name'         => $svc->service_name ?: 'Laundry Service',
                'unit'         => ($svc->pricing_type === 'per_kg') ? 'kg' : 'pcs',
                'qty'          => (float) $svc->total_qty,
                'total_amount' => (float) $svc->total_amount,
            ]);

        $combinedItemBreakdown = $itemBreakdown->concat($serviceBreakdown)
            ->sortByDesc('total_amount')
            ->take(15)
            ->values()
            ->all();

        // ── 11. Unified Paginated Transactions for End of Day Audit ───────────
        $salesList = Sale::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['branch:id,name', 'user:id,name,username', 'customer:id,name'])
            ->latest('id')
            ->get()
            ->map(fn(Sale $s) => [
                'id'             => 'sale_' . $s->id,
                'type'           => 'pos',
                'receipt_number' => $s->receipt_id ?? "REC-{$s->id}",
                'branch_name'    => $s->branch?->name ?? 'Unknown',
                'cashier_name'   => $s->user?->name ?? ($s->user?->username ?? 'Staff'),
                'customer_name'  => $s->customer?->name ?? 'Walk-in Customer',
                'final_total'    => (float) $s->final_total,
                'cash'           => (float) $s->cash,
                'bank_transfer'  => (float) $s->bank_transfer,
                'is_debt'        => (bool) $s->is_debt,
                'payment_method' => $s->payment_method ?: 'Cash',
                'created_at'     => $s->created_at?->format('h:i A'),
                'timestamp'      => $s->created_at?->timestamp ?? 0,
            ]);

        $laundryList = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('received_at', [$startDate, $endDate])
            ->with(['branch:id,name'])
            ->latest('received_at')
            ->get()
            ->map(fn(LaundryOrder $o) => [
                'id'             => 'ldy_' . $o->id,
                'type'           => 'laundry',
                'receipt_number' => $o->order_number,
                'branch_name'    => $o->branch?->name ?? 'Unknown',
                'cashier_name'   => 'Laundry Staff',
                'customer_name'  => $o->customer_name ?: 'Customer',
                'final_total'    => (float) $o->total,
                'cash'           => (float) $o->amount_paid,
                'bank_transfer'  => 0.0,
                'is_debt'        => ((float) $o->balance > 0),
                'payment_method' => ((float) $o->balance <= 0) ? 'Fully Paid' : 'Deposit / Balance Due',
                'created_at'     => $o->received_at?->format('h:i A'),
                'timestamp'      => $o->received_at?->timestamp ?? 0,
            ]);

        $allAuditTransactions = $salesList->concat($laundryList)
            ->sortByDesc('timestamp')
            ->values();

        $page = (int) $request->input('sales_page', 1);
        $perPage = 20;
        $totalItems = $allAuditTransactions->count();
        $slice = $allAuditTransactions->slice(($page - 1) * $perPage, $perPage)->values();

        $paginatedTransactions = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $totalItems,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'sales_page']
        );

        return Inertia::render('Org/Reports/EndOfDay', [
            'organization' => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
            'branches' => $orgBranches->map(fn($b) => [
                'id'             => $b->id,
                'name'           => $b->name,
                'slug'           => $b->slug,
                'operating_mode' => ($b->posEnabled() && $b->laundryEnabled()) ? 'both' : ($b->laundryEnabled() ? 'laundry_only' : 'pos_only'),
            ]),
            'filters'  => [
                'branch_id' => $selectedBranchId,
                'date'      => $targetDate->format('Y-m-d'),
            ],
            'reconciliation'       => $reconciliation,
            'branch_breakdown'     => $branchBreakdown,
            'cashier_stats'        => $cashierStats,
            'hourly_distribution' => $hourlyDistribution,
            'item_breakdown'       => $combinedItemBreakdown,
            'expenses_list'        => $expensesToday->map(fn($e) => [
                'id'          => $e->id,
                'branch_name' => $e->branch?->name ?? 'N/A',
                'category'    => $e->category?->name ?? 'Uncategorized',
                'description' => $e->description ?: '-',
                'amount'      => (float) $e->amount,
                'logged_by'   => $e->user?->name ?? 'Staff',
            ]),
            'sales'                => $paginatedTransactions,
        ]);
    }

    /**
     * Export filtered sales report as a CSV download.
     */
    public function exportSales(Request $request): StreamedResponse
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $orgBranches = Branch::where('organization_id', $org->id)->get(['id', 'name']);
        $allBranchIds = $orgBranches->pluck('id')->all();

        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId && in_array((int)$selectedBranchId, $allBranchIds)) {
            $targetBranchIds = [(int)$selectedBranchId];
            $branchName = $orgBranches->firstWhere('id', (int)$selectedBranchId)?->name ?? 'branch';
        } else {
            $targetBranchIds = $allBranchIds;
            $branchName = 'all_branches';
        }

        [$startDate, $endDate] = $this->parseDateRange($request);

        $filename = 'sales_report_' . str_replace(' ', '_', strtolower($branchName)) . '_' . $startDate->format('Ymd') . '_to_' . $endDate->format('Ymd') . '.csv';

        $salesQuery = Sale::whereIn('branch_id', $targetBranchIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['branch:id,name', 'user:id,name', 'customer:id,name'])
            ->latest('id');

        return response()->streamDownload(function () use ($salesQuery) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                '#',
                'Receipt ID',
                'Branch',
                'Cashier',
                'Customer',
                'Date & Time',
                'Payment Method',
                'Cash Paid (NGN)',
                'Transfer Paid (NGN)',
                'Discount (NGN)',
                'Total Amount (NGN)',
                'Profit (NGN)',
                'Status',
            ]);

            $idx = 1;
            $salesQuery->chunk(200, function ($sales) use ($handle, &$idx) {
                foreach ($sales as $s) {
                    $status = $s->is_debt ? 'Credit / Debt' : 'Completed';
                    fputcsv($handle, [
                        $idx++,
                        $s->receipt_id ?? "REC-{$s->id}",
                        $s->branch?->name ?? 'Unknown',
                        $s->user?->name ?? 'Staff',
                        $s->customer?->name ?? 'Walk-in',
                        $s->created_at?->format('Y-m-d H:i:s') ?? '',
                        $s->payment_method ?? 'Cash',
                        number_format((float)$s->cash, 2, '.', ''),
                        number_format((float)$s->bank_transfer, 2, '.', ''),
                        number_format((float)$s->discount_amount, 2, '.', ''),
                        number_format((float)$s->final_total, 2, '.', ''),
                        number_format((float)$s->profit_made, 2, '.', ''),
                        $status,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0, no-cache',
        ]);
    }
}
