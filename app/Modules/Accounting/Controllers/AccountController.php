<?php

namespace App\Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $accounts = Account::where('branch_id', $branch->id)
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('sub_type', 'like', "%{$search}%");
                });
            })
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts,
            'filters'  => $request->only('search', 'type'),
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $validated = $request->validate([
            'code'           => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($branch) {
                    if (Account::where('branch_id', $branch->id)->where('code', $value)->exists()) {
                        $fail("Account code {$value} is already in use in this branch.");
                    }
                },
            ],
            'name'           => 'required|string|max:100',
            'type'           => 'required|in:asset,liability,equity,revenue,expense',
            'sub_type'       => 'nullable|string|max:50',
            'category'       => 'nullable|string|max:50',
            'normal_balance' => 'required|in:debit,credit',
        ]);

        Account::create([
            'organization_id' => $branch->organization_id ?? 0,
            'branch_id'       => $branch->id,
            'code'            => $validated['code'],
            'name'            => $validated['name'],
            'type'            => $validated['type'],
            'sub_type'        => $validated['sub_type'] ?? $validated['category'] ?? null,
            'normal_balance'  => $validated['normal_balance'],
            'is_system'       => false,
            'is_active'       => true,
        ]);

        return back()->with('success', "Account {$validated['code']} - {$validated['name']} created successfully.");
    }

    public function update(Request $request, $branchParam, Account $account)
    {
        $branch = current_branch();
        if ($account->branch_id !== $branch->id) {
            abort(403, 'Unauthorized access to account.');
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'is_active'   => 'required|boolean',
        ]);

        if ($account->is_system && !$validated['is_active']) {
            return back()->withErrors(['is_active' => 'System accounts cannot be deactivated.']);
        }

        $account->update([
            'name'        => $validated['name'],
            'is_active'   => $validated['is_active'],
        ]);

        return back()->with('success', "Account {$account->code} updated successfully.");
    }

    public function export(Request $request)
    {
        $branch = current_branch();

        $accounts = Account::where('branch_id', $branch->id)
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('sub_type', 'like', "%{$search}%");
                });
            })
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('code')
            ->get();

        $format = strtolower($request->get('format', 'xlsx')) === 'csv' ? 'csv' : 'xlsx';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Chart of Accounts');

        $headers = ['Account Code', 'Account Name', 'Classification (Type)', 'Sub-Type', 'Normal Balance', 'Status', 'System Account'];
        $sheet->fromArray([$headers], null, 'A1');

        $r = 2;
        foreach ($accounts as $acc) {
            $sheet->fromArray([[
                $acc->code,
                $acc->name,
                ucfirst($acc->type),
                $acc->sub_type ?? '',
                ucfirst($acc->normal_balance),
                $acc->is_active ? 'Active' : 'Inactive',
                $acc->is_system ? 'Yes' : 'No',
            ]], null, "A{$r}");
            $r++;
        }

        $filename = ($branch ? $branch->slug : 'branch') . "_chart_of_accounts_" . date('Y-m-d_His') . '.' . $format;

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
