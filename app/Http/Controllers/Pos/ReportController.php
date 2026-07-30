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
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate   = $request->end_date   ? Carbon::parse($request->end_date)->endOfDay()     : Carbon::today()->endOfDay();

        // Summary computed on full dataset (no pagination)
        $allSales = Sale::whereBetween('created_at', [$startDate, $endDate])->get(['final_total', 'discount_amount']);

        $summary = [
            'total_sales'    => $allSales->count(),
            'total_revenue'  => $allSales->sum('final_total'),
            'total_discount' => $allSales->sum('discount_amount'),
            'total_tax'      => 0,
        ];

        // Paginated rows (25 per page)
        $sales = Sale::with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Reports/DailySales', [
            'sales'   => $sales,
            'summary' => $summary,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d')
            ]
        ]);
    }

    public function profitLoss(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::today()->startOfDay();
        $endDate   = $request->end_date   ? Carbon::parse($request->end_date)->endOfDay()     : Carbon::today()->endOfDay();

        $saleItems = \App\Models\SaleOrder::with(['sale', 'item'])
            ->whereHas('sale', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

        $totalRevenue = 0;
        $totalCost    = 0;

        foreach ($saleItems as $orderItem) {
            $totalRevenue += $orderItem->total_selling_price;
            if ($orderItem->item) {
                $totalCost += ($orderItem->item->buy_price * $orderItem->qty);
            }
        }

        $summary = [
            'total_revenue' => $totalRevenue,
            'total_cost'    => $totalCost,
            'gross_profit'  => $totalRevenue - $totalCost,
            'profit_margin' => $totalRevenue > 0 ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 2) : 0,
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
        $items = Item::where('qty', '<=', 10)
            ->orderBy('qty', 'asc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Reports/LowStock', [
            'items' => $items,
        ]);
    }

    public function customerDebt(Request $request)
    {
        // Total across ALL customers (not just current page)
        $totalDebt = PosCustomer::where('debt_bal', '>', 0)->sum('debt_bal');

        $customers = PosCustomer::where('debt_bal', '>', 0)
            ->orderBy('debt_bal', 'desc')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Reports/CustomerDebt', [
            'customers' => $customers,
            'total_debt' => $totalDebt,
        ]);
    }

    public function endOfDay(Request $request)
    {
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
        $allSalesToday = Sale::whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->get(['final_total', 'discount_amount', 'cash', 'bank_transfer', 'is_debt']);

        $debtRecovered = \App\Models\DebtPayment::whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->sum('amount');

        $summary = [
            'total_sales'    => $allSalesToday->count(),
            'total_revenue'  => $allSalesToday->sum('final_total'),
            'total_discount' => $allSalesToday->sum('discount_amount'),
            'cash_collected' => $allSalesToday->sum('cash'),
            'bank_collected' => $allSalesToday->sum('bank_transfer'),
            'debt_recorded'  => $allSalesToday->where('is_debt', true)->sum('final_total'),
            'debt_recovered' => $debtRecovered,
        ];

        // ── Paginated sales ────────────────────────────────────────────────────
        $sales = Sale::with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'sales_page')
            ->withQueryString();

        // ── Paginated debt payments ────────────────────────────────────────────
        $debtPayments = \App\Models\DebtPayment::with(['customer', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'debt_page')
            ->withQueryString();

        $users = $canSeeAll ? User::orderBy('name')->get(['id', 'name', 'username']) : [];

        return Inertia::render('Reports/EndOfDay', [
            'sales'        => $sales,
            'debtPayments' => $debtPayments,
            'summary'      => $summary,
            'users'        => $users,
            'canSeeAll'    => $canSeeAll,
            'filters'      => [
                'user_id' => $canSeeAll ? ($selectedUserId ?? '') : $currentUser->id,
            ],
        ]);
    }

    public function standardReport(Request $request)
    {
        return redirect()->route('pos.reports.index');
    }
}
