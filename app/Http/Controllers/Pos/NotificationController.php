<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Dedicated Notifications page for Branch Admin / Staff.
     */
    public function index(Request $request, $branchParam = null): Response
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $branchId = $branch?->id;

        $query = AppNotification::forBranch($branchId)
            ->with(['user:id,name', 'resolver:id,name']);

        // Search query
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Filter by Type
        if ($type = $request->input('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // Filter by Status
        $status = $request->input('status', 'unresolved'); // Default to unresolved for immediate action
        if ($status === 'unresolved') {
            $query->where('is_resolved', false);
        } elseif ($status === 'unread') {
            $query->where('is_read', false);
        } elseif ($status === 'resolved') {
            $query->where('is_resolved', true);
        }

        $notifications = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'total'           => AppNotification::forBranch($branchId)->count(),
            'unresolved'      => AppNotification::forBranch($branchId)->where('is_resolved', false)->count(),
            'unread'          => AppNotification::forBranch($branchId)->where('is_read', false)->count(),
            'stock_conflicts' => AppNotification::forBranch($branchId)->whereIn('type', ['stock_conflict', 'imei_conflict'])->where('is_resolved', false)->count(),
            'resolved'        => AppNotification::forBranch($branchId)->where('is_resolved', true)->count(),
        ];

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters'       => $request->only(['search', 'status', 'type']),
            'stats'         => $stats,
        ]);
    }

    /**
     * Fast API endpoint for live bell preview & polling.
     */
    public function preview(Request $request, $branchParam = null): JsonResponse
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $branchId = $branch?->id;

        $unreadCount     = NotificationService::getUnreadCount($branchId, Auth::id());
        $unresolvedCount = NotificationService::getUnresolvedCount($branchId);
        $preview         = NotificationService::getRecentPreview($branchId, 8);

        return response()->json([
            'unread_count'     => $unreadCount,
            'unresolved_count' => $unresolvedCount,
            'notifications'    => $preview,
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, $branchParam = null, AppNotification $notification = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if ($branch && $notification && $notification->branch_id && $notification->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to notification.');
        }

        $notification?->markAsRead();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark all notifications for the branch as read.
     */
    public function markAllAsRead(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $branchId = $branch?->id;

        NotificationService::markAllRead($branchId, Auth::id());

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Mark a notification as resolved.
     */
    public function resolve(Request $request, $branchParam = null, AppNotification $notification = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if ($branch && $notification && $notification->branch_id && $notification->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to notification.');
        }

        $notification?->markAsResolved(Auth::id());

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notification resolved successfully.');
    }

    /**
     * Delete/dismiss a notification.
     */
    public function destroy(Request $request, $branchParam = null, AppNotification $notification = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if ($branch && $notification && $notification->branch_id && $notification->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to notification.');
        }

        $notification?->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Notification dismissed.');
    }
}
