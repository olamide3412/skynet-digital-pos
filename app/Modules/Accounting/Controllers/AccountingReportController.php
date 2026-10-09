<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class AccountingReportController extends Controller
{
    /**
     * Resolve target branch(es) for reporting.
     * Supports single branch or multi-branch consolidated organization roll-up (Decision 1 & §8).
     */
    protected function resolveReportingScope(Request $request): array
    {
        $currentBranch = current_branch();
        $isConsolidated = $request->boolean('consolidated') || $request->get('branch_id') === 'all';

        if ($isConsolidated && $currentBranch && $currentBranch->organization_id) {
            $activeBranches = Branch::where('organization_id', $currentBranch->organization_id)
                ->where('is_active', true)
                ->get()
                ->filter(function ($b) {
                    $settings = PosSettings::forBranch($b->id);
                    return !empty($settings->accounting_started_at);
                });

            $branchIds = $activeBranches->pluck('id')->all();
            $branchesIncluded = $activeBranches->map(fn($b) => [
                'id'         => $b->id,
                'name'       => $b->name,
                'slug'       => $b->slug,
                'started_at' => PosSettings::forBranch($b->id)->accounting_started_at?->toDateString(),
            ])->values()->all();

            return [
                'branch_ids'        => $branchIds,
                'is_consolidated'   => true,
                'branches_included' => $branchesIncluded,
                'current_branch'    => $currentBranch,
            ];
        }

        $branchId = $currentBranch ? $currentBranch->id : null;
        $branchesIncluded = [];
        if ($currentBranch) {
            $settings = PosSettings::forBranch($currentBranch->id);
            $branchesIncluded[] = [
                'id'         => $currentBranch->id,
                'name'       => $currentBranch->name,
                'slug'       => $currentBranch->slug,
                'started_at' => $settings->accounting_started_at?->toDateString(),
            ];
        }

        return [
            'branch_ids'        => $branchId ? [$branchId] : [],
            'is_consolidated'   => false,
            'branches_included' => $branchesIncluded,
            'current_branch'    => $currentBranch,
        ];
    }

    public function getTrialBalanceData(Request $request): array
    {
        $scope = $this->resolveReportingScope($request);
        $asOfDate = $request->as_of_date ?: now()->toDateString();

        $accounts = Account::whereIn('branch_id', $scope['branch_ids'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $grouped = $accounts->groupBy('code');

        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($grouped as $code => $accList) {
            $firstAcc = $accList->first();
            $accountIds = $accList->pluck('id')->all();

            $sums = JournalLine::whereIn('account_id', $accountIds)
                ->whereHas('entry', function ($q) use ($asOfDate) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '<=', $asOfDate);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $d = (float) ($sums->total_debit ?? 0);
            $c = (float) ($sums->total_credit ?? 0);

            if ($d == 0 && $c == 0) {
                continue;
            }

            $debitBalance = 0;
            $creditBalance = 0;

            if ($firstAcc->normal_balance === 'debit') {
                $net = $d - $c;
                if ($net >= 0) {
                    $debitBalance = $net;
                } else {
                    $creditBalance = abs($net);
                }
            } else {
                $net = $c - $d;
                if ($net >= 0) {
                    $creditBalance = $net;
                } else {
                    $debitBalance = abs($net);
                }
            }

            $totalDebit += $debitBalance;
            $totalCredit += $creditBalance;

            $rows[] = [
                'id'             => $firstAcc->id,
                'code'           => $code,
                'name'           => $firstAcc->name,
                'type'           => $firstAcc->type,
                'debit'          => round($debitBalance, 2),
                'credit'         => round($creditBalance, 2),
            ];
        }

        return [
            'rows'              => $rows,
            'totalDebit'        => round($totalDebit, 2),
            'totalCredit'       => round($totalCredit, 2),
            'isBalanced'        => round($totalDebit, 2) === round($totalCredit, 2),
            'filters'           => ['as_of_date' => $asOfDate],
            'isConsolidated'    => $scope['is_consolidated'],
            'branchesIncluded'  => $scope['branches_included'],
        ];
    }

    public function trialBalance(Request $request)
    {
        $data = $this->getTrialBalanceData($request);
        return Inertia::render('Accounting/Reports/TrialBalance', $data);
    }

    public function getProfitLossData(Request $request): array
    {
        $scope = $this->resolveReportingScope($request);
        $fromDate = $request->from_date ?: now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?: now()->toDateString();

        $accounts = Account::whereIn('branch_id', $scope['branch_ids'])
            ->whereIn('type', ['revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $grouped = $accounts->groupBy('code');

        $revenues = [];
        $cogs = [];
        $expenses = [];

        $totalRevenue = 0;
        $totalCogs = 0;
        $totalExpenses = 0;

        foreach ($grouped as $code => $accList) {
            $firstAcc = $accList->first();
            $accountIds = $accList->pluck('id')->all();

            $sums = JournalLine::whereIn('account_id', $accountIds)
                ->whereHas('entry', function ($q) use ($fromDate, $toDate) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '>=', $fromDate)
                      ->whereDate('entry_date', '<=', $toDate);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $d = (float) ($sums->total_debit ?? 0);
            $c = (float) ($sums->total_credit ?? 0);

            if ($firstAcc->type === 'revenue') {
                $amount = $c - $d; // Credit normal
                if ($amount != 0) {
                    $revenues[] = ['code' => $code, 'name' => $firstAcc->name, 'amount' => round($amount, 2)];
                    $totalRevenue += $amount;
                }
            } elseif ($firstAcc->type === 'expense') {
                $amount = $d - $c; // Debit normal
                if (str_starts_with($code, '5')) {
                    if ($amount != 0) {
                        $cogs[] = ['code' => $code, 'name' => $firstAcc->name, 'amount' => round($amount, 2)];
                        $totalCogs += $amount;
                    }
                } else {
                    if ($amount != 0) {
                        $expenses[] = ['code' => $code, 'name' => $firstAcc->name, 'amount' => round($amount, 2)];
                        $totalExpenses += $amount;
                    }
                }
            }
        }

        $grossProfit = $totalRevenue - $totalCogs;
        $netProfit = $grossProfit - $totalExpenses;

        return [
            'revenues'          => $revenues,
            'cogs'              => $cogs,
            'expenses'          => $expenses,
            'totalRevenue'      => round($totalRevenue, 2),
            'totalCogs'         => round($totalCogs, 2),
            'grossProfit'       => round($grossProfit, 2),
            'totalExpenses'     => round($totalExpenses, 2),
            'netProfit'         => round($netProfit, 2),
            'filters'           => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ],
            'isConsolidated'    => $scope['is_consolidated'],
            'branchesIncluded'  => $scope['branches_included'],
        ];
    }

    public function profitLoss(Request $request)
    {
        $data = $this->getProfitLossData($request);
        return Inertia::render('Accounting/Reports/ProfitLoss', $data);
    }

    public function getBalanceSheetData(Request $request): array
    {
        $scope = $this->resolveReportingScope($request);
        $asOfDate = $request->as_of_date ?: now()->toDateString();

        $accounts = Account::whereIn('branch_id', $scope['branch_ids'])
            ->whereIn('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])
            ->orderBy('code')
            ->get();

        $grouped = $accounts->groupBy('code');

        $assets = [];
        $liabilities = [];
        $equity = [];

        $totalAssets = 0;
        $totalLiabilities = 0;
        $totalEquity = 0;

        $accumulatedRevenue = 0;
        $accumulatedExpenses = 0;

        foreach ($grouped as $code => $accList) {
            $firstAcc = $accList->first();
            $accountIds = $accList->pluck('id')->all();

            $sums = JournalLine::whereIn('account_id', $accountIds)
                ->whereHas('entry', function ($q) use ($asOfDate) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '<=', $asOfDate);
                })
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $d = (float) ($sums->total_debit ?? 0);
            $c = (float) ($sums->total_credit ?? 0);

            if ($firstAcc->type === 'asset') {
                $amount = $d - $c;
                if ($amount != 0) {
                    $assets[] = ['code' => $code, 'name' => $firstAcc->name, 'amount' => round($amount, 2)];
                    $totalAssets += $amount;
                }
            } elseif ($firstAcc->type === 'liability') {
                $amount = $c - $d;
                if ($amount != 0) {
                    $liabilities[] = ['code' => $code, 'name' => $firstAcc->name, 'amount' => round($amount, 2)];
                    $totalLiabilities += $amount;
                }
            } elseif ($firstAcc->type === 'equity') {
                $amount = $c - $d;
                if ($amount != 0) {
                    $equity[] = ['code' => $code, 'name' => $firstAcc->name, 'amount' => round($amount, 2)];
                    $totalEquity += $amount;
                }
            } elseif ($firstAcc->type === 'revenue') {
                $accumulatedRevenue += ($c - $d);
            } elseif ($firstAcc->type === 'expense') {
                $accumulatedExpenses += ($d - $c);
            }
        }

        $retainedEarnings = $accumulatedRevenue - $accumulatedExpenses;
        $totalEquityWithRetainedEarnings = $totalEquity + $retainedEarnings;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithRetainedEarnings;

        return [
            'assets'                    => $assets,
            'liabilities'               => $liabilities,
            'equity'                    => $equity,
            'retainedEarnings'          => round($retainedEarnings, 2),
            'totalAssets'               => round($totalAssets, 2),
            'totalLiabilities'          => round($totalLiabilities, 2),
            'totalEquity'               => round($totalEquityWithRetainedEarnings, 2),
            'totalLiabilitiesAndEquity' => round($totalLiabilitiesAndEquity, 2),
            'isBalanced'                => round($totalAssets, 2) === round($totalLiabilitiesAndEquity, 2),
            'filters'                   => ['as_of_date' => $asOfDate],
            'isConsolidated'            => $scope['is_consolidated'],
            'branchesIncluded'          => $scope['branches_included'],
        ];
    }

    public function balanceSheet(Request $request)
    {
        $data = $this->getBalanceSheetData($request);
        return Inertia::render('Accounting/Reports/BalanceSheet', $data);
    }

    public function getVatData(Request $request): array
    {
        $scope = $this->resolveReportingScope($request);
        $fromDate = $request->from_date ?: now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?: now()->toDateString();

        $vatAccounts = Account::whereIn('branch_id', $scope['branch_ids'])
            ->where('code', '2100')
            ->pluck('id')
            ->all();

        $lines = [];
        $totalOutputVat = 0;
        $totalInputVatOrReturns = 0;

        if (!empty($vatAccounts)) {
            $records = JournalLine::with('entry')
                ->whereIn('account_id', $vatAccounts)
                ->whereHas('entry', function ($q) use ($fromDate, $toDate) {
                    $q->where('status', 'posted')
                      ->whereDate('entry_date', '>=', $fromDate)
                      ->whereDate('entry_date', '<=', $toDate);
                })
                ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
                ->orderBy('journal_entries.entry_date', 'asc')
                ->select('journal_lines.*')
                ->get();

            foreach ($records as $r) {
                $d = (float) $r->debit;
                $c = (float) $r->credit;
                $totalInputVatOrReturns += $d;
                $totalOutputVat += $c;

                $lines[] = [
                    'id'           => $r->id,
                    'date'         => $r->entry->entry_date->toDateString(),
                    'entry_number' => $r->entry->entry_number,
                    'description'  => $r->entry->description,
                    'reference'    => $r->entry->event,
                    'debit'        => round($d, 2),
                    'credit'       => round($c, 2),
                ];
            }
        }

        $netVatPayable = $totalOutputVat - $totalInputVatOrReturns;

        return [
            'lines'                  => $lines,
            'totalOutputVat'         => round($totalOutputVat, 2),
            'totalInputVatOrReturns' => round($totalInputVatOrReturns, 2),
            'netVatPayable'          => round($netVatPayable, 2),
            'filters'                => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ],
            'isConsolidated'         => $scope['is_consolidated'],
            'branchesIncluded'       => $scope['branches_included'],
        ];
    }

    public function vat(Request $request)
    {
        $data = $this->getVatData($request);
        return Inertia::render('Accounting/Reports/Vat', $data);
    }

    public function getCashFlowData(Request $request): array
    {
        $scope = $this->resolveReportingScope($request);
        $fromDate = $request->from_date ?: now()->startOfMonth()->toDateString();
        $toDate = $request->to_date ?: now()->toDateString();

        // Direct cash & bank accounts (1000, 1010, 1020)
        $cashAccounts = Account::whereIn('branch_id', $scope['branch_ids'])
            ->whereIn('code', ['1000', '1010', '1020'])
            ->pluck('id')
            ->all();

        // Beginning cash balance (prior to fromDate)
        $priorSums = JournalLine::whereIn('account_id', $cashAccounts)
            ->whereHas('entry', function ($q) use ($fromDate) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '<', $fromDate);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $beginningCash = (float) ($priorSums->total_debit ?? 0) - (float) ($priorSums->total_credit ?? 0);

        // Period movements
        $periodLines = JournalLine::with('entry')
            ->whereIn('account_id', $cashAccounts)
            ->whereHas('entry', function ($q) use ($fromDate, $toDate) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '>=', $fromDate)
                  ->whereDate('entry_date', '<=', $toDate);
            })
            ->get();

        $operatingInflows = [];
        $operatingOutflows = [];
        $totalInflow = 0;
        $totalOutflow = 0;

        foreach ($periodLines as $line) {
            $d = (float) $line->debit;
            $c = (float) $line->credit;

            if ($d > 0) {
                $totalInflow += $d;
                $label = $line->memo ?: ($line->entry?->description ?: 'Cash Inflow');
                $operatingInflows[] = [
                    'date'   => $line->entry?->entry_date?->toDateString(),
                    'label'  => $label,
                    'amount' => round($d, 2),
                ];
            }

            if ($c > 0) {
                $totalOutflow += $c;
                $label = $line->memo ?: ($line->entry?->description ?: 'Cash Outflow');
                $operatingOutflows[] = [
                    'date'   => $line->entry?->entry_date?->toDateString(),
                    'label'  => $label,
                    'amount' => round($c, 2),
                ];
            }
        }

        $netCashChange = $totalInflow - $totalOutflow;
        $endingCash = $beginningCash + $netCashChange;

        return [
            'beginningCash'     => round($beginningCash, 2),
            'operatingInflows'  => $operatingInflows,
            'operatingOutflows' => $operatingOutflows,
            'totalInflows'      => round($totalInflow, 2),
            'totalOutflows'     => round($totalOutflow, 2),
            'netCashChange'     => round($netCashChange, 2),
            'endingCash'        => round($endingCash, 2),
            'filters'           => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
            ],
            'isConsolidated'    => $scope['is_consolidated'],
            'branchesIncluded'  => $scope['branches_included'],
        ];
    }

    public function cashFlow(Request $request)
    {
        $data = $this->getCashFlowData($request);
        return Inertia::render('Accounting/Reports/CashFlow', $data);
    }

    public function getArAgingData(Request $request): array
    {
        $scope = $this->resolveReportingScope($request);
        $asOfDate = $request->as_of_date ? Carbon::parse($request->as_of_date) : now();

        $arAccounts = Account::whereIn('branch_id', $scope['branch_ids'])
            ->where('code', '1100')
            ->pluck('id')
            ->all();

        // Unsettled debits on Accounts Receivable (1100) grouped by customer
        $lines = JournalLine::with(['entry', 'customer'])
            ->whereIn('account_id', $arAccounts)
            ->whereHas('entry', function ($q) use ($asOfDate) {
                $q->where('status', 'posted')
                  ->whereDate('entry_date', '<=', $asOfDate->toDateString());
            })
            ->get();

        $customerBuckets = [];
        $total0To30 = 0;
        $total31To60 = 0;
        $total61To90 = 0;
        $totalOver90 = 0;
        $totalAr = 0;

        $groupedByCustomer = $lines->groupBy('customer_id');

        foreach ($groupedByCustomer as $customerId => $custLines) {
            $customerName = 'General Debtors';
            if ($customerId) {
                $cObj = PosCustomer::find($customerId);
                if ($cObj) {
                    $customerName = $cObj->name . ($cObj->phone ? " ({$cObj->phone})" : '');
                }
            }

            $b0_30 = 0;
            $b31_60 = 0;
            $b61_90 = 0;
            $bOver90 = 0;

            foreach ($custLines as $l) {
                $net = (float) $l->debit - (float) $l->credit;
                if ($net == 0) continue;

                $entryDate = $l->entry?->entry_date ? Carbon::parse($l->entry->entry_date) : $asOfDate;
                $daysOld = $entryDate->diffInDays($asOfDate);

                if ($daysOld <= 30) {
                    $b0_30 += $net;
                } elseif ($daysOld <= 60) {
                    $b31_60 += $net;
                } elseif ($daysOld <= 90) {
                    $b61_90 += $net;
                } else {
                    $bOver90 += $net;
                }
            }

            $custTotal = $b0_30 + $b31_60 + $b61_90 + $bOver90;
            if ($custTotal <= 0) continue;

            $total0To30  += $b0_30;
            $total31To60 += $b31_60;
            $total61To90 += $b61_90;
            $totalOver90 += $bOver90;
            $totalAr     += $custTotal;

            $customerBuckets[] = [
                'customer_id'   => $customerId,
                'customer_name' => $customerName,
                'b0_30'         => round($b0_30, 2),
                'b31_60'        => round($b31_60, 2),
                'b61_90'        => round($b61_90, 2),
                'bOver90'       => round($bOver90, 2),
                'total'         => round($custTotal, 2),
            ];
        }

        return [
            'rows'              => $customerBuckets,
            'total0To30'        => round($total0To30, 2),
            'total31To60'       => round($total31To60, 2),
            'total61To90'       => round($total61To90, 2),
            'totalOver90'       => round($totalOver90, 2),
            'totalAr'           => round($totalAr, 2),
            'filters'           => ['as_of_date' => $asOfDate->toDateString()],
            'isConsolidated'    => $scope['is_consolidated'],
            'branchesIncluded'  => $scope['branches_included'],
        ];
    }

    public function arAging(Request $request)
    {
        $data = $this->getArAgingData($request);
        return Inertia::render('Accounting/Reports/ArAging', $data);
    }

    /**
     * Export any financial report to XLSX or CSV using PhpSpreadsheet stream.
     */
    public function export(Request $request, $branchParam = null, ?string $type = null)
    {
        $type = $type ?: $branchParam;
        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr(ucwords(str_replace('-', ' ', (string) $type)), 0, 31));

        $branch = current_branch();
        $filenamePrefix = ($branch ? $branch->slug : 'accounting') . '_' . str_replace('-', '_', $type);

        switch ($type) {
            case 'trial-balance':
                $sheet->fromArray([['Account Code', 'Account Name', 'Classification', 'Debit', 'Credit']], null, 'A1');
                $tbData = $this->getTrialBalanceData($request);
                $r = 2;
                foreach ($tbData['rows'] as $row) {
                    $sheet->fromArray([[$row['code'], $row['name'], ucfirst($row['type']), $row['debit'], $row['credit']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total', '', '', $tbData['totalDebit'], $tbData['totalCredit']]], null, "A{$r}");
                break;

            case 'profit-loss':
                $sheet->fromArray([['Section', 'Account Code', 'Account Name', 'Amount']], null, 'A1');
                $plData = $this->getProfitLossData($request);
                $r = 2;
                foreach ($plData['revenues'] as $row) {
                    $sheet->fromArray([['Revenue', $row['code'], $row['name'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Revenue', '', '', $plData['totalRevenue']]], null, "A{$r}");
                $r += 2;
                foreach ($plData['cogs'] as $row) {
                    $sheet->fromArray([['Cost of Goods Sold', $row['code'], $row['name'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Gross Profit', '', '', $plData['grossProfit']]], null, "A{$r}");
                $r += 2;
                foreach ($plData['expenses'] as $row) {
                    $sheet->fromArray([['Operating Expense', $row['code'], $row['name'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Expenses', '', '', $plData['totalExpenses']]], null, "A{$r}");
                $r += 2;
                $sheet->fromArray([['Net Profit', '', '', $plData['netProfit']]], null, "A{$r}");
                break;

            case 'balance-sheet':
                $sheet->fromArray([['Classification', 'Account Code', 'Account Name', 'Amount']], null, 'A1');
                $bsData = $this->getBalanceSheetData($request);
                $r = 2;
                foreach ($bsData['assets'] as $row) {
                    $sheet->fromArray([['Asset', $row['code'], $row['name'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Assets', '', '', $bsData['totalAssets']]], null, "A{$r}");
                $r += 2;
                foreach ($bsData['liabilities'] as $row) {
                    $sheet->fromArray([['Liability', $row['code'], $row['name'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Liabilities', '', '', $bsData['totalLiabilities']]], null, "A{$r}");
                $r += 2;
                foreach ($bsData['equity'] as $row) {
                    $sheet->fromArray([['Equity', $row['code'], $row['name'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Retained Earnings', '', '', $bsData['retainedEarnings']]], null, "A{$r}");
                $r++;
                $sheet->fromArray([['Total Equity', '', '', $bsData['totalEquity']]], null, "A{$r}");
                $r += 2;
                $sheet->fromArray([['Total Liabilities & Equity', '', '', $bsData['totalLiabilitiesAndEquity']]], null, "A{$r}");
                break;

            case 'cash-flow':
                $sheet->fromArray([['Flow Type', 'Date', 'Description / Memo', 'Amount']], null, 'A1');
                $cfData = $this->getCashFlowData($request);
                $r = 2;
                $sheet->fromArray([['Beginning Cash', '', 'Cash at start of period', $cfData['beginningCash']]], null, "A{$r}");
                $r++;
                foreach ($cfData['operatingInflows'] as $row) {
                    $sheet->fromArray([['Operating Inflow', $row['date'], $row['label'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Cash Inflows', '', '', $cfData['totalInflows']]], null, "A{$r}");
                $r += 2;
                foreach ($cfData['operatingOutflows'] as $row) {
                    $sheet->fromArray([['Operating Outflow', $row['date'], $row['label'], $row['amount']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Cash Outflows', '', '', $cfData['totalOutflows']]], null, "A{$r}");
                $r += 2;
                $sheet->fromArray([['Net Cash Change', '', '', $cfData['netCashChange']]], null, "A{$r}");
                $r++;
                $sheet->fromArray([['Ending Cash', '', '', $cfData['endingCash']]], null, "A{$r}");
                break;

            case 'ar-aging':
                $sheet->fromArray([['Customer', '0-30 Days', '31-60 Days', '61-90 Days', 'Over 90 Days', 'Total Receivable']], null, 'A1');
                $arData = $this->getArAgingData($request);
                $r = 2;
                foreach ($arData['rows'] as $row) {
                    $sheet->fromArray([[$row['customer_name'], $row['b0_30'], $row['b31_60'], $row['b61_90'], $row['bOver90'], $row['total']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total', $arData['total0To30'], $arData['total31To60'], $arData['total61To90'], $arData['totalOver90'], $arData['totalAr']]], null, "A{$r}");
                break;

            case 'vat':
                $sheet->fromArray([['Date', 'Entry #', 'Description', 'Reference', 'Input VAT / Returns (Debit)', 'Output VAT (Credit)']], null, 'A1');
                $vatData = $this->getVatData($request);
                $r = 2;
                foreach ($vatData['lines'] as $row) {
                    $sheet->fromArray([[$row['date'], $row['entry_number'], $row['description'], $row['reference'], $row['debit'], $row['credit']]], null, "A{$r}");
                    $r++;
                }
                $sheet->fromArray([['Total Input VAT / Returns', '', '', '', $vatData['totalInputVatOrReturns'], '']], null, "A{$r}");
                $r++;
                $sheet->fromArray([['Total Output VAT', '', '', '', '', $vatData['totalOutputVat']]], null, "A{$r}");
                $r++;
                $sheet->fromArray([['Net VAT Payable', '', '', '', '', $vatData['netVatPayable']]], null, "A{$r}");
                break;

            default:
                abort(404, "Report type '{$type}' not supported for export.");
        }

        return $this->streamExcelOrCsv($spreadsheet, $filenamePrefix, $format);
    }

    protected function streamExcelOrCsv(Spreadsheet $spreadsheet, string $filenamePrefix, string $format = 'xlsx')
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.' . $format;

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
