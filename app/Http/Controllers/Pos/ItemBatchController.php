<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemBatchSyncIssue;
use App\Services\BatchTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemBatchController extends Controller
{
    /**
     * Store a new batch record for an item.
     */
    public function store(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $validated = $request->validate([
            'item_id'       => 'required|exists:items,id',
            'quantity'      => 'required|integer|min:1',
            'expiry_date'   => 'required|date',
            'batch_label'   => 'nullable|string|max:100',
            'cost_price'    => 'nullable|numeric|min:0',
            'received_date' => 'nullable|date',
        ]);

        $item = Item::where('branch_id', $branch->id)->findOrFail($validated['item_id']);

        $batch = BatchTrackingService::createBatch($validated, $branch->id, Auth::user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'batch'   => $batch,
                'message' => 'Batch created successfully.',
            ]);
        }

        return back()->with('success', "Batch '{$batch->batch_label}' created successfully for {$item->item_name}.");
    }

    /**
     * Update an existing batch record.
     */
    public function update(Request $request, $branchParam, ItemBatch $batch)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $batch->branch_id !== $branch->id) {
            abort(403, 'Unauthorized batch update.');
        }

        $validated = $request->validate([
            'batch_label'   => 'nullable|string|max:100',
            'quantity'      => 'required|integer|min:0',
            'expiry_date'   => 'required|date',
            'cost_price'    => 'nullable|numeric|min:0',
            'received_date' => 'nullable|date',
            'status'        => 'required|in:active,expired,cleared',
        ]);

        BatchTrackingService::updateBatch($batch, $validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'batch'   => $batch->fresh(),
                'message' => 'Batch updated successfully.',
            ]);
        }

        return back()->with('success', 'Batch updated successfully.');
    }

    /**
     * Clear or dispose of a batch.
     */
    public function destroy(Request $request, $branchParam, ItemBatch $batch)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $batch->branch_id !== $branch->id) {
            abort(403, 'Unauthorized action.');
        }

        $reason = $request->input('reason', 'Manually cleared by user');
        BatchTrackingService::clearBatch($batch, $reason, Auth::user());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Batch marked as cleared.',
            ]);
        }

        return back()->with('success', 'Batch cleared successfully.');
    }

    /**
     * Mark an item batch sync issue as resolved.
     */
    public function resolveIssue(Request $request, $branchParam, ItemBatchSyncIssue $issue)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $issue->branch_id !== $branch->id) {
            abort(403, 'Unauthorized action.');
        }

        $issue->update(['status' => 'resolved']);

        return back()->with('success', 'Batch sync issue marked as resolved.');
    }
}
