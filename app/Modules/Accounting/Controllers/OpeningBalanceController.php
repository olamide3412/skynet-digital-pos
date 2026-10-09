<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PosSettings;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OpeningBalanceController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();
        $settings = PosSettings::forBranch($branch->id);

        $accounts = Account::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->get();

        // Check if opening balances have already been posted
        $existingEntry = JournalEntry::with('lines.account')
            ->where('branch_id', $branch->id)
            ->where('event', 'opening_balances')
            ->whereNull('reversal_of_id')
            ->whereDoesntHave('reversedBy')
            ->first();

        $existingLines = [];
        if ($existingEntry) {
            foreach ($existingEntry->lines as $line) {
                $existingLines[$line->account_id] = [
                    'debit'  => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ];
            }
        }

        return Inertia::render('Accounting/OpeningBalances/Index', [
            'accounts'              => $accounts,
            'existingEntry'         => $existingEntry,
            'existingLines'         => $existingLines,
            'accountingStartedAt'   => $settings->accounting_started_at?->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();
        $settings = PosSettings::forBranch($branch->id);

        $validated = $request->validate([
            'lines'              => 'required|array|min:1',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit'      => 'nullable|numeric|min:0',
            'lines.*.credit'     => 'nullable|numeric|min:0',
            'lines.*.memo'       => 'nullable|string|max:255',
        ]);

        $entryDate = $settings->accounting_started_at ? Carbon::parse($settings->accounting_started_at) : now();

        $lines = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($validated['lines'] as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            if ($debit == 0 && $credit == 0) {
                continue;
            }

            $lines[] = [
                'account_id' => (int) $line['account_id'],
                'debit'      => $debit,
                'credit'     => $credit,
                'memo'       => $line['memo'] ?? 'Opening balance',
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (empty($lines)) {
            return back()->with('error', 'Please enter at least one opening balance.');
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return back()->with('error', 'Opening balances must be balanced. Total Debits: ' . number_format($totalDebit, 2) . ', Total Credits: ' . number_format($totalCredit, 2));
        }

        // Reverse existing entry if one exists
        $existingEntry = JournalEntry::where('branch_id', $branch->id)
            ->where('event', 'opening_balances')
            ->whereNull('reversal_of_id')
            ->whereDoesntHave('reversedBy')
            ->first();

        if ($existingEntry) {
            PostingService::reverseEntry($existingEntry, 'Replaced by updated opening balances entry', auth()->user());
        }

        $entry = PostingService::post([
            'event'       => 'opening_balances',
            'branch_id'   => $branch->id,
            'created_by'  => auth()->id(),
            'entry_date'  => $entryDate,
            'description' => 'Opening Balances as of ' . $entryDate->toDateString(),
            'lines'       => $lines,
        ]);

        return redirect()->route('pos.accounting.journals.show', [
            'branch'  => $branch->slug,
            'journal' => $entry->id,
        ])->with('success', 'Opening balances recorded successfully.');
    }
}
