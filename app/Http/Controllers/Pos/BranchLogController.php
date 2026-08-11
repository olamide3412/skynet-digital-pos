<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchLogController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $query = PosLog::where('branch_id', $branch->id)
            ->with(['user:id,name,email'])
            ->latest('id');

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // Filter by user (within branch)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Search text
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('log', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search);
                  });
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        $branchUsers = User::where('branch_id', $branch->id)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('Pos/Reports/Logs', [
            'logs'        => $logs,
            'branchUsers' => $branchUsers,
            'filters'     => $request->only(['action_type', 'user_id', 'search', 'start_date', 'end_date']),
        ]);
    }
}
