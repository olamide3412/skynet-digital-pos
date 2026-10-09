<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\PostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $entries = JournalEntry::with(['lines.account', 'user'])
            ->where('branch_id', $branch->id)
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->from_date, fn ($q, $from) => $q->whereDate('entry_date', '>=', $from))
            ->when($request->to_date, fn ($q, $to) => $q->whereDate('entry_date', '<=', $to))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $accounts = Account::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);

        return Inertia::render('Accounting/Journals/Index', [
            'entries'  => $entries,
            'accounts' => $accounts,
            'filters'  => $request->only('search', 'status', 'from_date', 'to_date'),
        ]);
    }

    public function show(Request $request, $branchParam, JournalEntry $journal)
    {
        $branch = current_branch();
        if ($journal->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to journal entry.');
        }

        $journal->load(['lines.account', 'user', 'reversalOf', 'reversedBy']);

        if ($request->wantsJson()) {
            return response()->json($journal);
        }

        return Inertia::render('Accounting/Journals/Show', [
            'entry' => $journal,
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $validated = $request->validate([
            'entry_date'     => 'required|date',
            'description'    => 'required|string|max:500',
            'reference'      => 'nullable|string|max:100',
            'lines'          => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit'      => 'required|numeric|min:0',
            'lines.*.credit'     => 'required|numeric|min:0',
            'lines.*.memo'       => 'nullable|string|max:255',
        ]);

        $lines = array_map(function ($line) {
            return [
                'account_id' => (int) $line['account_id'],
                'debit'      => (float) $line['debit'],
                'credit'     => (float) $line['credit'],
                'memo'       => $line['memo'] ?? null,
            ];
        }, $validated['lines']);

        $desc = $validated['description'];
        if (!empty($validated['reference'])) {
            $desc .= " (Ref: {$validated['reference']})";
        }

        try {
            $entry = PostingService::post([
                'branch_id'    => $branch->id,
                'entry_date'   => Carbon::parse($validated['entry_date']),
                'source_type'  => 'manual',
                'source_id'    => null,
                'event'        => 'manual_entry',
                'description'  => $desc,
                'created_by'   => Auth::id(),
                'lines'        => $lines,
            ]);

            return back()->with('success', "Journal entry {$entry->entry_number} recorded successfully.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function reverse(Request $request, $branchParam, JournalEntry $journal)
    {
        $branch = current_branch();
        if ($journal->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to journal entry.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversal = PostingService::reverseEntry($journal, $validated['reason'], Auth::user());
            return back()->with('success', "Reversing entry {$reversal->entry_number} created successfully.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        $branch = current_branch();

        $entries = JournalEntry::with(['lines.account', 'user'])
            ->where('branch_id', $branch->id)
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('entry_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->from_date, fn ($q, $from) => $q->whereDate('entry_date', '>=', $from))
            ->when($request->to_date, fn ($q, $to) => $q->whereDate('entry_date', '<=', $to))
            ->orderBy('entry_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Journal Entries');

        $headers = ['Entry #', 'Date', 'Source', 'Event', 'Description', 'Account Code', 'Account Name', 'Debit', 'Credit', 'Line Memo', 'Status', 'Posted By'];
        $sheet->fromArray([$headers], null, 'A1');

        $r = 2;
        foreach ($entries as $entry) {
            $first = true;
            foreach ($entry->lines as $line) {
                $sheet->fromArray([[
                    $first ? $entry->entry_number : '',
                    $first ? $entry->entry_date->toDateString() : '',
                    $first ? ucfirst($entry->source_type ?? '') : '',
                    $first ? ($entry->event ?? '') : '',
                    $first ? $entry->description : '',
                    $line->account?->code ?? '',
                    $line->account?->name ?? '',
                    (float) $line->debit > 0 ? (float) $line->debit : '',
                    (float) $line->credit > 0 ? (float) $line->credit : '',
                    $line->memo ?? '',
                    $first ? ucfirst($entry->status) : '',
                    $first ? ($entry->user?->name ?? 'System') : '',
                ]], null, "A{$r}");
                $first = false;
                $r++;
            }
        }

        $filename = ($branch ? $branch->slug : 'branch') . "_journals_" . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $writer->setUseBOM(true);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}
