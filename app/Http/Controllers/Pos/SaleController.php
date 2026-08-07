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
    public function index(Request $request)
    {
        $branch    = current_branch();
        $canSeeAll = RoleService::canViewAllReports();

        $selectedUserId = $canSeeAll ? ($request->user_id ?: null) : auth()->id();

        $salesQuery = Sale::where('branch_id', $branch->id)
            ->with(['user', 'customer', 'saleOrders'])
            ->when($request->from,      fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,        fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($selectedUserId,     fn ($q) => $q->where('user_id', $selectedUserId))
            ->latest();

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
                'from'    => $request->from ?? '',
                'to'      => $request->to ?? '',
                'user_id' => $selectedUserId ?? '',
            ],
            'settings'  => \App\Models\PosSettings::current(),
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
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => 'Sale processing failed. Please try again.'], 500);
            }
            return back()->withErrors(['sale' => 'Sale processing failed. Please try again.']);
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
}
