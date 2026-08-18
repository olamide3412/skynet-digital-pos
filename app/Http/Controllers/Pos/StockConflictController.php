<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\StockConflict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StockConflictController extends Controller
{
    public function index(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        $branchId = $branch?->id;

        $query = StockConflict::when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with([
                'sale:id,receipt_id,offline_sale_id,final_total,payment_method,created_at,user_id',
                'sale.user:id,name',
                'item:id,item_name,barcode_number,front_store_qty,back_store_qty',
                'resolver:id,name',
            ]);

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('imei_or_device_id', 'like', "%{$search}%")
                  ->orWhere('offline_sale_id', 'like', "%{$search}%")
                  ->orWhereHas('sale', function ($sq) use ($search) {
                      $sq->where('receipt_id', 'like', "%{$search}%");
                  });
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('conflict_type')) {
            $query->where('conflict_type', $type);
        }

        $conflicts = $query->latest()->paginate(20)->withQueryString();

        $stats = [
            'pending'   => StockConflict::when($branchId, fn($q) => $q->where('branch_id', $branchId))->where('status', 'pending_review')->count(),
            'resolved'  => StockConflict::when($branchId, fn($q) => $q->where('branch_id', $branchId))->where('status', 'resolved')->count(),
            'dismissed' => StockConflict::when($branchId, fn($q) => $q->where('branch_id', $branchId))->where('status', 'dismissed')->count(),
            'total'     => StockConflict::when($branchId, fn($q) => $q->where('branch_id', $branchId))->count(),
        ];

        return Inertia::render('Inventory/Conflicts', [
            'conflicts' => $conflicts,
            'filters'   => $request->only(['search', 'status', 'conflict_type']),
            'stats'     => $stats,
        ]);
    }

    public function resolve(Request $request, $branchParam = null, StockConflict $conflict = null)
    {
        $branch = current_branch() ?? ($branchParam ? \App\Models\Branch::where('slug', $branchParam)->first() : null);
        if ($branch && $conflict && $conflict->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to this conflict record.');
        }

        $data = $request->validate([
            'status'           => 'required|in:resolved,dismissed',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        $conflict->update([
            'status'           => $data['status'],
            'resolution_notes' => $data['resolution_notes'],
            'resolved_by'      => Auth::id(),
            'resolved_at'      => now(),
        ]);

        \App\Services\ActivityLogger::stock(
            "Marked stock conflict #{$conflict->id} ({$conflict->item_name}) as {$data['status']}",
            $branch?->id
        );

        return back()->with('success', 'Conflict updated successfully.');
    }
}
