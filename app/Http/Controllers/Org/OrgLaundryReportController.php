<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Organization;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundrySetting;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrgLaundryReportController extends Controller
{
    /**
     * Resolve the active organization for the authenticated user.
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
        $preset    = $request->input('preset') ?: $request->input('range');
        $fromInput = $request->input('from') ?: $request->input('start_date');
        $toInput   = $request->input('to') ?: $request->input('end_date');

        if ($preset) {
            switch ($preset) {
                case 'today':
                    return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay(), 'today'];
                case 'yesterday':
                    return [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay(), 'yesterday'];
                case 'week':
                case 'this_week':
                    return [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfDay(), 'week'];
                case 'month':
                case 'this_month':
                    return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfDay(), 'month'];
                case 'last_month':
                    return [
                        Carbon::now()->subMonth()->startOfMonth()->startOfDay(),
                        Carbon::now()->subMonth()->endOfMonth()->endOfDay(),
                        'last_month'
                    ];
                case '30_days':
                    return [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay(), '30_days'];
                case 'all':
                    return [Carbon::create(2020, 1, 1)->startOfDay(), Carbon::now()->endOfDay(), 'all'];
            }
        }

        if ($fromInput && $toInput) {
            return [Carbon::parse($fromInput)->startOfDay(), Carbon::parse($toInput)->endOfDay(), 'custom'];
        } elseif ($fromInput) {
            return [Carbon::parse($fromInput)->startOfDay(), Carbon::today()->endOfDay(), 'custom'];
        }

        // Default to current month
        return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfDay(), 'month'];
    }

    /**
     * Display Organization-level Laundry Reports & Analytics.
     */
    public function index(Request $request): Response
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        // ── 1. Fetch Laundry-Enabled Branches ──────────────────────────────────
        $allOrgBranches = Branch::where('organization_id', $org->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $laundryBranches = Branch::where('organization_id', $org->id)
            ->whereHas('settings', fn($q) => $q->where('laundry_enabled', true))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $laundryBranchIds = $laundryBranches->pluck('id')->all();

        // Check if specific branch is requested
        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId && in_array((int) $selectedBranchId, $allOrgBranches->pluck('id')->all())) {
            $targetBranchIds = [(int) $selectedBranchId];
            $selectedBranchId = (int) $selectedBranchId;
        } else {
            // Default: Consolidated view across all laundry branches
            $targetBranchIds = $laundryBranchIds;
            $selectedBranchId = null;
        }

        // ── 2. Date Range Resolution ──────────────────────────────────────────
        [$startDate, $endDate, $activePreset] = $this->parseDateRange($request);
        $isAllTime = ($activePreset === 'all');

        // Early return structure if org has no laundry-enabled branches
        if (empty($laundryBranchIds) && empty($targetBranchIds)) {
            return Inertia::render('Org/Reports/Laundry', [
                'organization'           => ['id' => $org->id, 'name' => $org->name],
                'branches'               => [],
                'has_laundry_branches'   => false,
                'filters'                => [
                    'branch_id' => null,
                    'from'      => $startDate->format('Y-m-d'),
                    'to'        => $endDate->format('Y-m-d'),
                    'preset'    => $activePreset,
                    'search'    => null,
                    'status'    => 'all',
                ],
                'kpis'                   => $this->emptyKpis(),
                'status_breakdown'       => [],
                'payment_methods'        => [],
                'revenue_trend'          => [],
                'branch_comparison'      => [],
                'top_services'           => [],
                'overdue_count'          => 0,
                'unclaimed_count'        => 0,
                'recent_orders'          => ['data' => [], 'links' => []],
            ]);
        }

        // ── 3. Executive KPI Queries ──────────────────────────────────────────
        $ordersBaseQuery = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->when(!$isAllTime, fn($q) => $q->whereBetween('received_at', [$startDate, $endDate]));

        $totalOrdersCount = (int) (clone $ordersBaseQuery)->count();
        $totalOrdersValue = (float) (clone $ordersBaseQuery)->where('status', '!=', 'cancelled')->sum('total');
        $totalDiscounts   = (float) (clone $ordersBaseQuery)->where('status', '!=', 'cancelled')->sum('discount_amount');
        $grossIntakeValue = round($totalOrdersValue + $totalDiscounts, 2);

        // Payments in period
        $paymentsBaseQuery = LaundryPayment::whereIn('branch_id', $targetBranchIds)
            ->when(!$isAllTime, fn($q) => $q->whereBetween('paid_at', [$startDate, $endDate]));

        $grossCollections = (float) (clone $paymentsBaseQuery)->where('type', 'payment')->sum('amount');
        $refundsTotal     = (float) (clone $paymentsBaseQuery)->where('type', 'refund')->sum('amount');
        $netCollections   = round($grossCollections - $refundsTotal, 2);

        // Total Outstanding Receivables (orders with positive unpaid balance)
        $outstandingDebt = (float) LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->where('status', '!=', 'cancelled')
            ->where('balance', '>', 0)
            ->sum('balance');

        // Total Items Processed in period
        $totalItemsCount = (float) LaundryOrderItem::whereHas('order', function ($q) use ($targetBranchIds, $startDate, $endDate, $isAllTime) {
            $q->whereIn('branch_id', $targetBranchIds)
                ->where('status', '!=', 'cancelled')
                ->when(!$isAllTime, fn($sq) => $sq->whereBetween('received_at', [$startDate, $endDate]));
        })->sum('quantity');

        // Handover / Completed Pickups in period
        $completedPickupsCount = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->where('status', 'collected')
            ->when(!$isAllTime, fn($q) => $q->whereBetween('collected_at', [$startDate, $endDate]))
            ->count();

        // ── 4. Garment Pipeline Status Breakdown ─────────────────────────────
        $statusCounts = (clone $ordersBaseQuery)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as total_value'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = ['received', 'in_progress', 'ready', 'collected', 'cancelled'];
        $statusBreakdown = [];
        foreach ($statuses as $st) {
            $item = $statusCounts->get($st);
            $statusBreakdown[] = [
                'status'      => $st,
                'label'       => match ($st) {
                    'received'    => 'Received / Intake',
                    'in_progress' => 'In Progress (Washing/Ironing)',
                    'ready'       => 'Ready for Pickup',
                    'collected'   => 'Collected / Delivered',
                    'cancelled'   => 'Cancelled / Returned',
                    default       => ucfirst(str_replace('_', ' ', $st)),
                },
                'count'       => $item ? (int) $item->count : 0,
                'total_value' => $item ? (float) $item->total_value : 0.0,
            ];
        }

        // Active Orders currently in production
        $activeOrdersCount = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->whereIn('status', ['received', 'in_progress'])
            ->count();

        $readyOrdersCount = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->where('status', 'ready')
            ->count();

        // ── 5. Payment Methods Breakdown ─────────────────────────────────────
        $paymentsByMethod = (clone $paymentsBaseQuery)
            ->select('method', DB::raw('sum(amount) as total_amount'), DB::raw('count(*) as count'))
            ->where('type', 'payment')
            ->groupBy('method')
            ->get();

        $paymentMethods = $paymentsByMethod->map(fn($row) => [
            'method' => match (strtolower($row->method ?? '')) {
                'cash'          => 'Cash',
                'card', 'pos'   => 'Card / POS',
                'transfer'      => 'Bank Transfer',
                default         => $row->method ? ucwords(str_replace(['_', '-'], ' ', $row->method)) : 'Other',
            },
            'amount' => (float) $row->total_amount,
            'count'  => (int) $row->count,
        ])->sortByDesc('amount')->values()->all();

        // ── 6. Branch Comparison (When viewing multiple branches) ───────────
        $branchComparison = [];
        if (count($targetBranchIds) > 1 || (!$selectedBranchId && count($laundryBranches) > 0)) {
            foreach ($laundryBranches as $b) {
                $bOrdersQuery = LaundryOrder::where('branch_id', $b->id)
                    ->when(!$isAllTime, fn($q) => $q->whereBetween('received_at', [$startDate, $endDate]));

                $bPaymentsQuery = LaundryPayment::where('branch_id', $b->id)
                    ->when(!$isAllTime, fn($q) => $q->whereBetween('paid_at', [$startDate, $endDate]));

                $bGrossCol = (float) (clone $bPaymentsQuery)->where('type', 'payment')->sum('amount');
                $bRefTotal = (float) (clone $bPaymentsQuery)->where('type', 'refund')->sum('amount');

                $bDebt = (float) LaundryOrder::where('branch_id', $b->id)
                    ->where('status', '!=', 'cancelled')
                    ->where('balance', '>', 0)
                    ->sum('balance');

                $bActive = LaundryOrder::where('branch_id', $b->id)
                    ->whereIn('status', ['received', 'in_progress'])
                    ->count();

                $bReady = LaundryOrder::where('branch_id', $b->id)
                    ->where('status', 'ready')
                    ->count();

                $branchComparison[] = [
                    'branch_id'         => $b->id,
                    'branch_name'       => $b->name,
                    'branch_slug'       => $b->slug,
                    'orders_count'      => (int) (clone $bOrdersQuery)->count(),
                    'orders_value'      => (float) (clone $bOrdersQuery)->where('status', '!=', 'cancelled')->sum('total'),
                    'net_collections'   => round($bGrossCol - $bRefTotal, 2),
                    'outstanding_debt'  => round($bDebt, 2),
                    'active_orders'     => $bActive,
                    'ready_orders'      => $bReady,
                ];
            }

            // Sort by net collections descending
            usort($branchComparison, fn($a, $b) => $b['net_collections'] <=> $a['net_collections']);
        }

        // ── 7. Daily / Periodic Revenue Trend for Chart.js ───────────────────
        $revenueTrend = [];
        $daysDiff = $startDate->diffInDays($endDate);

        if ($daysDiff <= 62) {
            $period = CarbonPeriod::create($startDate, $endDate);
            $dailyPayments = (clone $paymentsBaseQuery)
                ->select(DB::raw("DATE(paid_at) as date"), DB::raw("SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END) as net_amount"))
                ->groupBy(DB::raw("DATE(paid_at)"))
                ->pluck('net_amount', 'date')
                ->all();

            $dailyOrders = (clone $ordersBaseQuery)
                ->select(DB::raw("DATE(received_at) as date"), DB::raw("COUNT(*) as count"), DB::raw("SUM(total) as total_val"))
                ->groupBy(DB::raw("DATE(received_at)"))
                ->get()
                ->keyBy('date');

            foreach ($period as $dt) {
                $dateKey = $dt->format('Y-m-d');
                $dOrder = $dailyOrders->get($dateKey);
                $revenueTrend[] = [
                    'date'         => $dateKey,
                    'label'        => $dt->format('d M'),
                    'collections'  => (float) ($dailyPayments[$dateKey] ?? 0),
                    'orders_count' => $dOrder ? (int) $dOrder->count : 0,
                    'orders_value' => $dOrder ? (float) $dOrder->total_val : 0,
                ];
            }
        } else {
            // Group by month for long ranges
            $monthlyPayments = (clone $paymentsBaseQuery)
                ->select(DB::raw("DATE_FORMAT(paid_at, '%Y-%m') as ym"), DB::raw("SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END) as net_amount"))
                ->groupBy(DB::raw("DATE_FORMAT(paid_at, '%Y-%m')"))
                ->pluck('net_amount', 'ym')
                ->all();

            $monthlyOrders = (clone $ordersBaseQuery)
                ->select(DB::raw("DATE_FORMAT(received_at, '%Y-%m') as ym"), DB::raw("COUNT(*) as count"), DB::raw("SUM(total) as total_val"))
                ->groupBy(DB::raw("DATE_FORMAT(received_at, '%Y-%m')"))
                ->get()
                ->keyBy('ym');

            $currMonth = $startDate->copy()->startOfMonth();
            while ($currMonth->lte($endDate)) {
                $ym = $currMonth->format('Y-m');
                $mOrder = $monthlyOrders->get($ym);
                $revenueTrend[] = [
                    'date'         => $ym,
                    'label'        => $currMonth->format('M Y'),
                    'collections'  => (float) ($monthlyPayments[$ym] ?? 0),
                    'orders_count' => $mOrder ? (int) $mOrder->count : 0,
                    'orders_value' => $mOrder ? (float) $mOrder->total_val : 0,
                ];
                $currMonth->addMonth();
            }
        }

        // ── 8. Top Performing Services ───────────────────────────────────────
        $topServices = LaundryOrderItem::whereHas('order', function ($q) use ($targetBranchIds, $startDate, $endDate, $isAllTime) {
            $q->whereIn('branch_id', $targetBranchIds)
                ->where('status', '!=', 'cancelled')
                ->when(!$isAllTime, fn($sq) => $sq->whereBetween('received_at', [$startDate, $endDate]));
        })
        ->select('service_name', DB::raw('sum(quantity) as total_qty'), DB::raw('sum(line_total) as total_revenue'))
        ->groupBy('service_name')
        ->orderByDesc('total_revenue')
        ->limit(8)
        ->get()
        ->map(fn($row) => [
            'service_name'  => $row->service_name ?: 'Standard Service',
            'total_qty'     => (float) $row->total_qty,
            'total_revenue' => (float) $row->total_revenue,
        ]);

        // ── 9. Operational Alerts (Overdue & Unclaimed) ──────────────────────
        $overdueCount = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->whereIn('status', ['received', 'in_progress'])
            ->where('due_at', '<', Carbon::now())
            ->count();

        $unclaimedCount = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->where('status', 'ready')
            ->where('due_at', '<', Carbon::now()->subDays(14))
            ->count();

        // ── 10. Paginated Recent Laundry Orders with Search ───────────────────
        $search = $request->input('search');
        $statusFilter = $request->input('status', 'all');

        $recentOrders = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->when(!$isAllTime, fn($q) => $q->whereBetween('received_at', [$startDate, $endDate]))
            ->when($statusFilter && $statusFilter !== 'all', fn($q) => $q->where('status', $statusFilter))
            ->when($search, function ($q, $s) {
                $q->where(function ($sq) use ($s) {
                    $sq->where('order_number', 'like', "%{$s}%")
                       ->orWhere('customer_name', 'like', "%{$s}%")
                       ->orWhere('customer_phone', 'like', "%{$s}%");
                });
            })
            ->with(['branch:id,name,slug'])
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Org/Reports/Laundry', [
            'organization'           => [
                'id'   => $org->id,
                'name' => $org->name,
            ],
            'branches'               => $laundryBranches,
            'has_laundry_branches'   => count($laundryBranches) > 0,
            'filters'                => [
                'branch_id' => $selectedBranchId,
                'from'      => $startDate->format('Y-m-d'),
                'to'        => $endDate->format('Y-m-d'),
                'preset'    => $activePreset,
                'search'    => $search,
                'status'    => $statusFilter,
            ],
            'kpis'                   => [
                'net_collections'         => $netCollections,
                'gross_collections'       => $grossCollections,
                'refunds_total'           => $refundsTotal,
                'total_orders'            => $totalOrdersCount,
                'total_orders_value'      => $totalOrdersValue,
                'gross_intake_value'      => $grossIntakeValue,
                'total_discounts'         => $totalDiscounts,
                'outstanding_debt'        => $outstandingDebt,
                'total_items_count'       => $totalItemsCount,
                'active_orders_count'     => $activeOrdersCount,
                'ready_orders_count'      => $readyOrdersCount,
                'completed_pickups_count' => $completedPickupsCount,
            ],
            'status_breakdown'       => $statusBreakdown,
            'payment_methods'        => $paymentMethods,
            'revenue_trend'          => $revenueTrend,
            'branch_comparison'      => $branchComparison,
            'top_services'           => $topServices,
            'overdue_count'          => $overdueCount,
            'unclaimed_count'        => $unclaimedCount,
            'recent_orders'          => $recentOrders,
        ]);
    }

    /**
     * Export Organization Laundry Orders to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $laundryBranches = Branch::where('organization_id', $org->id)
            ->whereHas('settings', fn($q) => $q->where('laundry_enabled', true))
            ->get(['id', 'name']);

        $laundryBranchIds = $laundryBranches->pluck('id')->all();

        $selectedBranchId = $request->input('branch_id');
        if ($selectedBranchId && in_array((int) $selectedBranchId, $laundryBranchIds)) {
            $targetBranchIds = [(int) $selectedBranchId];
            $branchName = $laundryBranches->firstWhere('id', (int) $selectedBranchId)?->name ?? 'branch';
        } else {
            $targetBranchIds = $laundryBranchIds;
            $branchName = 'all_branches';
        }

        [$startDate, $endDate, $preset] = $this->parseDateRange($request);
        $isAllTime = ($preset === 'all');

        $statusFilter = $request->input('status', 'all');
        $search       = $request->input('search');

        $filename = 'laundry_report_' . str_replace(' ', '_', strtolower($branchName)) . '_' . $startDate->format('Ymd') . '_to_' . $endDate->format('Ymd') . '.csv';

        $ordersQuery = LaundryOrder::whereIn('branch_id', $targetBranchIds)
            ->when(!$isAllTime, fn($q) => $q->whereBetween('received_at', [$startDate, $endDate]))
            ->when($statusFilter && $statusFilter !== 'all', fn($q) => $q->where('status', $statusFilter))
            ->when($search, function ($q, $s) {
                $q->where(function ($sq) use ($s) {
                    $sq->where('order_number', 'like', "%{$s}%")
                       ->orWhere('customer_name', 'like', "%{$s}%")
                       ->orWhere('customer_phone', 'like', "%{$s}%");
                });
            })
            ->with(['branch:id,name'])
            ->latest('received_at');

        return response()->streamDownload(function () use ($ordersQuery) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Microsoft Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                '#',
                'Order Number',
                'Branch',
                'Customer Name',
                'Customer Phone',
                'Status',
                'Intake Date',
                'Estimated Due Date',
                'Collected Date',
                'Subtotal (NGN)',
                'Discount (NGN)',
                'Total (NGN)',
                'Amount Paid (NGN)',
                'Balance Due (NGN)',
                'Handover Staff',
            ]);

            $idx = 1;
            $ordersQuery->chunk(200, function ($orders) use ($handle, &$idx) {
                foreach ($orders as $o) {
                    fputcsv($handle, [
                        $idx++,
                        $o->order_number,
                        $o->branch?->name ?? 'Unknown',
                        $o->customer_name ?: 'Walk-in Customer',
                        $o->customer_phone ?: '-',
                        ucfirst(str_replace('_', ' ', $o->status)),
                        $o->received_at?->format('Y-m-d H:i') ?? '',
                        $o->due_at?->format('Y-m-d H:i') ?? '',
                        $o->collected_at?->format('Y-m-d H:i') ?? '',
                        number_format((float) $o->subtotal, 2, '.', ''),
                        number_format((float) $o->discount_amount, 2, '.', ''),
                        number_format((float) $o->total, 2, '.', ''),
                        number_format((float) $o->amount_paid, 2, '.', ''),
                        number_format((float) $o->balance, 2, '.', ''),
                        $o->collected_by_name ?: ($o->collected_by_user_id ? 'Staff' : '-'),
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

    /**
     * Default empty KPI structure.
     */
    protected function emptyKpis(): array
    {
        return [
            'net_collections'         => 0,
            'gross_collections'       => 0,
            'refunds_total'           => 0,
            'total_orders'            => 0,
            'total_orders_value'      => 0,
            'gross_intake_value'      => 0,
            'total_discounts'         => 0,
            'outstanding_debt'        => 0,
            'total_items_count'       => 0,
            'active_orders_count'     => 0,
            'ready_orders_count'      => 0,
            'completed_pickups_count' => 0,
        ];
    }
}

