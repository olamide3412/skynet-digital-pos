<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\FinancialPeriod;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PeriodController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $periods = FinancialPeriod::with('closedByUser')
            ->where('branch_id', $branch->id)
            ->orderByDesc('starts_on')
            ->get();

        return Inertia::render('Accounting/Periods/Index', [
            'periods' => $periods,
        ]);
    }

    public function close(Request $request, $branchParam, FinancialPeriod $period)
    {
        $branch = current_branch();
        if ($period->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to financial period.');
        }

        if ($period->status === 'closed') {
            return back()->with('info', "Period {$period->name} is already closed.");
        }

        $period->update([
            'status'    => 'closed',
            'closed_at' => now(),
            'closed_by' => Auth::id(),
        ]);

        return back()->with('success', "Financial period {$period->name} closed successfully.");
    }

    public function reopen(Request $request, $branchParam, FinancialPeriod $period)
    {
        $branch = current_branch();
        if ($period->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to financial period.');
        }

        if ($period->status === 'open') {
            return back()->with('info', "Period {$period->name} is already open.");
        }

        $period->update([
            'status'    => 'open',
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return back()->with('success', "Financial period {$period->name} reopened successfully.");
    }
}
