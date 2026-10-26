<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundrySetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LaundryReportController extends Controller
{
    /**
     * Display the Laundry Reports and analytics dashboard.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.view_reports')) {
            abort(403, 'Unauthorized to view laundry reports.');
        }

        $branch = current_branch();
        $settings = LaundrySetting::getForBranch($branch->id);

        $range = $request->input('range', 'this_month');
        $customStart = $request->input('start_date');
        $customEnd = $request->input('end_date');

        $now = Carbon::now();
        switch ($range) {
            case 'today':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
            case 'yesterday':
                $startDate = $now->copy()->subDay()->startOfDay();
                $endDate = $now->copy()->subDay()->endOfDay();
                break;
            case 'this_week':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
            case 'last_month':
                $startDate = $now->copy()->subMonth()->startOfMonth();
                $endDate = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'custom':
                $startDate = $customStart ? Carbon::parse($customStart)->startOfDay() : $now->copy()->startOfMonth();
                $endDate = $customEnd ? Carbon::parse($customEnd)->endOfDay() : $now->copy()->endOfDay();
                break;
            case 'this_month':
            default:
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
        }

        // 1. Order Summary in Period (by received_at)
        $ordersQuery = LaundryOrder::forBranch($branch->id)
            ->whereBetween('received_at', [$startDate, $endDate]);

        $totalOrdersCount = (clone $ordersQuery)->count();
        $totalOrdersValue = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('total');

        // Status Breakdown
        $statusBreakdown = (clone $ordersQuery)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as total_amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        // 2. Payments Collected in Period (by paid_at)
        $paymentsQuery = LaundryPayment::forBranch($branch->id)
            ->whereBetween('paid_at', [$startDate, $endDate]);

        $grossCollections = (float) (clone $paymentsQuery)->where('type', 'payment')->sum('amount');
        $refundsTotal = (float) (clone $paymentsQuery)->where('type', 'refund')->sum('amount');
        $netCollections = $grossCollections - $refundsTotal;

        // Payment Methods Breakdown
        $paymentsByMethod = (clone $paymentsQuery)
            ->select('method', 'type', DB::raw('sum(amount) as total_amount'), DB::raw('count(*) as count'))
            ->groupBy('method', 'type')
            ->get();

        $methodSummary = [];
        foreach ($paymentsByMethod as $p) {
            $m = $p->method;
            if (!isset($methodSummary[$m])) {
                $methodSummary[$m] = ['method' => $m, 'payments' => 0, 'refunds' => 0, 'net' => 0];
            }
            if ($p->type === 'payment') {
                $methodSummary[$m]['payments'] += (float) $p->total_amount;
            } else {
                $methodSummary[$m]['refunds'] += (float) $p->total_amount;
            }
            $methodSummary[$m]['net'] = $methodSummary[$m]['payments'] - $methodSummary[$m]['refunds'];
        }

        // 3. Top Services Processed in Period
        $topServices = LaundryOrderItem::query()
            ->whereHas('order', function ($q) use ($branch, $startDate, $endDate) {
                $q->forBranch($branch->id)
                    ->whereBetween('received_at', [$startDate, $endDate])
                    ->where('status', '!=', 'cancelled');
            })
            ->select('service_name', 'pricing_type', DB::raw('sum(quantity) as total_qty'), DB::raw('sum(line_total) as total_revenue'))
            ->groupBy('service_name', 'pricing_type')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        // 4. Overdue and Unclaimed Orders (Snapshot right now)
        $overdueOrders = LaundryOrder::forBranch($branch->id)
            ->whereIn('status', ['received', 'in_progress'])
            ->where('due_at', '<', $now)
            ->with('customer')
            ->orderBy('due_at')
            ->get();

        $unclaimedCutoff = $now->copy()->subDays($settings->unclaimed_after_days);
        $unclaimedOrders = LaundryOrder::forBranch($branch->id)
            ->where('status', 'ready')
            ->where('ready_at', '<', $unclaimedCutoff)
            ->with('customer')
            ->orderBy('ready_at')
            ->get();

        // 5. Active Outstanding Balances (Orders collected or active with balance > 0)
        $unpaidOrders = LaundryOrder::forBranch($branch->id)
            ->where('status', '!=', 'cancelled')
            ->with('payments')
            ->get()
            ->filter(fn($order) => $order->balance_due > 0)
            ->values();

        $totalOutstandingDebt = $unpaidOrders->sum(fn($o) => $o->balance_due);

        // Group outstanding balances by customer
        $outstandingByCustomer = $unpaidOrders->groupBy('customer_id')->map(function ($orders) {
            $first = $orders->first();
            return [
                'customer_id'    => $first->customer_id,
                'customer_name'  => $first->customer_name,
                'customer_phone' => $first->customer_phone,
                'orders_count'   => $orders->count(),
                'total_balance'  => round($orders->sum(fn($o) => $o->balance_due), 2),
            ];
        })->sortByDesc('total_balance')->values();

        return Inertia::render('Laundry/Reports/Index', [
            'filters' => [
                'range'      => $range,
                'start_date' => $startDate->toDateString(),
                'end_date'   => $endDate->toDateString(),
            ],
            'metrics' => [
                'orders_count'         => $totalOrdersCount,
                'orders_value'         => $totalOrdersValue,
                'net_collections'      => $netCollections,
                'gross_collections'    => $grossCollections,
                'refunds_total'        => $refundsTotal,
                'outstanding_debt'     => $totalOutstandingDebt,
                'overdue_count'        => $overdueOrders->count(),
                'unclaimed_count'      => $unclaimedOrders->count(),
            ],
            'status_breakdown'        => $statusBreakdown,
            'methods_summary'         => array_values($methodSummary),
            'top_services'            => $topServices,
            'overdue_orders'          => $overdueOrders,
            'unclaimed_orders'        => $unclaimedOrders,
            'outstanding_by_customer' => $outstandingByCustomer,
        ]);
    }
}
