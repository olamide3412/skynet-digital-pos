<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = PosLog::with([
            'branch:id,name,slug',
            'user:id,name,email',
        ])->latest('id');

        // Filter by branch
        if ($request->filled('branch_id')) {
            if ($request->branch_id === 'system') {
                $query->whereNull('branch_id');
            } else {
                $query->where('branch_id', $request->branch_id);
            }
        }

        // Filter by action type
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // Filter by user
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

        // Search text in log, ip, details
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('log', 'like', $search)
                  ->orWhere('ip_address', 'like', $search)
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', $search)->orWhere('email', 'like', $search);
                  });
            });
        }

        $logs = $query->paginate(25)->withQueryString();

        $branches = Branch::select('id', 'name', 'slug')->orderBy('name')->get();
        $users    = User::select('id', 'name', 'email')->orderBy('name')->get();

        return Inertia::render('SuperAdmin/Logs/Index', [
            'logs'     => $logs,
            'branches' => $branches,
            'users'    => $users,
            'filters'  => $request->only(['branch_id', 'action_type', 'user_id', 'search', 'start_date', 'end_date']),
        ]);
    }
}
