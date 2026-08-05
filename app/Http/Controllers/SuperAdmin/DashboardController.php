<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount(['users', 'items'])->get();

        // Cross-branch sales summary for current month
        $startOfMonth = now()->startOfMonth();
        $salesSummary = Sale::select(
                'branch_id',
                DB::raw('COUNT(*) as sale_count'),
                DB::raw('SUM(final_total) as total_revenue'),
                DB::raw('SUM(profit_made) as total_profit')
            )
            ->where('created_at', '>=', $startOfMonth)
            ->groupBy('branch_id')
            ->with('branch:id,name,slug')
            ->get()
            ->keyBy('branch_id');

        // All-time totals per branch
        $allTimeSales = Sale::select(
                'branch_id',
                DB::raw('COUNT(*) as sale_count'),
                DB::raw('SUM(final_total) as total_revenue')
            )
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $branchStats = $branches->map(function (Branch $branch) use ($salesSummary, $allTimeSales) {
            $monthly  = $salesSummary->get($branch->id);
            $allTime  = $allTimeSales->get($branch->id);
            return [
                'id'              => $branch->id,
                'name'            => $branch->name,
                'slug'            => $branch->slug,
                'is_active'       => $branch->is_active,
                'user_count'      => $branch->users_count,
                'item_count'      => $branch->items_count,
                'monthly_sales'   => (int) ($monthly?->sale_count ?? 0),
                'monthly_revenue' => (float) ($monthly?->total_revenue ?? 0),
                'monthly_profit'  => (float) ($monthly?->total_profit ?? 0),
                'total_sales'     => (int) ($allTime?->sale_count ?? 0),
                'total_revenue'   => (float) ($allTime?->total_revenue ?? 0),
            ];
        });

        return Inertia::render('SuperAdmin/Dashboard', [
            'branchStats'     => $branchStats,
            'totalBranches'   => $branches->count(),
            'activeBranches'  => $branches->where('is_active', true)->count(),
            'month'           => now()->format('F Y'),
        ]);
    }
}
