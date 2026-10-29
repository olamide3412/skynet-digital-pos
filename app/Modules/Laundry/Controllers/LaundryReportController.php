<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\TillSession;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryOrderStatusHistory;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Services\RoleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LaundryReportController extends Controller
{
    /**
     * Display the Laundry Reports and analytics dashboard with date range and staff/cashier queries.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.view_reports')) {
            abort(403, 'Unauthorized to view laundry reports.');
        }

        $branch = current_branch();
        $settings = LaundrySetting::getForBranch($branch->id);

        // ── 1. Parse Date Range & Staff Filter (Supporting POS-style range, preset, from, to) ──
        $rangeInput = $request->input('range') ?: $request->input('preset');
        $fromInput  = $request->input('from') ?: $request->input('start_date');
        $toInput    = $request->input('to') ?: $request->input('end_date');
        $selectedUserId = $request->input('user_id') ?: null;

        $isAllTime = ($rangeInput === 'all')
            || ($fromInput === 'all')
            || ($request->has('from') && $request->has('to') && empty($fromInput) && empty($toInput))
            || ($request->has('range') && $rangeInput === 'all');

        $now = Carbon::now();

        if ($isAllTime) {
            $earliestOrder = LaundryOrder::forBranch($branch->id)->min('received_at');
            $earliestPayment = LaundryPayment::forBranch($branch->id)->min('paid_at');
            $earliest = collect([$earliestOrder, $earliestPayment])->filter()->min();
            $startDate = $earliest ? Carbon::parse($earliest)->startOfDay() : Carbon::create(2020, 1, 1)->startOfDay();
            $endDate   = $now->copy()->endOfDay();
            $range     = 'all';
        } elseif ($fromInput && $toInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = Carbon::parse($toInput)->endOfDay();
            $range     = 'custom';
        } elseif ($fromInput) {
            $startDate = Carbon::parse($fromInput)->startOfDay();
            $endDate   = $now->copy()->endOfDay();
            $range     = 'custom';
        } else {
            $range = $rangeInput ?: 'this_month';
            switch ($range) {
                case 'today':
                    $startDate = $now->copy()->startOfDay();
                    $endDate   = $now->copy()->endOfDay();
                    break;
                case 'yesterday':
                    $startDate = $now->copy()->subDay()->startOfDay();
                    $endDate   = $now->copy()->subDay()->endOfDay();
                    break;
                case 'week':
                case 'this_week':
                    $startDate = $now->copy()->startOfWeek()->startOfDay();
                    $endDate   = $now->copy()->endOfDay();
                    $range     = 'this_week';
                    break;
                case 'last_month':
                    $startDate = $now->copy()->subMonth()->startOfMonth()->startOfDay();
                    $endDate   = $now->copy()->subMonth()->endOfMonth()->endOfDay();
                    break;
                case 'month':
                case 'this_month':
                default:
                    $startDate = $now->copy()->startOfMonth()->startOfDay();
                    $endDate   = $now->copy()->endOfDay();
                    $range     = 'this_month';
                    break;
            }
        }

        // ── 2. Staff & Cashiers List for Filtering ────────────────────────────
        $branchUserIds = User::where('branch_id', $branch->id)->pluck('id');
        $historicalUserIds = LaundryOrder::forBranch($branch->id)->select('created_by')->distinct()->pluck('created_by')
            ->merge(LaundryPayment::forBranch($branch->id)->select('recorded_by')->distinct()->pluck('recorded_by'))
            ->merge(LaundryOrder::forBranch($branch->id)->whereNotNull('collected_by_user_id')->pluck('collected_by_user_id'))
            ->merge(LaundryOrderStatusHistory::whereHas('order', fn ($q) => $q->forBranch($branch->id))->select('changed_by')->distinct()->pluck('changed_by'));

        $allUserIds = $branchUserIds->merge($historicalUserIds)->unique()->filter();

        setPermissionsTeamId($branch->id);

        $users = User::whereIn('id', $allUserIds)
            ->with(['roles' => fn ($q) => $q->where('roles.team_id', $branch->id)->orWhereNull('roles.team_id')])
            ->select('id', 'name', 'username', 'full_name')
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                $role = $u->roles->first()?->name;
                $roleLabel = match ($role) {
                    'branch-admin'      => 'Branch Admin',
                    'cashier'           => 'Cashier',
                    'laundry-staff'     => 'Laundry Staff',
                    'manager'           => 'Store Manager',
                    'inventory-manager' => 'Inventory Manager',
                    default             => $role ? ucwords(str_replace(['-', '_'], ' ', $role)) : 'Staff',
                };
                return [
                    'id'        => $u->id,
                    'name'      => $u->full_name ?: $u->name ?: $u->username,
                    'username'  => $u->username,
                    'role'      => $roleLabel,
                ];
            });

        $selectedUser = null;
        if ($selectedUserId) {
            $matched = $users->firstWhere('id', (int) $selectedUserId);
            if ($matched) {
                $selectedUser = $matched;
            } else {
                $userModel = User::find($selectedUserId);
                if ($userModel) {
                    $selectedUser = [
                        'id'       => $userModel->id,
                        'name'     => $userModel->full_name ?: $userModel->name ?: $userModel->username,
                        'username' => $userModel->username,
                        'role'     => 'Staff',
                    ];
                }
            }
        }

        // ── 3. Orders Intake & Discount Metrics (Filtered by Staff if user_id given) ──
        $ordersQuery = LaundryOrder::forBranch($branch->id)
            ->when(!$isAllTime, fn ($q) => $q->whereBetween('received_at', [$startDate, $endDate]))
            ->when($selectedUserId, fn ($q) => $q->where('created_by', $selectedUserId));

        $totalOrdersCount = (clone $ordersQuery)->count();
        $totalOrdersValue = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('total');

        // Order-level discounts
        $orderDiscountsTotal = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('discount_amount');
        $generalDiscountOrdersCount = (clone $ordersQuery)->where('status', '!=', 'cancelled')->where('discount_amount', '>', 0)->count();

        // Service-level item discounts
        $serviceDiscountsQuery = LaundryOrderItem::whereHas('order', function ($q) use ($branch, $startDate, $endDate, $isAllTime, $selectedUserId) {
            $q->forBranch($branch->id)
                ->where('status', '!=', 'cancelled')
                ->when(!$isAllTime, fn ($sq) => $sq->whereBetween('received_at', [$startDate, $endDate]))
                ->when($selectedUserId, fn ($sq) => $sq->where('created_by', $selectedUserId));
        })
        ->where(function ($q) {
            $q->where('discount_amount', '>', 0)
              ->orWhereColumn('original_price', '>', 'unit_price');
        });

        $serviceDiscountsTotal = (float) (clone $serviceDiscountsQuery)
            ->sum(DB::raw('CASE WHEN original_price IS NOT NULL AND original_price > unit_price THEN (original_price - unit_price) * quantity ELSE discount_amount * quantity END'));
        $serviceDiscountItemsCount = (int) (clone $serviceDiscountsQuery)->count();

        $totalDiscounts = round($orderDiscountsTotal + $serviceDiscountsTotal, 2);

        $discountedOrdersCount = (clone $ordersQuery)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('discount_amount', '>', 0)
                  ->orWhereHas('items', function ($iq) {
                      $iq->where('discount_amount', '>', 0)
                         ->orWhereColumn('original_price', '>', 'unit_price');
                  });
            })->count();

        $grossBeforeDiscount = round($totalOrdersValue + $totalDiscounts, 2);
        $discountRatePercent = $grossBeforeDiscount > 0 ? round(($totalDiscounts / $grossBeforeDiscount) * 100, 1) : 0;

        // Promotional campaign performance breakdown
        $generalPromos = (clone $ordersQuery)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('laundry_discount_id')
            ->select('laundry_discount_id', DB::raw('count(*) as usage_count'), DB::raw('sum(discount_amount) as total_savings'))
            ->groupBy('laundry_discount_id')
            ->with('generalDiscount:id,name,discount_type,discount_value')
            ->get()
            ->map(function ($row) {
                $disc = $row->generalDiscount;
                return [
                    'id'           => 'gen_' . $row->laundry_discount_id,
                    'name'         => $disc ? $disc->name : 'General Order Promo',
                    'scope'        => 'general',
                    'scope_label'  => 'Order Promo',
                    'type'         => $disc ? $disc->discount_type : 'percentage',
                    'rate'         => $disc ? (float) $disc->discount_value : null,
                    'usage_count'  => (int) $row->usage_count,
                    'unit_label'   => 'orders',
                    'total_savings'=> (float) $row->total_savings,
                ];
            });

        $manualDiscountsRow = (clone $ordersQuery)
            ->where('status', '!=', 'cancelled')
            ->whereNull('laundry_discount_id')
            ->where('discount_amount', '>', 0)
            ->select(DB::raw('count(*) as usage_count'), DB::raw('sum(discount_amount) as total_savings'))
            ->first();

        $discountsPerformance = collect($generalPromos);
        if ($manualDiscountsRow && $manualDiscountsRow->usage_count > 0) {
            $discountsPerformance->push([
                'id'           => 'manual_order',
                'name'         => 'Manual Order Discount',
                'scope'        => 'manual',
                'scope_label'  => 'Manual Discount',
                'type'         => 'custom',
                'rate'         => null,
                'usage_count'  => (int) $manualDiscountsRow->usage_count,
                'unit_label'   => 'orders',
                'total_savings'=> (float) $manualDiscountsRow->total_savings,
            ]);
        }

        $servicePromos = LaundryOrderItem::whereHas('order', function ($q) use ($branch, $startDate, $endDate, $isAllTime, $selectedUserId) {
            $q->forBranch($branch->id)
                ->where('status', '!=', 'cancelled')
                ->when(!$isAllTime, fn ($sq) => $sq->whereBetween('received_at', [$startDate, $endDate]))
                ->when($selectedUserId, fn ($sq) => $sq->where('created_by', $selectedUserId));
        })
        ->whereNotNull('laundry_discount_id')
        ->select(
            'laundry_discount_id',
            DB::raw('count(*) as item_lines_count'),
            DB::raw('sum(quantity) as total_units'),
            DB::raw('sum(CASE WHEN original_price IS NOT NULL AND original_price > unit_price THEN (original_price - unit_price) * quantity ELSE discount_amount * quantity END) as total_savings')
        )
        ->groupBy('laundry_discount_id')
        ->with('discount:id,name,discount_type,discount_value,service_id', 'discount.service:id,name')
        ->get()
        ->map(function ($row) {
            $disc = $row->discount;
            $units = (float) $row->total_units;
            $formattedUnits = ($units == (int) $units) ? (int) $units : $units;
            return [
                'id'           => 'srv_' . $row->laundry_discount_id,
                'name'         => $disc ? $disc->name : 'Service Promo',
                'target'       => $disc?->service?->name,
                'scope'        => 'service',
                'scope_label'  => 'Service Promo',
                'type'         => $disc ? $disc->discount_type : 'percentage',
                'rate'         => $disc ? (float) $disc->discount_value : null,
                'usage_count'  => (int) $row->item_lines_count,
                'units_count'  => $formattedUnits,
                'unit_label'   => 'items',
                'total_savings'=> (float) $row->total_savings,
            ];
        });

        $discountsPerformance = $discountsPerformance->concat($servicePromos)->sortByDesc('total_savings')->values();

        // Status Breakdown
        $statusBreakdown = (clone $ordersQuery)
            ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as total_amount'))
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        // ── 4. Payments Collected in Period (Filtered by Staff if user_id given) ──
        $paymentsQuery = LaundryPayment::forBranch($branch->id)
            ->when(!$isAllTime, fn ($q) => $q->whereBetween('paid_at', [$startDate, $endDate]))
            ->when($selectedUserId, fn ($q) => $q->where('recorded_by', $selectedUserId));

        $grossCollections = (float) (clone $paymentsQuery)->where('type', 'payment')->sum('amount');
        $refundsTotal     = (float) (clone $paymentsQuery)->where('type', 'refund')->sum('amount');
        $netCollections   = $grossCollections - $refundsTotal;

        // Payment Methods Breakdown
        $paymentsByMethod = (clone $paymentsQuery)
            ->select('method', 'type', DB::raw('sum(amount) as total_amount'), DB::raw('count(*) as count'))
            ->groupBy('method', 'type')
            ->get();

        $methodSummary = [];
        foreach ($paymentsByMethod as $p) {
            $m = $p->method;
            if (!isset($methodSummary[$m])) {
                $methodSummary[$m] = ['method' => $m, 'payments' => 0, 'refunds' => 0, 'net' => 0, 'count' => 0];
            }
            if ($p->type === 'payment') {
                $methodSummary[$m]['payments'] += (float) $p->total_amount;
                $methodSummary[$m]['count']    += (int) $p->count;
            } else {
                $methodSummary[$m]['refunds']  += (float) $p->total_amount;
            }
            $methodSummary[$m]['net'] = $methodSummary[$m]['payments'] - $methodSummary[$m]['refunds'];
        }

        // ── 5. Handover / Pickups & Status Changes in Period ───────────────────
        $handoverQuery = LaundryOrder::forBranch($branch->id)
            ->whereNotNull('collected_at')
            ->when(!$isAllTime, fn ($q) => $q->whereBetween('collected_at', [$startDate, $endDate]))
            ->when($selectedUserId, fn ($q) => $q->where('collected_by_user_id', $selectedUserId));
        $handoverCount = (clone $handoverQuery)->count();

        $statusHistoryQuery = LaundryOrderStatusHistory::whereHas('order', fn ($q) => $q->forBranch($branch->id))
            ->when(!$isAllTime, fn ($q) => $q->whereBetween('changed_at', [$startDate, $endDate]))
            ->when($selectedUserId, fn ($q) => $q->where('changed_by', $selectedUserId));
        $statusChangesCount = (clone $statusHistoryQuery)->count();

        // ── 6. Top Services Processed in Period ──────────────────────────────
        $topServices = LaundryOrderItem::query()
            ->whereHas('order', function ($q) use ($branch, $startDate, $endDate, $isAllTime, $selectedUserId) {
                $q->forBranch($branch->id)
                    ->where('status', '!=', 'cancelled')
                    ->when(!$isAllTime, fn ($sq) => $sq->whereBetween('received_at', [$startDate, $endDate]))
                    ->when($selectedUserId, fn ($sq) => $sq->where('created_by', $selectedUserId));
            })
            ->select('service_name', 'pricing_type', DB::raw('sum(quantity) as total_qty'), DB::raw('sum(line_total) as total_revenue'))
            ->groupBy('service_name', 'pricing_type')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get()
            ->map(function ($srv) {
                $q = (float) $srv->total_qty;
                return [
                    'service_name'  => $srv->service_name,
                    'pricing_type'  => $srv->pricing_type,
                    'total_qty'     => ($q == (int) $q) ? (int) $q : $q,
                    'total_revenue' => (float) $srv->total_revenue,
                ];
            });

        // ── 7. Overdue & Unclaimed Orders Snapshot ────────────────────────────
        $overdueOrders = LaundryOrder::forBranch($branch->id)
            ->whereIn('status', ['received', 'in_progress'])
            ->where('due_at', '<', $now)
            ->when($selectedUserId, fn ($q) => $q->where('created_by', $selectedUserId))
            ->with('customer')
            ->orderBy('due_at')
            ->get();

        $unclaimedCutoff = $now->copy()->subDays($settings->unclaimed_after_days ?: 14);
        $unclaimedOrders = LaundryOrder::forBranch($branch->id)
            ->where('status', 'ready')
            ->where('due_at', '<=', $unclaimedCutoff)
            ->when($selectedUserId, fn ($q) => $q->where('created_by', $selectedUserId))
            ->with('customer')
            ->orderBy('due_at')
            ->get();

        // ── 8. Active Outstanding Balances (Debt) ─────────────────────────────
        $unpaidOrders = LaundryOrder::forBranch($branch->id)
            ->where('status', '!=', 'cancelled')
            ->where('balance', '>', 0)
            ->when($selectedUserId, fn ($q) => $q->where('created_by', $selectedUserId))
            ->get();

        $totalOutstandingDebt = (float) $unpaidOrders->sum('balance');

        $allOutstanding = $unpaidOrders->groupBy(function ($o) {
            return $o->customer_id ? 'id_' . $o->customer_id : 'name_' . $o->customer_name;
        })->map(function ($orders) {
            $first = $orders->first();
            return [
                'customer_id'    => $first->customer_id,
                'customer_name'  => $first->customer_name,
                'customer_phone' => $first->customer_phone,
                'orders_count'   => $orders->count(),
                'total_balance'  => round($orders->sum('balance'), 2),
            ];
        })->sortByDesc('total_balance')->values();

        $perPage = 10;
        $debtPage = (int) $request->input('debt_page', 1);
        $totalDebtors = $allOutstanding->count();
        $slicedItems = $allOutstanding->slice(($debtPage - 1) * $perPage, $perPage)->values();

        $outstandingByCustomer = new LengthAwarePaginator(
            $slicedItems,
            $totalDebtors,
            $perPage,
            $debtPage,
            [
                'path'     => $request->url(),
                'query'    => $request->query(),
                'pageName' => 'debt_page',
            ]
        );

        // ── 9. Chronological Activity Log (When querying specific user/cashier) ─
        $userActivityLog = null;
        if ($selectedUserId) {
            $createdEvents = LaundryOrder::forBranch($branch->id)
                ->where('created_by', $selectedUserId)
                ->when(!$isAllTime, fn ($q) => $q->whereBetween('received_at', [$startDate, $endDate]))
                ->with(['generalDiscount:id,name', 'items:id,order_id,original_price,unit_price,quantity,discount_amount'])
                ->get(['id', 'order_number', 'customer_name', 'customer_phone', 'status', 'total', 'subtotal', 'discount_amount', 'laundry_discount_id', 'amount_paid', 'received_at'])
                ->map(function ($o) {
                    $lineDisc = $o->items->sum(function ($it) {
                        $orig = (float) ($it->original_price ?: $it->unit_price ?: 0);
                        $unit = (float) ($it->unit_price ?: 0);
                        $diff = max(0, $orig - $unit);
                        return $diff * (float) $it->quantity;
                    });
                    $totalDisc = round((float) $o->discount_amount + $lineDisc, 2);
                    $promoName = $o->generalDiscount?->name ?: ($o->discount_amount > 0 ? 'Discount' : ($lineDisc > 0 ? 'Promo' : null));
                    $discText = $totalDisc > 0 ? " • Savings: -₦" . number_format($totalDisc, 2) . ($promoName ? " ({$promoName})" : '') : '';

                    return [
                        'id'             => 'order_' . $o->id,
                        'action_type'    => 'order_created',
                        'timestamp'      => $o->received_at?->toISOString(),
                        'formatted_time' => $o->received_at?->format('M j, Y h:i A'),
                        'title'          => "Intake Order #{$o->order_number}",
                        'description'    => "Customer: {$o->customer_name} ({$o->customer_phone}){$discText}",
                        'order_id'       => $o->id,
                        'order_number'   => $o->order_number,
                        'amount'         => (float) $o->total,
                        'discount_amount'=> $totalDisc,
                        'promo_label'    => $promoName,
                        'status'         => $o->status,
                        'badge'          => 'Intake',
                        'badge_color'    => 'indigo',
                    ];
                });

            $paymentEvents = LaundryPayment::forBranch($branch->id)
                ->where('recorded_by', $selectedUserId)
                ->with('order:id,order_number,customer_name')
                ->when(!$isAllTime, fn ($q) => $q->whereBetween('paid_at', [$startDate, $endDate]))
                ->get()
                ->map(function ($p) {
                    $isRefund = $p->type === 'refund';
                    return [
                        'id'             => 'payment_' . $p->id,
                        'action_type'    => $isRefund ? 'refund_issued' : 'payment_recorded',
                        'timestamp'      => $p->paid_at?->toISOString(),
                        'formatted_time' => $p->paid_at?->format('M j, Y h:i A'),
                        'title'          => ($isRefund ? 'Issued Refund' : 'Collected Payment') . " ({$p->method})",
                        'description'    => "Order #{$p->order?->order_number}" . ($p->order?->customer_name ? " for {$p->order->customer_name}" : '') . ($p->reference ? " • Ref: {$p->reference}" : ''),
                        'order_id'       => $p->order_id,
                        'order_number'   => $p->order?->order_number,
                        'amount'         => (float) $p->amount,
                        'status'         => $isRefund ? 'refund' : 'payment',
                        'badge'          => $p->method,
                        'badge_color'    => $isRefund ? 'rose' : 'emerald',
                    ];
                });

            $handoverEvents = LaundryOrder::forBranch($branch->id)
                ->where('collected_by_user_id', $selectedUserId)
                ->when(!$isAllTime, fn ($q) => $q->whereBetween('collected_at', [$startDate, $endDate]))
                ->get(['id', 'order_number', 'customer_name', 'customer_phone', 'status', 'collected_at'])
                ->map(function ($o) {
                    return [
                        'id'             => 'collect_' . $o->id,
                        'action_type'    => 'order_collected',
                        'timestamp'      => $o->collected_at?->toISOString(),
                        'formatted_time' => $o->collected_at?->format('M j, Y h:i A'),
                        'title'          => "Handed Over Order #{$o->order_number}",
                        'description'    => "Released clean garments to {$o->customer_name}",
                        'order_id'       => $o->id,
                        'order_number'   => $o->order_number,
                        'amount'         => null,
                        'status'         => 'collected',
                        'badge'          => 'Handover',
                        'badge_color'    => 'sky',
                    ];
                });

            $statusEvents = LaundryOrderStatusHistory::whereHas('order', fn ($q) => $q->forBranch($branch->id))
                ->where('changed_by', $selectedUserId)
                ->with('order:id,order_number,customer_name')
                ->when(!$isAllTime, fn ($q) => $q->whereBetween('changed_at', [$startDate, $endDate]))
                ->get()
                ->map(function ($h) {
                    $from = ucwords(str_replace('_', ' ', $h->from_status ?: 'New'));
                    $to   = ucwords(str_replace('_', ' ', $h->to_status));
                    return [
                        'id'             => 'status_' . $h->id,
                        'action_type'    => 'status_changed',
                        'timestamp'      => $h->changed_at?->toISOString(),
                        'formatted_time' => $h->changed_at?->format('M j, Y h:i A'),
                        'title'          => "Updated Status: {$from} → {$to}",
                        'description'    => "Order #{$h->order?->order_number}" . ($h->order?->customer_name ? " for {$h->order->customer_name}" : '') . ($h->note ? " • Note: {$h->note}" : ''),
                        'order_id'       => $h->order_id,
                        'order_number'   => $h->order?->order_number,
                        'amount'         => null,
                        'status'         => $h->to_status,
                        'badge'          => $to,
                        'badge_color'    => 'amber',
                    ];
                });

            $allActivities = $createdEvents->concat($paymentEvents)
                ->concat($handoverEvents)
                ->concat($statusEvents)
                ->sortByDesc('timestamp')
                ->values();

            $actPage = (int) $request->input('activity_page', 1);
            $actPerPage = 15;
            $userActivityLog = new LengthAwarePaginator(
                $allActivities->slice(($actPage - 1) * $actPerPage, $actPerPage)->values(),
                $allActivities->count(),
                $actPerPage,
                $actPage,
                [
                    'path'     => $request->url(),
                    'query'    => $request->query(),
                    'pageName' => 'activity_page',
                ]
            );
        }

        // ── 10. Staff Contribution Summary (When viewing All Staff) ───────────
        $staffSummary = [];
        if (!$selectedUserId) {
            $staffSummary = $users->map(function ($u) use ($branch, $startDate, $endDate, $isAllTime) {
                $uid = $u['id'];

                $createdQ = LaundryOrder::forBranch($branch->id)->where('created_by', $uid)
                    ->when(!$isAllTime, fn ($q) => $q->whereBetween('received_at', [$startDate, $endDate]));
                $ordersCount = (clone $createdQ)->count();
                $ordersValue = (float) (clone $createdQ)->where('status', '!=', 'cancelled')->sum('total');

                $staffOrderDiscount = (float) (clone $createdQ)->where('status', '!=', 'cancelled')->sum('discount_amount');
                $staffServiceDiscount = (float) LaundryOrderItem::whereHas('order', function ($q) use ($branch, $uid, $startDate, $endDate, $isAllTime) {
                    $q->where('branch_id', $branch->id)
                        ->where('created_by', $uid)
                        ->where('status', '!=', 'cancelled')
                        ->when(!$isAllTime, fn ($sq) => $sq->whereBetween('received_at', [$startDate, $endDate]));
                })
                ->where(fn ($q) => $q->where('discount_amount', '>', 0)->orWhereColumn('original_price', '>', 'unit_price'))
                ->sum(DB::raw('CASE WHEN original_price IS NOT NULL AND original_price > unit_price THEN (original_price - unit_price) * quantity ELSE discount_amount * quantity END'));
                $totalStaffDiscount = round($staffOrderDiscount + $staffServiceDiscount, 2);

                $paymentsQ = LaundryPayment::forBranch($branch->id)->where('recorded_by', $uid)
                    ->when(!$isAllTime, fn ($q) => $q->whereBetween('paid_at', [$startDate, $endDate]));
                $paymentsGross = (float) (clone $paymentsQ)->where('type', 'payment')->sum('amount');
                $refundsTotal  = (float) (clone $paymentsQ)->where('type', 'refund')->sum('amount');
                $netCollections = $paymentsGross - $refundsTotal;

                $cashSum = (float) (clone $paymentsQ)->where('type', 'payment')->where('method', 'Cash')->sum('amount');
                $bankSum = (float) (clone $paymentsQ)->where('type', 'payment')->where('method', 'Bank Transfer')->sum('amount');
                $otherSum = $paymentsGross - ($cashSum + $bankSum);

                $collectedCount = LaundryOrder::forBranch($branch->id)
                    ->where('collected_by_user_id', $uid)
                    ->whereNotNull('collected_at')
                    ->when(!$isAllTime, fn ($q) => $q->whereBetween('collected_at', [$startDate, $endDate]))
                    ->count();

                $statusCount = LaundryOrderStatusHistory::whereHas('order', fn ($q) => $q->forBranch($branch->id))
                    ->where('changed_by', $uid)
                    ->when(!$isAllTime, fn ($q) => $q->whereBetween('changed_at', [$startDate, $endDate]))
                    ->count();

                return [
                    'user_id'            => $uid,
                    'name'               => $u['name'],
                    'username'           => $u['username'],
                    'role'               => $u['role'],
                    'orders_count'       => $ordersCount,
                    'orders_value'       => $ordersValue,
                    'discounts_given'    => $totalStaffDiscount,
                    'gross_collections'  => $paymentsGross,
                    'refunds_total'      => $refundsTotal,
                    'net_collections'    => $netCollections,
                    'cash_collected'     => $cashSum,
                    'bank_collected'     => $bankSum,
                    'other_collected'    => $otherSum,
                    'collected_count'    => $collectedCount,
                    'status_count'       => $statusCount,
                    'total_actions'      => $ordersCount + $paymentsQ->count() + $collectedCount + $statusCount,
                ];
            })->filter(function ($row) {
                return $row['total_actions'] > 0;
            })->sortByDesc('net_collections')->values();
        }

        return Inertia::render('Laundry/Reports/Index', [
            'filters' => [
                'range'      => $range,
                'from'       => $isAllTime ? '' : $startDate->toDateString(),
                'to'         => $isAllTime ? '' : $endDate->toDateString(),
                'start_date' => $isAllTime ? '' : $startDate->toDateString(),
                'end_date'   => $isAllTime ? '' : $endDate->toDateString(),
                'user_id'    => $selectedUserId ? (string) $selectedUserId : '',
            ],
            'users'         => $users,
            'selected_user' => $selectedUser,
            'metrics' => [
                'orders_count'                  => $totalOrdersCount,
                'orders_value'                  => $totalOrdersValue,
                'gross_before_discount'         => $grossBeforeDiscount,
                'total_discounts'               => $totalDiscounts,
                'order_discounts_total'         => round($orderDiscountsTotal, 2),
                'service_discounts_total'       => round($serviceDiscountsTotal, 2),
                'discounted_orders_count'       => $discountedOrdersCount,
                'general_discount_orders_count' => $generalDiscountOrdersCount,
                'service_discount_items_count'  => $serviceDiscountItemsCount,
                'discount_rate_percent'         => $discountRatePercent,
                'net_collections'               => $netCollections,
                'gross_collections'             => $grossCollections,
                'refunds_total'                 => $refundsTotal,
                'outstanding_debt'              => $totalOutstandingDebt,
                'overdue_count'                 => $overdueOrders->count(),
                'unclaimed_count'               => $unclaimedOrders->count(),
                'handover_count'                => $handoverCount,
                'status_changes_count'          => $statusChangesCount,
            ],
            'status_breakdown'        => $statusBreakdown,
            'methods_summary'         => array_values($methodSummary),
            'top_services'            => $topServices,
            'overdue_orders'          => $overdueOrders,
            'unclaimed_orders'        => $unclaimedOrders,
            'outstanding_by_customer' => $outstandingByCustomer,
            'user_activity_log'       => $userActivityLog,
            'staff_summary'           => $staffSummary,
            'discounts_performance'   => $discountsPerformance,
        ]);
    }

    /**
     * Display the Laundry End of Day report and drawer reconciliation.
     */
    public function endOfDay(Request $request): Response
    {
        $user = auth()->user();
        $canAccess = $user->isBranchAdmin()
            || $user->isSuperAdmin()
            || $user->hasRole(['cashier', 'laundry-staff', 'manager'])
            || RoleService::canViewEndOfDay()
            || RoleService::can('canViewEndOfDay')
            || RoleService::can('laundry.record_payment')
            || RoleService::can('laundry.view_reports');

        if (!$canAccess) {
            abort(403, 'Unauthorized to view laundry end of day report.');
        }

        $branch = current_branch();
        $dateStr = $request->input('date') ?: Carbon::today()->toDateString();
        $startDate = Carbon::parse($dateStr)->startOfDay();
        $endDate   = Carbon::parse($dateStr)->endOfDay();

        $canSeeAll = RoleService::canViewAllReports() || RoleService::can('laundry.view_reports');

        if ($canSeeAll) {
            $selectedUserId = $request->get('user_id') ?: null;
        } else {
            $selectedUserId = $user->id;
        }

        // ── 1. Orders Intake & Values Today ──────────────────────────────────
        $ordersQuery = LaundryOrder::forBranch($branch->id)
            ->whereBetween('received_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('created_by', $selectedUserId));

        $totalOrdersCount = (clone $ordersQuery)->count();
        $totalOrdersValue = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('total');
        $subtotal         = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('subtotal');
        $orderDiscounts   = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('discount_amount');
        $taxAmount        = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->sum('tax_amount');
        $debtRecorded     = (float) (clone $ordersQuery)->where('status', '!=', 'cancelled')->where('balance', '>', 0)->sum('balance');

        // Service item discounts
        $serviceDiscounts = (float) LaundryOrderItem::whereHas('order', function ($q) use ($branch, $startDate, $endDate, $selectedUserId) {
            $q->forBranch($branch->id)
                ->where('status', '!=', 'cancelled')
                ->whereBetween('received_at', [$startDate, $endDate])
                ->when($selectedUserId, fn ($sq) => $sq->where('created_by', $selectedUserId));
        })
        ->where(function ($q) {
            $q->where('discount_amount', '>', 0)
              ->orWhereColumn('original_price', '>', 'unit_price');
        })
        ->sum(DB::raw('CASE WHEN original_price IS NOT NULL AND original_price > unit_price THEN (original_price - unit_price) * quantity ELSE discount_amount * quantity END'));

        $totalDiscounts = round($orderDiscounts + $serviceDiscounts, 2);
        $grossOrderValue = round($totalOrdersValue + $totalDiscounts, 2);

        // ── 2. Payments Collected Today (By Method) ──────────────────────────
        $paymentsQuery = LaundryPayment::forBranch($branch->id)
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('recorded_by', $selectedUserId));

        $grossCollections = (float) (clone $paymentsQuery)->where('type', 'payment')->sum('amount');
        $refundsProcessed = (float) (clone $paymentsQuery)->where('type', 'refund')->sum('amount');
        $netCollections   = $grossCollections - $refundsProcessed;

        $cashCollected = (float) (clone $paymentsQuery)
            ->where('type', 'payment')
            ->whereRaw('LOWER(method) = ?', ['cash'])
            ->sum('amount');
        $cashRefunds = (float) (clone $paymentsQuery)
            ->where('type', 'refund')
            ->whereRaw('LOWER(method) = ?', ['cash'])
            ->sum('amount');
        $netCash = $cashCollected - $cashRefunds;

        $bankCollected = (float) (clone $paymentsQuery)
            ->where('type', 'payment')
            ->where(function ($q) {
                $q->whereRaw('LOWER(method) LIKE ?', ['%bank%'])
                  ->orWhereRaw('LOWER(method) LIKE ?', ['%transfer%']);
            })
            ->sum('amount');
        $bankRefunds = (float) (clone $paymentsQuery)
            ->where('type', 'refund')
            ->where(function ($q) {
                $q->whereRaw('LOWER(method) LIKE ?', ['%bank%'])
                  ->orWhereRaw('LOWER(method) LIKE ?', ['%transfer%']);
            })
            ->sum('amount');
        $netBank = $bankCollected - $bankRefunds;

        $otherCollected = (float) (clone $paymentsQuery)
            ->where('type', 'payment')
            ->whereRaw('LOWER(method) NOT IN (?, ?, ?)', ['cash', 'bank transfer', 'bank_transfer'])
            ->whereRaw('LOWER(method) NOT LIKE ?', ['%transfer%'])
            ->sum('amount');
        $otherRefunds = (float) (clone $paymentsQuery)
            ->where('type', 'refund')
            ->whereRaw('LOWER(method) NOT IN (?, ?, ?)', ['cash', 'bank transfer', 'bank_transfer'])
            ->whereRaw('LOWER(method) NOT LIKE ?', ['%transfer%'])
            ->sum('amount');
        $netOther = $otherCollected - $otherRefunds;

        // ── 3. Laundry Debt Recovered Today ──────────────────────────────────
        $debtRecoveredQuery = DebtPayment::whereBetween('created_at', [$startDate, $endDate])
            ->where('type', 'credit')
            ->where('branch_id', $branch->id)
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->where('source', 'laundry');

        $laundryDebtRecovered = (float) (clone $debtRecoveredQuery)->sum('amount');

        // ── 4. Pickups / Completed Orders Handed Over Today ──────────────────
        $pickupsCompleted = LaundryOrder::forBranch($branch->id)
            ->whereNotNull('collected_at')
            ->whereBetween('collected_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('collected_by_user_id', $selectedUserId))
            ->count();

        // ── 5. Branch Expenses Today ─────────────────────────────────────────
        $dailyExpenses = (float) Expense::where('branch_id', $branch->id)
            ->where(function ($q) use ($startDate, $endDate, $dateStr) {
                $q->whereDate('expense_date', $dateStr)
                  ->orWhereBetween('created_at', [$startDate, $endDate]);
            })
            ->sum('amount');

        $netCashFlow = round(($netCash + $laundryDebtRecovered) - $dailyExpenses, 2);

        $summary = [
            'total_orders'           => $totalOrdersCount,
            'total_revenue'          => $totalOrdersValue,
            'gross_order_value'      => $grossOrderValue,
            'subtotal'               => $subtotal,
            'total_discount'         => $totalDiscounts,
            'tax_amount'             => $taxAmount,
            'debt_recorded'          => $debtRecorded,
            'gross_collections'      => $grossCollections,
            'refunds_processed'      => $refundsProcessed,
            'net_collections'        => $netCollections,
            'cash_collected'         => $netCash,
            'bank_collected'         => $netBank,
            'other_collected'        => $netOther,
            'laundry_debt_recovered' => $laundryDebtRecovered,
            'pickups_completed'      => $pickupsCompleted,
            'total_expenses'         => $dailyExpenses,
            'net_cash_flow'          => $netCashFlow,
        ];

        // ── 6. Services Rendered Breakdown ───────────────────────────────────
        $serviceBreakdown = LaundryOrderItem::query()
            ->whereHas('order', function ($q) use ($branch, $startDate, $endDate, $selectedUserId) {
                $q->forBranch($branch->id)
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('received_at', [$startDate, $endDate])
                    ->when($selectedUserId, fn ($sq) => $sq->where('created_by', $selectedUserId));
            })
            ->select(
                'service_name',
                'pricing_type',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(line_total) as total_amount')
            )
            ->groupBy('service_name', 'pricing_type')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($item) {
                $q = (float) $item->total_qty;
                return [
                    'service_name' => $item->service_name,
                    'pricing_type' => $item->pricing_type,
                    'total_qty'    => ($q == (int) $q) ? (int) $q : $q,
                    'total_amount' => (float) $item->total_amount,
                ];
            });

        // ── 7. Till Sessions & Cash Drawer Reconciliation ────────────────────
        $tillSessions = TillSession::with(['cashier:id,name,username', 'closedBy:id,name', 'cashMovements.performer:id,name'])
            ->where('branch_id', $branch->id)
            ->whereBetween('opened_at', [$startDate, $endDate])
            ->when($selectedUserId, fn ($q) => $q->where('cashier_id', $selectedUserId))
            ->orderBy('opened_at', 'desc')
            ->get()
            ->map(function ($s) {
                return array_merge($s->toArray(), [
                    'summary' => $s->calculateSummary(),
                ]);
            });

        // ── 8. Paginated Orders Intake ───────────────────────────────────────
        $orders = (clone $ordersQuery)
            ->with([
                'customer:id,name,phone',
                'creator:id,name,username,full_name',
                'items:id,order_id,service_name,quantity,line_total,pricing_type',
                'payments:id,order_id,amount,method,type,paid_at',
            ])
            ->orderBy('received_at', 'desc')
            ->paginate(25, ['*'], 'orders_page')
            ->withQueryString();

        // ── 9. Paginated Payments Received Today ─────────────────────────────
        $payments = (clone $paymentsQuery)
            ->with([
                'order:id,order_number,customer_id,status,total,balance',
                'order.customer:id,name,phone',
                'recorder:id,name,username,full_name',
            ])
            ->orderBy('paid_at', 'desc')
            ->paginate(25, ['*'], 'payments_page')
            ->withQueryString();

        // ── 10. Paginated Debt Recoveries ────────────────────────────────────
        $debtPayments = (clone $debtRecoveredQuery)
            ->with(['customer', 'user', 'laundryOrder'])
            ->orderBy('created_at', 'desc')
            ->paginate(25, ['*'], 'debt_page')
            ->withQueryString();

        // ── 11. Staff Users for Filter ───────────────────────────────────────
        $branchUserIds = User::where('branch_id', $branch->id)->pluck('id');
        $todayUserIds = LaundryOrder::forBranch($branch->id)->whereBetween('received_at', [$startDate, $endDate])->pluck('created_by')
            ->merge(LaundryPayment::forBranch($branch->id)->whereBetween('paid_at', [$startDate, $endDate])->pluck('recorded_by'))
            ->merge(TillSession::where('branch_id', $branch->id)->whereBetween('opened_at', [$startDate, $endDate])->pluck('cashier_id'));
        $allUserIds = $branchUserIds->merge($todayUserIds)->unique()->filter();

        $users = $canSeeAll
            ? User::whereIn('id', $allUserIds)
                ->select('id', 'name', 'username', 'full_name')
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => [
                    'id'       => $u->id,
                    'name'     => $u->full_name ?: $u->name ?: $u->username,
                    'username' => $u->username,
                ])
            : [];

        return Inertia::render('Laundry/Reports/EndOfDay', [
            'date'             => $dateStr,
            'summary'          => $summary,
            'serviceBreakdown' => $serviceBreakdown,
            'tillSessions'     => $tillSessions,
            'orders'           => $orders,
            'payments'         => $payments,
            'debtPayments'     => $debtPayments,
            'users'            => $users,
            'canSeeAll'        => $canSeeAll,
            'filters'          => [
                'date'    => $dateStr,
                'user_id' => $canSeeAll ? ($selectedUserId ?? '') : $user->id,
            ],
        ]);
    }
}
