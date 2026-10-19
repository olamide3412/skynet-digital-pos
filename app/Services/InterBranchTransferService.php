<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\InterBranchTransfer;
use App\Models\InterBranchTransferItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\PosSettings;
use App\Models\ProductGroup;
use App\Models\ProductGroupItem;
use App\Modules\Accounting\Services\InventoryPostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class InterBranchTransferService
{
    /**
     * Create a new inter-branch transfer from a sender branch.
     */
    public static function createTransfer(
        int $sourceBranchId,
        int $destinationBranchId,
        array $items,
        string $fromLocation = 'back_store',
        ?string $notes = null,
        ?int $userId = null
    ): InterBranchTransfer {
        return DB::transaction(function () use ($sourceBranchId, $destinationBranchId, $items, $fromLocation, $notes, $userId) {
            $sourceBranch = Branch::with('organization')->findOrFail($sourceBranchId);
            $destBranch = Branch::findOrFail($destinationBranchId);

            if ($sourceBranch->id === $destBranch->id) {
                throw new InvalidArgumentException("Source and destination branch cannot be the same.");
            }

            if ($sourceBranch->organization_id !== $destBranch->organization_id) {
                throw new InvalidArgumentException("Branches must belong to the same organization.");
            }

            // Check Plan Capability
            if ($sourceBranch->organization && !$sourceBranch->organization->hasFeature('inter_branch_transfers')) {
                throw new RuntimeException("Inter-branch transfers are available on Silver, Gold, and Enterprise plans. Please upgrade your plan.");
            }

            $fromLocation = in_array($fromLocation, ['back_store', 'front_store']) ? $fromLocation : 'back_store';
            $qtyField = $fromLocation === 'back_store' ? 'back_store_qty' : 'front_store_qty';

            // Generate unique reference
            $datePrefix = date('Ymd');
            $randomSuffix = strtoupper(Str::random(5));
            $reference = "IBT-{$datePrefix}-{$randomSuffix}";

            $hasUnitMismatch = false;
            $unitMismatchReasons = [];

            // 1. Create Transfer record
            $transfer = InterBranchTransfer::create([
                'organization_id'       => $sourceBranch->organization_id,
                'reference'             => $reference,
                'source_branch_id'      => $sourceBranch->id,
                'destination_branch_id' => $destBranch->id,
                'from_location'         => $fromLocation,
                'to_location'           => null, // Set on accept
                'status'                => 'pending',
                'notes'                 => $notes,
                'sent_by_user_id'       => $userId,
                'sent_at'               => now(),
            ]);

            // 2. Process Lines
            foreach ($items as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $qty = (int) ($line['quantity'] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $sourceItem = Item::where('branch_id', $sourceBranchId)->findOrFail($itemId);

                // Verify stock availability
                $available = (int) ($sourceItem->{$qtyField} ?? 0);
                if ($available < $qty) {
                    $locationName = $fromLocation === 'back_store' ? 'Back Store' : 'Front Store';
                    throw new RuntimeException("Insufficient stock for '{$sourceItem->item_name}' in {$locationName} (Available: {$available}, Requested: {$qty}).");
                }

                // Deduct stock immediately and record inventory transaction
                $prevSenderQty = $available;
                $newSenderQty = $available - $qty;
                $sourceItem->{$qtyField} = $newSenderQty;
                $sourceItem->save();

                $dispatchNote = "Inter-branch transfer out to {$destBranch->name} ({$transfer->reference})";
                if (!empty($line['notes'])) {
                    $dispatchNote .= " - Note: {$line['notes']}";
                } elseif (!empty($notes)) {
                    $dispatchNote .= " - Note: {$notes}";
                }

                InventoryTransaction::create([
                    'branch_id'        => $sourceBranchId,
                    'item_id'          => $sourceItem->id,
                    'transaction_type' => 'transfer',
                    'qty'              => -$qty,
                    'previous_qty'     => $prevSenderQty,
                    'new_qty'          => $newSenderQty,
                    'location'         => $fromLocation,
                    'reference_id'     => $transfer->reference,
                    'notes'            => $dispatchNote,
                    'user_id'          => $userId ?? auth()->id() ?? 1,
                ]);

                // Check linking and destination item
                $productGroupId = null;
                $destItemId = null;
                $sourceUnit = strtolower(trim($sourceItem->unit_label ?: 'pcs'));

                $groupItem = ProductGroupItem::where('item_id', $sourceItem->id)->first();
                if ($groupItem) {
                    $productGroupId = $groupItem->product_group_id;
                    $destGroupItem = ProductGroupItem::with('item')
                        ->where('product_group_id', $productGroupId)
                        ->where('branch_id', $destBranch->id)
                        ->first();

                    if ($destGroupItem && $destGroupItem->item) {
                        $destItemId = $destGroupItem->item_id;
                        $destUnit = strtolower(trim($destGroupItem->item->unit_label ?: 'pcs'));

                        if ($sourceUnit !== $destUnit) {
                            $hasUnitMismatch = true;
                            $unitMismatchReasons[] = "'{$sourceItem->item_name}' ({$sourceUnit}) does not match '{$destGroupItem->item->item_name}' ({$destUnit})";
                        }
                    }
                }

                InterBranchTransferItem::create([
                    'inter_branch_transfer_id' => $transfer->id,
                    'product_group_id'         => $productGroupId,
                    'source_item_id'           => $sourceItem->id,
                    'destination_item_id'      => $destItemId,
                    'quantity_sent'            => $qty,
                    'sender_cost_price'        => (float) ($sourceItem->buy_price ?? 0),
                    'status'                   => 'pending',
                    'notes'                    => $line['notes'] ?? null,
                ]);
            }

            // 3. If unit mismatch found, block the transfer
            if ($hasUnitMismatch) {
                $transfer->update([
                    'status'         => 'blocked',
                    'blocked_reason' => "Base unit mismatch detected on: " . implode('; ', $unitMismatchReasons),
                ]);
            }

            // 4. Accounting Entry: Sender Side (Dr 1300 / Cr 1200)
            try {
                InventoryPostingService::postStockTransferOut(
                    $transfer->source_branch_id,
                    $transfer->destination_branch_id,
                    $transfer->total_cost,
                    $transfer->reference,
                    $userId
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to post stock transfer out accounting: " . $e->getMessage());
            }

            // 5. Notification to Destination Branch
            try {
                AppNotification::create([
                    'branch_id'      => $destBranch->id,
                    'user_id'        => null,
                    'type'           => 'inter_branch_transfer',
                    'title'          => "Incoming Stock Transfer — {$transfer->reference}",
                    'message'        => "Branch '{$sourceBranch->name}' sent a stock transfer of " . count($items) . " item(s). Status: " . ucfirst($transfer->status),
                    'action_url'     => "/{$destBranch->slug}/inventory/transfers/inter-branch",
                    'reference_type' => InterBranchTransfer::class,
                    'reference_id'   => $transfer->id,
                    'data'           => [
                        'transfer_id'     => $transfer->id,
                        'reference'       => $transfer->reference,
                        'source_branch'   => $sourceBranch->name,
                        'items_count'     => count($items),
                        'status'          => $transfer->status,
                    ],
                ]);
            } catch (\Throwable $e) {
                // non-fatal
            }

            return $transfer->fresh(['items.sourceItem', 'items.destinationItem']);
        });
    }

    /**
     * Cancel a pending or blocked transfer and restore stock to sender's originating location.
     */
    public static function cancelTransfer(InterBranchTransfer $transfer, ?int $userId = null): InterBranchTransfer
    {
        return DB::transaction(function () use ($transfer, $userId) {
            $transfer->load(['items.sourceItem', 'sourceBranch', 'destinationBranch']);

            if (!in_array($transfer->status, ['pending', 'blocked'])) {
                throw new RuntimeException("Only pending or blocked transfers can be cancelled.");
            }

            $qtyField = $transfer->from_location === 'back_store' ? 'back_store_qty' : 'front_store_qty';

            // Restore stock to sender originating location and record inventory transaction
            foreach ($transfer->items as $itemLine) {
                if ($itemLine->sourceItem) {
                    $sourceItem = $itemLine->sourceItem;
                    $prevQty = (int) ($sourceItem->{$qtyField} ?? 0);
                    $newQty = $prevQty + $itemLine->quantity_sent;
                    $sourceItem->{$qtyField} = $newQty;
                    $sourceItem->save();

                    InventoryTransaction::create([
                        'branch_id'        => $transfer->source_branch_id,
                        'item_id'          => $sourceItem->id,
                        'transaction_type' => 'transfer',
                        'qty'              => $itemLine->quantity_sent,
                        'previous_qty'     => $prevQty,
                        'new_qty'          => $newQty,
                        'location'         => $transfer->from_location,
                        'reference_id'     => $transfer->reference,
                        'notes'            => "Inter-branch transfer cancelled ({$transfer->reference}) - Stock restored to " . ($transfer->from_location === 'back_store' ? 'Back Store' : 'Front Store'),
                        'user_id'          => $userId ?? auth()->id() ?? 1,
                    ]);
                }
            }

            // Accounting Reversal (Sender Side)
            try {
                InventoryPostingService::reverseStockTransferOut(
                    $transfer->source_branch_id,
                    $transfer->destination_branch_id,
                    $transfer->total_cost,
                    $transfer->reference,
                    $userId
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to reverse stock transfer accounting: " . $e->getMessage());
            }

            $transfer->update([
                'status'               => 'cancelled',
                'cancelled_at'         => now(),
                'cancelled_by_user_id' => $userId,
            ]);

            // Notify destination branch of cancellation
            try {
                AppNotification::create([
                    'branch_id'      => $transfer->destination_branch_id,
                    'user_id'        => null,
                    'type'           => 'inter_branch_transfer',
                    'title'          => "Transfer Cancelled — {$transfer->reference}",
                    'message'        => "Stock transfer {$transfer->reference} from '{$transfer->sourceBranch?->name}' was cancelled by sender.",
                    'action_url'     => "/{$transfer->destinationBranch?->slug}/inventory/transfers/inter-branch",
                    'reference_type' => InterBranchTransfer::class,
                    'reference_id'   => $transfer->id,
                ]);
            } catch (\Throwable $e) {}

            return $transfer;
        });
    }

    /**
     * Accept a transfer at the destination branch.
     * Records actual received quantity, deposits into Back Store or Front Store,
     * and supports on-the-fly linking for unlinked items.
     */
    public static function acceptTransfer(
        InterBranchTransfer $transfer,
        array $itemReceipts,
        string $toLocation = 'back_store',
        ?int $userId = null,
        ?string $receiverNotes = null
    ): InterBranchTransfer {
        return DB::transaction(function () use ($transfer, $itemReceipts, $toLocation, $userId, $receiverNotes) {
            $transfer->load(['items.sourceItem', 'items.destinationItem', 'items.productGroup', 'sourceBranch', 'destinationBranch']);

            if ($transfer->status === 'blocked') {
                throw new RuntimeException("This transfer is blocked due to unit mismatches. Base units must be resolved before acceptance.");
            }

            if ($transfer->status !== 'pending') {
                throw new RuntimeException("Transfer is not in pending status (Current: {$transfer->status}).");
            }

            $sourceBranch = Branch::find($transfer->source_branch_id);
            $destBranch = Branch::find($transfer->destination_branch_id);
            $sourceBranchName = $sourceBranch?->name ?? 'Sender Branch';
            $destBranchName = $destBranch?->name ?? 'Destination Branch';

            $fromLocation = in_array($transfer->from_location, ['back_store', 'front_store']) ? $transfer->from_location : 'back_store';
            $fromQtyField = $fromLocation === 'back_store' ? 'back_store_qty' : 'front_store_qty';

            $toLocation = in_array($toLocation, ['back_store', 'front_store']) ? $toLocation : 'back_store';
            $qtyField = $toLocation === 'back_store' ? 'back_store_qty' : 'front_store_qty';
            $receiptsByLineId = collect($itemReceipts)->keyBy('line_id');

            $totalShortfall = 0;
            $totalReceivedCost = 0.0;

            foreach ($transfer->items as $line) {
                $receiptData = $receiptsByLineId->get($line->id) ?? [];
                $receivedQty = isset($receiptData['quantity_received']) ? (int) $receiptData['quantity_received'] : $line->quantity_sent;

                // Handle on-the-fly linking if destination item is not yet linked
                if (!$line->destination_item_id) {
                    $linkAction = $receiptData['link_action'] ?? null;

                    if ($linkAction === 'existing' && !empty($receiptData['existing_item_id'])) {
                        // Link existing item in receiver's branch
                        $destItem = Item::where('branch_id', $transfer->destination_branch_id)->findOrFail($receiptData['existing_item_id']);

                        if ($line->product_group_id) {
                            $targetGroupId = $line->product_group_id;
                        } else {
                            $group = ProductLinkingService::createGroup(
                                $transfer->organization_id,
                                $line->sourceItem?->item_name ?: 'Product Group',
                                $userId,
                                $line->source_item_id,
                                'receiving_queue'
                            );
                            $targetGroupId = $group->id;
                            $line->product_group_id = $group->id;
                        }

                        $existingDestGi = ProductGroupItem::where('item_id', $destItem->id)->first();
                        if ($existingDestGi) {
                            if ($existingDestGi->product_group_id !== $targetGroupId) {
                                ProductLinkingService::mergeGroups($targetGroupId, $existingDestGi->product_group_id, $userId);
                            }
                        } else {
                            ProductLinkingService::linkItem(
                                $targetGroupId,
                                $transfer->destination_branch_id,
                                $destItem->id,
                                $userId,
                                'receiving_queue'
                            );
                        }

                        $line->destination_item_id = $destItem->id;
                    } elseif ($linkAction === 'copy' || !$line->destination_item_id) {
                        // Create item in destination branch via copy
                        $copyResult = ProductLinkingService::copyToBranches(
                            $line->source_item_id,
                            [$transfer->destination_branch_id],
                            true,
                            $userId
                        );

                        $newCreated = $copyResult['created_items'][0] ?? null;
                        if ($newCreated) {
                            $line->destination_item_id = $newCreated['item_id'];
                            $line->product_group_id = $copyResult['product_group_id'];
                        }
                    }
                }

                $destItem = $line->destination_item_id ? Item::find($line->destination_item_id) : null;
                $shortfall = max(0, $line->quantity_sent - $receivedQty);
                $sentQty = (int) $line->quantity_sent;

                // 1. Destination / Receiver Branch Inventory Log & Stock Credit
                if ($destItem) {
                    $prevReceiverQty = (int) ($destItem->{$qtyField} ?? 0);
                    $newReceiverQty = $prevReceiverQty + $receivedQty;

                    if ($receivedQty > 0) {
                        // Credit stock to receiver chosen location (Back Store or Front Store)
                        // Note: Receiving branch keeps its own cost price (§12-B). Cost is NOT modified.
                        $destItem->increment($qtyField, $receivedQty);
                    }

                    $receiverNote = "Inter-branch transfer in from {$sourceBranchName} ({$transfer->reference})";
                    if ($shortfall > 0) {
                        $receiverNote .= " [Sent: {$sentQty}, Received: {$receivedQty}, Shortfall: {$shortfall}]";
                    }
                    $customNote = !empty($receiverNotes) ? $receiverNotes : (!empty($line->notes) ? $line->notes : (!empty($transfer->notes) ? $transfer->notes : null));
                    if ($customNote) {
                        $receiverNote .= " - Note: {$customNote}";
                    }

                    InventoryTransaction::create([
                        'branch_id'        => $transfer->destination_branch_id,
                        'item_id'          => $destItem->id,
                        'transaction_type' => 'transfer',
                        'qty'              => $receivedQty,
                        'previous_qty'     => $prevReceiverQty,
                        'new_qty'          => $newReceiverQty,
                        'location'         => $toLocation,
                        'reference_id'     => $transfer->reference,
                        'notes'            => $receiverNote,
                        'user_id'          => $userId ?? auth()->id() ?? $transfer->sent_by_user_id ?? 1,
                    ]);
                }

                // If shortfall occurred, update sender dispatch transaction note to document shortfall
                if ($shortfall > 0) {
                    $senderTxn = InventoryTransaction::where('branch_id', $transfer->source_branch_id)
                        ->where('reference_id', $transfer->reference)
                        ->where('item_id', $line->source_item_id)
                        ->first();
                    if ($senderTxn && !str_contains($senderTxn->notes, 'Shortfall:')) {
                        $senderTxn->update([
                            'notes' => $senderTxn->notes . " [Shortfall: {$shortfall}]",
                        ]);
                    }
                }

                $line->update([
                    'destination_item_id' => $line->destination_item_id,
                    'product_group_id'    => $line->product_group_id,
                    'quantity_received'   => $receivedQty,
                    'shortfall_quantity'  => $shortfall,
                    'status'              => $shortfall > 0 ? 'discrepancy' : 'accepted',
                ]);

                $totalShortfall += $shortfall;
                $totalReceivedCost += (float) ($line->sender_cost_price * $receivedQty);
            }

            $transfer->update([
                'to_location'          => $toLocation,
                'status'               => 'accepted',
                'receiver_notes'       => $receiverNotes,
                'accepted_at'          => now(),
                'accepted_by_user_id'  => $userId,
            ]);

            // Accounting Entry: Receiver Side (Dr 1200 / Cr 2200 for received quantity)
            try {
                InventoryPostingService::postStockTransferIn(
                    $transfer->source_branch_id,
                    $transfer->destination_branch_id,
                    $totalReceivedCost,
                    $transfer->reference,
                    $userId
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to post stock transfer in accounting: " . $e->getMessage());
            }

            // If shortfall occurred, dispatch discrepancy notification
            if ($totalShortfall > 0) {
                try {
                    AppNotification::create([
                        'branch_id'      => $transfer->source_branch_id,
                        'user_id'        => null,
                        'type'           => 'stock_conflict',
                        'title'          => "Transfer Shortfall Discrepancy — {$transfer->reference}",
                        'message'        => "Receiver reported a shortfall of {$totalShortfall} unit(s) on transfer {$transfer->reference}.",
                        'action_url'     => "/{$transfer->sourceBranch?->slug}/inventory/transfers/inter-branch",
                        'reference_type' => InterBranchTransfer::class,
                        'reference_id'   => $transfer->id,
                        'data'           => [
                            'transfer_id' => $transfer->id,
                            'reference'   => $transfer->reference,
                            'shortfall'   => $totalShortfall,
                        ],
                    ]);
                } catch (\Throwable $e) {}
            }

            return $transfer->fresh(['items.sourceItem', 'items.destinationItem']);
        });
    }

    /**
     * Resolve a blocked transfer by re-evaluating unit compatibility.
     * If units now match, status moves back to 'pending'.
     */
    public static function checkAndUnblock(InterBranchTransfer $transfer): bool
    {
        if ($transfer->status !== 'blocked') {
            return false;
        }

        $transfer->load(['items.sourceItem', 'items.destinationItem']);

        $hasMismatch = false;
        foreach ($transfer->items as $line) {
            if ($line->destination_item_id) {
                $sourceUnit = strtolower(trim($line->sourceItem?->unit_label ?: 'pcs'));
                $destUnit = strtolower(trim($line->destinationItem?->unit_label ?: 'pcs'));
                if ($sourceUnit !== $destUnit) {
                    $hasMismatch = true;
                    break;
                }
            }
        }

        if (!$hasMismatch) {
            $transfer->update([
                'status'         => 'pending',
                'blocked_reason' => null,
            ]);
            return true;
        }

        return false;
    }
}
