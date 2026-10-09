<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class LedgerController extends Controller
{
    public function getLedgerData(Request $request): array
    {
        $branch = current_branch();

        $accounts = Account::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);

        $selectedAccountId = $request->account_id ?: ($accounts->first()?->id ?? null);
        $selectedAccount = $selectedAccountId ? Account::find($selectedAccountId) : null;

        $fromDate = $request->from_date ?: now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?: now()->toDateString();

        $lines = [];
        $openingBalance = 0;
        $totalDebits = 0;
        $totalCredits = 0;
        $closingBalance = 0;

        if ($selectedAccount) {
            // Opening balance before fromDate
            $priorLines = JournalLine::where('account_id', $selectedAccount->id)
                ->whereHas('entry', function ($q) use ($fromDate) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '<', $fromDate);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $priorDebit = (float) ($priorLines->total_debit ?? 0);
            $priorCredit = (float) ($priorLines->total_credit ?? 0);

            if ($selectedAccount->normal_balance === 'debit') {
                $openingBalance = $priorDebit - $priorCredit;
            } else {
                $openingBalance = $priorCredit - $priorDebit;
            }

            // Entries in date range
            $records = JournalLine::with('entry')
                ->where('account_id', $selectedAccount->id)
                ->whereHas('entry', function ($q) use ($fromDate, $toDate) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '>=', $fromDate)
                      ->whereDate('entry_date', '<=', $toDate);
                })
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->orderBy('journal_entries.entry_date', 'asc')
                ->orderBy('journal_entries.id', 'asc')
                ->select('journal_lines.*')
                ->get();

            $currentBalance = $openingBalance;
            foreach ($records as $rec) {
                $d = (float) $rec->debit;
                $c = (float) $rec->credit;
                $totalDebits += $d;
                $totalCredits += $c;

                if ($selectedAccount->normal_balance === 'debit') {
                    $currentBalance += ($d - $c);
                } else {
                    $currentBalance += ($c - $d);
                }

                $lines[] = [
                    'id'           => $rec->id,
                    'date'         => $rec->entry->entry_date->toDateString(),
                    'entry_number' => $rec->entry->entry_number,
                    'reference'    => $rec->entry->reference,
                    'description'  => $rec->entry->description,
                    'memo'         => $rec->memo,
                    'source_type'  => $rec->entry->source_type,
                    'source_id'    => $rec->entry->source_id,
                    'debit'        => $d,
                    'credit'       => $c,
                    'balance'      => $currentBalance,
                ];
            }

            $closingBalance = $currentBalance;
        }

        return [
            'accounts'        => $accounts,
            'selectedAccount' => $selectedAccount,
            'openingBalance'  => round($openingBalance, 2),
            'closingBalance'  => round($closingBalance, 2),
            'totalDebits'     => round($totalDebits, 2),
            'totalCredits'    => round($totalCredits, 2),
            'lines'           => $lines,
            'filters'         => [
                'account_id' => $selectedAccountId,
                'from_date'  => $fromDate,
                'to_date'    => $toDate,
            ],
        ];
    }

    public function index(Request $request)
    {
        $data = $this->getLedgerData($request);
        return Inertia::render('Accounting/Ledger/Index', $data);
    }

    public function export(Request $request)
    {
        $data = $this->getLedgerData($request);
        $account = $data['selectedAccount'];
        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('General Ledger');

        $headers = ['Date', 'Entry #', 'Description', 'Memo', 'Source', 'Debit', 'Credit', 'Balance'];
        $sheet->fromArray([$headers], null, 'A1');

        $r = 2;
        $sheet->fromArray([[
            $data['filters']['from_date'],
            'OPENING',
            'Opening Balance',
            '',
            '',
            '',
            '',
            $data['openingBalance'],
        ]], null, "A{$r}");
        $r++;

        foreach ($data['lines'] as $line) {
            $sheet->fromArray([[
                $line['date'],
                $line['entry_number'],
                $line['description'],
                $line['memo'] ?? '',
                ucfirst($line['source_type'] ?? ''),
                $line['debit'] > 0 ? $line['debit'] : '',
                $line['credit'] > 0 ? $line['credit'] : '',
                $line['balance'],
            ]], null, "A{$r}");
            $r++;
        }

        $sheet->fromArray([[
            $data['filters']['to_date'],
            'CLOSING',
            'Totals / Closing Balance',
            '',
            '',
            $data['totalDebits'],
            $data['totalCredits'],
            $data['closingBalance'],
        ]], null, "A{$r}");

        $branch = current_branch();
        $code = $account ? $account->code : 'all';
        $filename = ($branch ? $branch->slug : 'branch') . "_ledger_{$code}_" . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new XlsxWriter($spreadsheet);
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
