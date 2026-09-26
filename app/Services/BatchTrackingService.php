<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemBatchSyncIssue;
use App\Models\PosSettings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BatchTrackingService
{
    /**
     * Check if batch tracking is active for a given branch and optionally for a specific item.
     */
    public static function isBatchTrackingActive(int $branchId, ?Item $item = null): bool
    {
        $settings = PosSettings::forBranch($branchId);
        if (!$settings->batch_tracking_enabled) {
            return false;
        }

        if ($item !== null) {
            return (bool) $item->batch_tracking_enabled;
        }

        return true;
    }

    /**
     * Decrement batches via First-Expired-First-Out (FEFO) logic.
     * Guaranteed to execute as a silent, non-blocking side-effect that will NEVER
     * throw exceptions or disrupt core checkout / inventory operations.
     */
    public static function deductFefo(
        Item $item,
        int $qty,
        string $actionType,
        ?string $referenceId = null,
        ?User $user = null,
        ?string $notes = null
    ): bool {
        try {
            if ($qty <= 0) {
                return true;
            }

            // Early exit if tracking is not enabled for branch or item
            if (!static::isBatchTrackingActive($item->branch_id, $item)) {
                return true;
            }

            return DB::transaction(function () use ($item, $qty, $actionType, $referenceId, $notes) {
                // Fetch active batches with remaining quantity, ordered by soonest expiry first
                $batches = ItemBatch::where('branch_id', $item->branch_id)
                    ->where('item_id', $item->id)
                    ->where('status', 'active')
                    ->where('quantity', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalAvailable = (int) $batches->sum('quantity');
                $remainingToDeduct = $qty;

                foreach ($batches as $batch) {
                    if ($remainingToDeduct <= 0) {
                        break;
                    }

                    $deductFromThis = min((int) $batch->quantity, $remainingToDeduct);
                    $newQty = $batch->quantity - $deductFromThis;
                    $remainingToDeduct -= $deductFromThis;

                    $updateData = ['quantity' => $newQty];
                    if ($newQty === 0) {
                        $updateData['status'] = 'cleared';
                    }

                    $batch->update($updateData);
                }

                // If available batch quantity fell short of the total requested,
                // quietly record an issue in item_batch_sync_issues
                if ($remainingToDeduct > 0) {
                    $shortfall = $remainingToDeduct;
                    ItemBatchSyncIssue::create([
                        'branch_id'           => $item->branch_id,
                        'item_id'             => $item->id,
                        'action_type'         => $actionType,
                        'requested_qty'       => $qty,
                        'available_batch_qty' => max(0, $totalAvailable),
                        'reason'              => "Auto-deduction for {$actionType} (Ref #{$referenceId}) requested {$qty} units, but only {$totalAvailable} units were available in active batches. Shortfall: {$shortfall} untracked units.",
                        'reference_id'        => $referenceId,
                        'status'              => 'logged',
                    ]);
                }

                return true;
            });
        } catch (\Throwable $e) {
            // Fails silently per §4.1: Log quietly without bubbling error to cashier
            Log::warning("BatchTrackingService::deductFefo failed silently for item #{$item->id}: " . $e->getMessage(), [
                'item_id'      => $item->id,
                'qty'          => $qty,
                'action_type'  => $actionType,
                'reference_id' => $referenceId,
                'exception'    => $e,
            ]);

            try {
                ItemBatchSyncIssue::create([
                    'branch_id'           => $item->branch_id,
                    'item_id'             => $item->id,
                    'action_type'         => $actionType,
                    'requested_qty'       => $qty,
                    'available_batch_qty' => 0,
                    'reason'              => "Unexpected exception during FEFO auto-deduction: " . $e->getMessage(),
                    'reference_id'        => $referenceId,
                    'status'              => 'logged',
                ]);
            } catch (\Throwable) {
                // Secondary safety net: never throw
            }

            return false;
        }
    }

    /**
     * Create a new batch record for an item.
     */
    public static function createBatch(array $data, int $branchId, ?User $user = null): ItemBatch
    {
        $expiry = Carbon::parse($data['expiry_date'])->startOfDay();
        $isExpired = $expiry->isPast();

        $batch = ItemBatch::create([
            'branch_id'     => $branchId,
            'item_id'       => (int) $data['item_id'],
            'batch_label'   => !empty($data['batch_label']) ? trim($data['batch_label']) : null,
            'quantity'      => max(1, (int) $data['quantity']),
            'expiry_date'   => $expiry->toDateString(),
            'received_date' => !empty($data['received_date']) ? Carbon::parse($data['received_date'])->toDateString() : Carbon::today()->toDateString(),
            'cost_price'    => isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            'status'        => $isExpired ? 'expired' : 'active',
        ]);

        ActivityLogger::log(
            'batch',
            "Created batch '{$batch->batch_label}' for item #{$batch->item_id} (Qty: {$batch->quantity}, Expiry: {$batch->expiry_date})",
            $branchId,
            ['batch_id' => $batch->id, 'item_id' => $batch->item_id, 'quantity' => $batch->quantity]
        );

        return $batch;
    }

    /**
     * Update an existing batch.
     */
    public static function updateBatch(ItemBatch $batch, array $data): ItemBatch
    {
        $expiry = Carbon::parse($data['expiry_date'])->startOfDay();
        $isExpired = $expiry->isPast();

        $status = $data['status'] ?? $batch->status;
        if ($isExpired && $status === 'active') {
            $status = 'expired';
        }

        $batch->update([
            'batch_label'   => !empty($data['batch_label']) ? trim($data['batch_label']) : null,
            'quantity'      => max(0, (int) $data['quantity']),
            'expiry_date'   => $expiry->toDateString(),
            'received_date' => !empty($data['received_date']) ? Carbon::parse($data['received_date'])->toDateString() : $batch->received_date,
            'cost_price'    => isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            'status'        => $status,
        ]);

        return $batch;
    }

    /**
     * Clear / dispose of a batch (stock physically removed or disposed).
     */
    public static function clearBatch(ItemBatch $batch, ?string $reason = null, ?User $user = null): void
    {
        $batch->update(['status' => 'cleared']);

        ActivityLogger::log(
            'batch',
            "Cleared / Disposed batch #{$batch->id} (Label: '{$batch->batch_label}') for item #{$batch->item_id}. Reason: " . ($reason ?: 'Manually cleared by staff'),
            $batch->branch_id,
            ['batch_id' => $batch->id, 'item_id' => $batch->item_id, 'reason' => $reason]
        );
    }

    /**
     * Calculate variance between core stock count and total active batch count.
     */
    public static function computeItemVariance(Item $item): array
    {
        $actualStock = ((int) ($item->front_store_qty ?? 0)) + ((int) ($item->back_store_qty ?? 0));

        $batchTotal = (int) ItemBatch::where('item_id', $item->id)
            ->where('branch_id', $item->branch_id)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->sum('quantity');

        $variance = $actualStock - $batchTotal;

        return [
            'item_id'          => $item->id,
            'item_name'        => $item->item_name,
            'barcode_number'   => $item->barcode_number,
            'actual_stock'     => $actualStock,
            'batch_total'      => $batchTotal,
            'variance'         => $variance,
            'untracked_units'  => max(0, $variance),
            'excess_units'     => max(0, -$variance),
            'is_synced'        => $variance === 0,
        ];
    }

    /**
     * Scan active batches and generate notifications for batches expiring soon or expired.
     */
    public static function checkAndGenerateExpiryNotifications(?int $branchId = null): int
    {
        $query = Branch::where('is_active', true);
        if ($branchId) {
            $query->where('id', $branchId);
        }

        $branches = $query->get();
        $notificationsCreated = 0;
        $today = Carbon::today();

        foreach ($branches as $branch) {
            $settings = PosSettings::forBranch($branch->id);
            if (!$settings->batch_tracking_enabled) {
                continue;
            }

            $alertDays = max(1, (int) ($settings->expiry_alert_days ?? 30));
            $targetDate = $today->copy()->addDays($alertDays)->toDateString();

            // 1. Mark past-due active batches as expired
            ItemBatch::where('branch_id', $branch->id)
                ->where('status', 'active')
                ->where('expiry_date', '<', $today->toDateString())
                ->update(['status' => 'expired']);

            // 2. Fetch all upcoming expiries (within alert window) and currently expired batches
            $expiringBatches = ItemBatch::where('branch_id', $branch->id)
                ->where('quantity', '>', 0)
                ->whereIn('status', ['active', 'expired'])
                ->where('expiry_date', '<=', $targetDate)
                ->with('item:id,item_name,barcode_number')
                ->get();

            foreach ($expiringBatches as $batch) {
                $expiryDate = Carbon::parse($batch->expiry_date)->startOfDay();
                $isExpired = $expiryDate->isPast();
                $daysRemaining = (int) $today->diffInDays($expiryDate, false);

                // Prevent duplicate notifications within 24 hours for the same batch
                $existing = AppNotification::where('branch_id', $branch->id)
                    ->where('type', 'batch_expiry')
                    ->where('reference_type', ItemBatch::class)
                    ->where('reference_id', $batch->id)
                    ->where('created_at', '>=', Carbon::now()->subHours(24))
                    ->exists();

                if ($existing) {
                    continue;
                }

                $itemName = $batch->item?->item_name ?? "Item #{$batch->item_id}";
                $lotLabel = $batch->batch_label ? " (Batch {$batch->batch_label})" : "";

                if ($isExpired) {
                    $title = "EXPIRED: {$itemName}{$lotLabel}";
                    $message = "Batch of {$batch->quantity} units expired on {$expiryDate->format('d M Y')}. Please remove or dispose of this stock.";
                } else {
                    $title = "Expiring Soon: {$itemName}{$lotLabel}";
                    $daysText = $daysRemaining === 0 ? "today" : ($daysRemaining === 1 ? "tomorrow" : "in {$daysRemaining} days");
                    $message = "{$batch->quantity} units expiring {$daysText} ({$expiryDate->format('d M Y')}).";
                }

                AppNotification::create([
                    'branch_id'      => $branch->id,
                    'user_id'        => null, // Branch-wide notification
                    'type'           => 'batch_expiry',
                    'title'          => $title,
                    'message'        => $message,
                    'action_url'     => "/{$branch->slug}/reports/expiry-overview?search=" . urlencode($itemName),
                    'reference_type' => ItemBatch::class,
                    'reference_id'   => $batch->id,
                    'data'           => [
                        'batch_id'       => $batch->id,
                        'item_id'        => $batch->item_id,
                        'item_name'      => $itemName,
                        'batch_label'    => $batch->batch_label,
                        'quantity'       => $batch->quantity,
                        'expiry_date'    => $batch->expiry_date,
                        'days_remaining' => $daysRemaining,
                        'is_expired'     => $isExpired,
                    ],
                    'is_read'        => false,
                    'is_resolved'    => false,
                ]);

                $notificationsCreated++;
            }
        }

        return $notificationsCreated;
    }
}
