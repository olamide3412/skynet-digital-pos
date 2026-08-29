<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\StockConflict;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class NotificationService
{
    /**
     * Dispatch a notification for an offline stock or IMEI conflict.
     */
    public static function notifyConflict(StockConflict $conflict, Branch|int $branch, ?User $user = null): AppNotification
    {
        $branchModel = $branch instanceof Branch ? $branch : Branch::find($branch);
        $branchId = $branchModel?->id ?? (int) $branch;
        $branchSlug = $branchModel?->slug ?? 'branch';

        $isImei = $conflict->conflict_type === 'imei_already_sold';
        $type = $isImei ? 'imei_conflict' : 'stock_conflict';
        
        $title = $isImei
            ? "IMEI Conflict — {$conflict->item_name}"
            : "Stock Conflict — {$conflict->item_name}";

        $message = $isImei
            ? "Serial/IMEI '{$conflict->imei_or_device_id}' for {$conflict->item_name} was already sold prior to offline sync."
            : "Offline sync recorded {$conflict->requested_qty} sold against {$conflict->available_qty_at_sync} in front store ({$conflict->requested_qty} units requested).";

        $actionUrl = "/{$branchSlug}/inventory/conflicts?search=" . urlencode($conflict->item_name);

        return AppNotification::create([
            'branch_id'      => $branchId,
            'user_id'        => $user?->id,
            'type'           => $type,
            'title'          => $title,
            'message'        => $message,
            'action_url'     => $actionUrl,
            'reference_type' => StockConflict::class,
            'reference_id'   => $conflict->id,
            'data'           => [
                'conflict_id'           => $conflict->id,
                'item_id'               => $conflict->item_id,
                'item_name'             => $conflict->item_name,
                'conflict_type'         => $conflict->conflict_type,
                'requested_qty'         => $conflict->requested_qty,
                'available_qty_at_sync' => $conflict->available_qty_at_sync,
                'imei_or_device_id'     => $conflict->imei_or_device_id,
                'offline_sale_id'       => $conflict->offline_sale_id,
                'sale_id'               => $conflict->sale_id,
            ],
            'is_read'        => false,
            'is_resolved'    => false,
        ]);
    }

    /**
     * Generic helper to notify a branch about any event.
     */
    public static function notifyBranch(
        Branch|int $branch,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?Model $reference = null,
        ?array $data = null,
        ?int $userId = null
    ): AppNotification {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return AppNotification::create([
            'branch_id'      => $branchId,
            'user_id'        => $userId,
            'type'           => $type,
            'title'          => $title,
            'message'        => $message,
            'action_url'     => $actionUrl,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id'   => $reference?->getKey(),
            'data'           => $data,
            'is_read'        => false,
            'is_resolved'    => false,
        ]);
    }

    /**
     * Get unread notification count for a branch.
     */
    public static function getUnreadCount(Branch|int|null $branch, ?int $userId = null): int
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        if (!$branchId) return 0;

        return AppNotification::forBranch($branchId)
            ->when($userId, fn($q) => $q->where(fn($sub) => $sub->where('user_id', $userId)->orWhereNull('user_id')))
            ->unread()
            ->count();
    }

    /**
     * Get unresolved notification count for a branch.
     */
    public static function getUnresolvedCount(Branch|int|null $branch): int
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        if (!$branchId) return 0;

        return AppNotification::forBranch($branchId)
            ->unresolved()
            ->count();
    }

    /**
     * Get recent notifications preview for dropdowns.
     */
    public static function getRecentPreview(Branch|int|null $branch, int $limit = 5): array
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        if (!$branchId) return [];

        return AppNotification::forBranch($branchId)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn(AppNotification $n) => [
                'id'          => $n->id,
                'type'        => $n->type,
                'title'       => $n->title,
                'message'     => $n->message,
                'action_url'  => $n->action_url,
                'is_read'     => $n->is_read,
                'is_resolved' => $n->is_resolved,
                'created_at'  => $n->created_at?->diffForHumans() ?? 'Just now',
                'raw_time'    => $n->created_at?->toIso8601String(),
                'data'        => $n->data,
            ])
            ->toArray();
    }

    /**
     * Mark all unread notifications as read for a branch.
     */
    public static function markAllRead(Branch|int $branch, ?int $userId = null): int
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return AppNotification::forBranch($branchId)
            ->when($userId, fn($q) => $q->where(fn($sub) => $sub->where('user_id', $userId)->orWhereNull('user_id')))
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Mark matching notifications as resolved when the underlying reference is resolved.
     */
    public static function resolveForReference(string $referenceType, int $referenceId, ?int $userId = null): int
    {
        return AppNotification::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('is_resolved', false)
            ->update([
                'is_resolved' => true,
                'resolved_at' => now(),
                'resolved_by' => $userId ?? auth()->id(),
                'is_read'     => true,
                'read_at'     => now(),
            ]);
    }
}
