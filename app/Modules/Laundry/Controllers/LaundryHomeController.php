<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaundryHomeController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $receivedTodayCount = LaundryOrder::where('branch_id', $branch->id)
            ->whereDate('received_at', Carbon::today())
            ->count();

        $inProgressCount = LaundryOrder::where('branch_id', $branch->id)
            ->where('status', 'in_progress')
            ->count();

        $readyCount = LaundryOrder::where('branch_id', $branch->id)
            ->where('status', 'ready')
            ->count();

        $overdueCount = LaundryOrder::where('branch_id', $branch->id)
            ->whereNotIn('status', ['collected', 'cancelled'])
            ->where('due_at', '<', Carbon::now())
            ->count();

        $user = $request->user();
        $userId = $user?->id;
        $userRole = $user ? $user->getRoleDisplayName($branch->id, true) : 'Staff';

        $cashCollectedToday = (float) LaundryPayment::where('branch_id', $branch->id)
            ->where('recorded_by', $userId)
            ->whereDate('paid_at', Carbon::today())
            ->where('type', 'payment')
            ->whereRaw('LOWER(method) = ?', ['cash'])
            ->sum('amount')
            - (float) LaundryPayment::where('branch_id', $branch->id)
            ->where('recorded_by', $userId)
            ->whereDate('paid_at', Carbon::today())
            ->where('type', 'refund')
            ->whereRaw('LOWER(method) = ?', ['cash'])
            ->sum('amount');

        $totalCollectedToday = (float) LaundryPayment::where('branch_id', $branch->id)
            ->where('recorded_by', $userId)
            ->whereDate('paid_at', Carbon::today())
            ->where('type', 'payment')
            ->sum('amount')
            - (float) LaundryPayment::where('branch_id', $branch->id)
            ->where('recorded_by', $userId)
            ->whereDate('paid_at', Carbon::today())
            ->where('type', 'refund')
            ->sum('amount');

        $recentOrders = LaundryOrder::where('branch_id', $branch->id)
            ->with(['items'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn($o) => [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'customer_name'  => $o->customer_name,
                'customer_phone' => $o->customer_phone,
                'status'         => $o->status,
                'items_count'    => $o->items->count(),
                'total'          => (float) $o->total,
                'balance'        => (float) $o->balance,
                'due_at'         => $o->due_at?->format('d M, h:i A'),
                'is_overdue'     => $o->isOverdue(),
            ]);

        return Inertia::render('Laundry/Home', [
            'stats' => [
                'received_today'   => $receivedTodayCount,
                'in_progress'      => $inProgressCount,
                'ready'            => $readyCount,
                'overdue'          => $overdueCount,
                'cash_today'       => round(max(0, $cashCollectedToday), 2),
                'total_paid_today' => round(max(0, $totalCollectedToday), 2),
            ],
            'recent_orders' => $recentOrders,
            'user_role'     => $userRole,
        ]);
    }
}
