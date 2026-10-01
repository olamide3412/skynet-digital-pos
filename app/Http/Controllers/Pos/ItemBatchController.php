<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemBatch;
use App\Models\ItemBatchSyncIssue;
use App\Models\PosSettings;
use App\Services\BatchTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ItemBatchController extends Controller
{
    /**
     * Display a paginated list of batches for a specific item.
     */
    public function index(Request $request, $branchParam, Item $item)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $item->branch_id !== $branch->id) {
            abort(403, 'Unauthorized.');
        }

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $query = ItemBatch::where('branch_id', $branch->id)
            ->where('item_id', $item->id);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('batch_label', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        $now = now();
        $settings = PosSettings::current();
        $alertDays = (int) ($settings->expiry_alert_days ?? 30);
        $alertDate = now()->addDays($alertDays);

        if ($status === 'active') {
            $query->where('status', 'active');
        } elseif ($status === 'expiring_soon') {
            $query->where('status', 'active')
                ->whereBetween('expiry_date', [$now->toDateString(), $alertDate->toDateString()]);
        } elseif ($status === 'expired') {
            $query->where(function ($q) use ($now) {
                $q->where('status', 'expired')
                  ->orWhere(fn($sub) => $sub->where('status', 'active')->where('expiry_date', '<', $now->toDateString()));
            });
        } elseif ($status === 'cleared') {
            $query->where('status', 'cleared');
        }

        // FEFO order: soonest expiry date first, then id
        $batches = $query->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate(15)
            ->withQueryString();

        $actualStock = ((int) ($item->front_store_qty ?? 0)) + ((int) ($item->back_store_qty ?? 0));
        $activeBatchesTotal = (int) $item->batches()->where('status', 'active')->sum('quantity');
        $batchVariance = $actualStock - $activeBatchesTotal;

        $stats = [
            'total_batches'       => $item->batches()->count(),
            'active_batches'      => $item->batches()->where('status', 'active')->count(),
            'active_batches_qty'  => $activeBatchesTotal,
            'actual_stock'        => $actualStock,
            'batch_variance'      => $batchVariance,
            'expiring_soon_count' => $item->batches()->where('status', 'active')
                ->whereBetween('expiry_date', [$now->toDateString(), $alertDate->toDateString()])
                ->count(),
            'expired_count'       => $item->batches()->where(function ($q) use ($now) {
                $q->where('status', 'expired')
                  ->orWhere(fn($sub) => $sub->where('status', 'active')->where('expiry_date', '<', $now->toDateString()));
            })->count(),
            'cleared_count'       => $item->batches()->where('status', 'cleared')->count(),
            'alert_days'          => $alertDays,
        ];

        return Inertia::render('Items/Batches', [
            'item' => [
                'id'                     => $item->id,
                'item_name'              => $item->item_name,
                'barcode_number'         => $item->barcode_number,
                'unit_label'             => $item->unit_label ?? 'Unit',
                'front_store_qty'        => $item->front_store_qty ?? 0,
                'back_store_qty'         => $item->back_store_qty ?? 0,
                'buy_price'              => $item->buy_price,
                'price'                  => $item->price,
                'batch_tracking_enabled' => (bool) $item->batch_tracking_enabled,
                'category_name'          => $item->category?->name ?? 'Uncategorized',
            ],
            'batches' => $batches,
            'stats'   => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'settings' => $settings,
        ]);
    }

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
