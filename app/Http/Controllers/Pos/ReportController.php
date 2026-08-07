<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Item;
use App\Models\SaleOrder;
use App\Models\PosCustomer;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Reports/Index');
    }

    public function dailySales(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = \App\Services\RoleService::canViewAllReports();

        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate   = $request->end_date   ? Carbon::parse($request->end_date)->endOfDay()     : Carbon::today()->endOfDay();

        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        // Summary computed on full dataset (no pagination)
        $allSales = Sale::where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->get(['final_total', 'discount_amount', 'profit_made']);

        $summary = [
            'total_sales'    => $allSales->count(),
            'total_revenue'  => (float) $allSales->sum('final_total'),
            'total_discount' => (float) $allSales->sum('discount_amount'),
            'total_profit'   => (float) $allSales->sum('profit_made'),
            'total_tax'      => 0,
        ];

        // Paginated rows (25 per page)
        $sales = Sale::with(['customer', 'user', 'saleOrders'])
            ->where('branch_id', $branch->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
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
                'user_id'    => $selectedUserId ?? '',
            ]
        ]);
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
        $threshold = (int) ($branch->settings['out_of_stock'] ?? 10);

        $items = Item::where('branch_id', $branch->id)
            ->where(DB::raw('(front_store_qty + back_store_qty)'), '<=', $threshold)
            ->orderBy(DB::raw('(front_store_qty + back_store_qty)'), 'asc')
            ->paginate(25)
            ->through(fn ($item) => array_merge($item->toArray(), [
                'qty'            => $item->total_qty,
                'alert_quantity' => $threshold,
            ]))
            ->withQueryString();

        return Inertia::render('Reports/LowStock', [
            'items' => $items,
        ]);
    }

    public function customerDebt(Request $request)
    {
        $branch = current_branch();

        // Only customers who have made purchases or debts in this branch
        $branchCustomerIds = Sale::where('branch_id', $branch->id)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique();

        $totalDebt = PosCustomer::whereIn('id', $branchCustomerIds)
            ->where('debt_bal', '>', 0)
            ->sum('debt_bal');

        $customers = PosCustomer::whereIn('id', $branchCustomerIds)
            ->where('debt_bal', '>', 0)
            ->orderBy('debt_bal', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Reports/CustomerDebt', [
            'customers'  => $customers,
            'total_debt' => $totalDebt,
        ]);
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

        $summary = [
            'total_sales'    => $allSalesToday->count(),
            'total_revenue'  => (float) $allSalesToday->sum('final_total'),
            'total_discount' => (float) $allSalesToday->sum('discount_amount'),
            'cash_collected' => (float) $allSalesToday->sum('cash'),
            'bank_collected' => (float) $allSalesToday->sum('bank_transfer'),
            'debt_recorded'  => (float) $allSalesToday->where('is_debt', true)->sum('final_total'),
            'debt_recovered' => (float) $debtRecovered,
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
