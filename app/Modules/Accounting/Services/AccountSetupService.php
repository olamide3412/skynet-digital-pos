<?php

namespace App\Modules\Accounting\Services;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\PosSettings;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\FinancialPeriod;
use Illuminate\Support\Facades\DB;

class AccountSetupService
{
    /**
     * Standard System Chart of Accounts (§5)
     */
    public const DEFAULT_ACCOUNTS = [
        ['code' => '1000', 'name' => 'Cash on Hand',                          'type' => 'asset',     'sub_type' => 'cash_and_bank',       'normal_balance' => 'debit'],
        ['code' => '1010', 'name' => 'Bank',                                  'type' => 'asset',     'sub_type' => 'cash_and_bank',       'normal_balance' => 'debit'],
        ['code' => '1020', 'name' => 'POS / Card Clearing',                   'type' => 'asset',     'sub_type' => 'cash_and_bank',       'normal_balance' => 'debit'],
        ['code' => '1100', 'name' => 'Accounts Receivable (Customer Debt)',    'type' => 'asset',     'sub_type' => 'receivables',         'normal_balance' => 'debit'],
        ['code' => '1200', 'name' => 'Inventory',                              'type' => 'asset',     'sub_type' => 'inventory',           'normal_balance' => 'debit'],
        ['code' => '1300', 'name' => 'Inter-Branch Receivable',                'type' => 'asset',     'sub_type' => 'inter_branch',        'normal_balance' => 'debit'],
        ['code' => '2000', 'name' => 'Accounts Payable (Vendors)',             'type' => 'liability', 'sub_type' => 'payables',            'normal_balance' => 'credit'],
        ['code' => '2100', 'name' => 'VAT / Tax Payable',                      'type' => 'liability', 'sub_type' => 'tax_payable',         'normal_balance' => 'credit'],
        ['code' => '2200', 'name' => 'Inter-Branch Payable',                   'type' => 'liability', 'sub_type' => 'inter_branch',        'normal_balance' => 'credit'],
        ['code' => '3000', 'name' => "Owner's Capital",                       'type' => 'equity',    'sub_type' => 'capital',             'normal_balance' => 'credit'],
        ['code' => '3100', 'name' => 'Retained Earnings',                     'type' => 'equity',    'sub_type' => 'retained_earnings',   'normal_balance' => 'credit'],
        ['code' => '4000', 'name' => 'Sales Revenue',                         'type' => 'revenue',   'sub_type' => 'operating_revenue',   'normal_balance' => 'credit'],
        ['code' => '4100', 'name' => 'Sales Returns',                         'type' => 'revenue',   'sub_type' => 'contra_revenue',      'normal_balance' => 'debit'],
        ['code' => '4200', 'name' => 'Sales Discounts',                       'type' => 'revenue',   'sub_type' => 'contra_revenue',      'normal_balance' => 'debit'],
        ['code' => '5000', 'name' => 'Cost of Goods Sold',                    'type' => 'expense',   'sub_type' => 'cost_of_sales',       'normal_balance' => 'debit'],
        ['code' => '5100', 'name' => 'Inventory Shrinkage / Expiry Write-off','type' => 'expense',   'sub_type' => 'direct_expense',      'normal_balance' => 'debit'],
        ['code' => '5200', 'name' => 'Cash Over / Short',                     'type' => 'expense',   'sub_type' => 'direct_expense',      'normal_balance' => 'debit'],
    ];

    /**
     * Static shortcut to setupBranch
     */
    public static function activateBranch(Branch $branch): array
    {
        return (new self())->setupBranch($branch);
    }

    /**
     * Activate and seed accounting for a branch.
     * Idempotent: safe to run multiple times.
     */
    public function setupBranch(Branch $branch): array
    {
        return DB::transaction(function () use ($branch) {
            $createdCount = 0;
            $orgId = $branch->organization_id;

            // 1. Seed system accounts
            $accountsMap = [];
            foreach (self::DEFAULT_ACCOUNTS as $accDef) {
                $account = Account::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'branch_id'       => $branch->id,
                        'code'            => $accDef['code'],
                    ],
                    [
                        'name'            => $accDef['name'],
                        'type'            => $accDef['type'],
                        'sub_type'        => $accDef['sub_type'],
                        'normal_balance'  => $accDef['normal_balance'],
                        'is_system'       => true,
                        'is_active'       => true,
                    ]
                );

                if ($account->wasRecentlyCreated) {
                    $createdCount++;
                }

                $accountsMap[$accDef['code']] = $account;
            }

            // 2. Seed an expense account (6xxx) for each existing ExpenseCategory
            $expenseCategories = ExpenseCategory::where(function ($q) use ($branch) {
                $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
            })->get();

            $nextCode = 6010;
            foreach ($expenseCategories as $cat) {
                if ($cat->account_id && Account::where('id', $cat->account_id)->exists()) {
                    continue;
                }

                // Check if account with matching name already exists for this branch
                $catAccount = Account::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'branch_id'       => $branch->id,
                        'name'            => 'Expense: ' . $cat->name,
                    ],
                    [
                        'code'            => (string) $nextCode,
                        'type'            => 'expense',
                        'sub_type'        => 'operating_expense',
                        'normal_balance'  => 'debit',
                        'is_system'       => false,
                        'is_active'       => true,
                    ]
                );

                if ($catAccount->wasRecentlyCreated) {
                    $createdCount++;
                    $nextCode += 10;
                }

                $cat->update(['account_id' => $catAccount->id]);
            }

            // 3. Seed default bank/cash accounts
            if (isset($accountsMap['1000'])) {
                BankAccount::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'branch_id'       => $branch->id,
                        'type'            => 'cash',
                    ],
                    [
                        'account_id'      => $accountsMap['1000']->id,
                        'name'            => 'Main Till / Cash Drawer',
                        'is_active'       => true,
                    ]
                );
            }

            if (isset($accountsMap['1010'])) {
                BankAccount::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'branch_id'       => $branch->id,
                        'type'            => 'bank',
                    ],
                    [
                        'account_id'      => $accountsMap['1010']->id,
                        'name'            => 'Primary Operating Bank Account',
                        'is_active'       => true,
                    ]
                );
            }

            if (isset($accountsMap['1020'])) {
                BankAccount::firstOrCreate(
                    [
                        'organization_id' => $orgId,
                        'branch_id'       => $branch->id,
                        'type'            => 'pos_clearing',
                    ],
                    [
                        'account_id'      => $accountsMap['1020']->id,
                        'name'            => 'POS Terminal / Card Clearing',
                        'is_active'       => true,
                    ]
                );
            }

            // 4. Seed current financial period if none exists
            $now = now();
            FinancialPeriod::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'branch_id'       => $branch->id,
                    'starts_on'       => $now->copy()->startOfMonth()->toDateString(),
                    'ends_on'         => $now->copy()->endOfMonth()->toDateString(),
                ],
                [
                    'name'            => $now->format('F Y'),
                    'status'          => 'open',
                ]
            );

            // 5. Update PosSettings: set accounting_started_at if not set
            $settings = PosSettings::forBranch($branch->id);
            if (!$settings->accounting_started_at) {
                $settings->accounting_started_at = $now;
                $settings->save();
            }

            return [
                'branch_id'             => $branch->id,
                'branch_name'           => $branch->name,
                'accounts_created'      => $createdCount,
                'accounting_started_at' => $settings->accounting_started_at?->toDateTimeString(),
            ];
        });
    }

    /**
     * Find an account by code for a branch.
     */
    public static function getAccountByCode(int $branchId, string $code): ?Account
    {
        return Account::where('branch_id', $branchId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }
}
