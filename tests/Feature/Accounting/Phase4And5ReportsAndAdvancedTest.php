<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\PricingPlan;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountSetupService;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Services\SalePostingService;
use App\Services\ExpenseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

class Phase4And5ReportsAndAdvancedTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected Branch $branch2;
    protected User $user;
    protected Organization $org;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'                => 'Reports & Advanced Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $plan = PricingPlan::where('name', 'Silver')->first() ?? PricingPlan::first();
        if ($plan) {
            PlanCapability::updateOrCreate(
                ['pricing_plan_id' => $plan->id],
                ['feature_accounting' => true]
            );

            Subscription::create([
                'organization_id' => $this->org->id,
                'pricing_plan_id' => $plan->id,
                'status'          => 'active',
                'started_at'      => now()->subDays(5),
                'renewal_date'    => now()->addDays(25),
            ]);
        }

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Reports Branch 1',
            'slug'            => 'rep-b1-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->branch2 = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Reports Branch 2',
            'slug'            => 'rep-b2-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->user = User::create([
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'name'            => 'Chief Accountant',
            'username'        => 'accountant_' . uniqid(),
            'email'           => 'accountant_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);

        AccountSetupService::activateBranch($this->branch);
        AccountSetupService::activateBranch($this->branch2);

        $settings = PosSettings::forBranch($this->branch->id);
        $settings->update([
            'accounting_started_at'     => now()->subDays(10),
            'is_accounting_vat_enabled' => true,
        ]);

        $this->item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Report Test Item',
            'barcode_number'  => 'ITEM-R-' . uniqid(),
            'buy_price'       => 100.00,
            'price'           => 200.00,
            'front_store_qty' => 100,
            'back_store_qty'  => 100,
        ]);
    }

    public function test_trial_balance_and_balance_sheet_are_balanced(): void
    {
        // 1. Post a cash sale: 1 item @ 200, cost 100
        $sale = Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-TEST-TB-1',
            'payment_method'  => 'cash',
            'final_total'     => 200.00,
            'cash'            => 200.00,
            'tax_amount'      => 0.00,
        ]);

        SaleOrder::create([
            'sale_id'             => $sale->id,
            'user_id'             => $this->user->id,
            'item_id'             => $this->item->id,
            'item_name'           => $this->item->item_name,
            'qty'                 => 1,
            'unit_cost_snapshot'  => 100.00,
            'selling_price'       => 200.00,
            'total_selling_price' => 200.00,
        ]);

        SalePostingService::postSale($sale);

        // 2. Post an operating expense: 40
        $cat = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Maintenance',
        ]);
        AccountSetupService::activateBranch($this->branch);

        $expense = ExpenseService::logExpense([
            'expense_category_id' => $cat->id,
            'amount'              => 40.00,
            'expense_date'        => now()->toDateString(),
            'description'         => 'AC Cleaning',
        ], $this->branch->id, $this->user);

        // Act: Test Trial Balance via Controller
        $controller = new \App\Modules\Accounting\Controllers\AccountingReportController();
        
        $requestTB = Request::create('/pos/' . $this->branch->slug . '/accounting/reports/trial-balance', 'GET', [
            'as_of_date' => now()->toDateString(),
        ]);
        $requestTB->setUserResolver(fn() => $this->user);
        app()->instance('current_branch', $this->branch);

        $responseTB = $controller->trialBalance($requestTB);
        $tbData = $responseTB->toResponse($requestTB)->getOriginalContent()['page']['props'];

        $this->assertTrue($tbData['isBalanced']);
        $this->assertEquals($tbData['totalDebit'], $tbData['totalCredit']);

        // Act: Test Balance Sheet
        $responseBS = $controller->balanceSheet($requestTB);
        $bsData = $responseBS->toResponse($requestTB)->getOriginalContent()['page']['props'];

        $this->assertTrue($bsData['isBalanced']);
        $this->assertEquals($bsData['totalAssets'], $bsData['totalLiabilitiesAndEquity']);
    }

    public function test_profit_and_loss_report(): void
    {
        $sale = Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-TEST-PL-1',
            'payment_method'  => 'cash',
            'final_total'     => 1000.00,
            'cash'            => 1000.00,
            'tax_amount'      => 0.00,
        ]);

        SaleOrder::create([
            'sale_id'             => $sale->id,
            'user_id'             => $this->user->id,
            'item_id'             => $this->item->id,
            'item_name'           => $this->item->item_name,
            'qty'                 => 5,
            'unit_cost_snapshot'  => 100.00, // COGS = 500
            'selling_price'       => 200.00,
            'total_selling_price' => 1000.00,
        ]);

        SalePostingService::postSale($sale);

        $cat = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Stationery',
        ]);
        AccountSetupService::activateBranch($this->branch);

        ExpenseService::logExpense([
            'expense_category_id' => $cat->id,
            'amount'              => 150.00,
            'expense_date'        => now()->toDateString(),
            'description'         => 'Office Pens',
        ], $this->branch->id, $this->user);

        // Revenue: 1000, COGS: 500, Gross Profit: 500, Expenses: 150, Net Profit: 350
        $controller = new \App\Modules\Accounting\Controllers\AccountingReportController();
        $req = Request::create('/pos/' . $this->branch->slug . '/accounting/reports/profit-loss', 'GET', [
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date'   => now()->toDateString(),
        ]);
        $req->setUserResolver(fn() => $this->user);
        app()->instance('current_branch', $this->branch);

        $response = $controller->profitLoss($req);
        $pl = $response->toResponse($req)->getOriginalContent()['page']['props'];

        $this->assertEquals(1000.00, $pl['totalRevenue']);
        $this->assertEquals(500.00, $pl['totalCogs']);
        $this->assertEquals(500.00, $pl['grossProfit']);
        $this->assertEquals(150.00, $pl['totalExpenses']);
        $this->assertEquals(350.00, $pl['netProfit']);
    }

    public function test_cash_flow_statement_direct_method(): void
    {
        // Sale: Cash Inflow +600
        $sale = Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-TEST-CF-1',
            'payment_method'  => 'cash',
            'final_total'     => 600.00,
            'cash'            => 600.00,
            'tax_amount'      => 0.00,
        ]);

        SaleOrder::create([
            'sale_id'             => $sale->id,
            'user_id'             => $this->user->id,
            'item_id'             => $this->item->id,
            'item_name'           => $this->item->item_name,
            'qty'                 => 3,
            'unit_cost_snapshot'  => 100.00,
            'selling_price'       => 200.00,
            'total_selling_price' => 600.00,
        ]);
        SalePostingService::postSale($sale);

        // Expense: Cash Outflow -100
        $cat = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Supplies',
        ]);
        AccountSetupService::activateBranch($this->branch);

        ExpenseService::logExpense([
            'expense_category_id' => $cat->id,
            'amount'              => 100.00,
            'expense_date'        => now()->toDateString(),
            'description'         => 'Tea',
        ], $this->branch->id, $this->user);

        $controller = new \App\Modules\Accounting\Controllers\AccountingReportController();
        $req = Request::create('/pos/' . $this->branch->slug . '/accounting/reports/cash-flow', 'GET', [
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date'   => now()->toDateString(),
        ]);
        $req->setUserResolver(fn() => $this->user);
        app()->instance('current_branch', $this->branch);

        $response = $controller->cashFlow($req);
        $cf = $response->toResponse($req)->getOriginalContent()['page']['props'];

        $this->assertEquals(600.00, $cf['totalInflows']);
        $this->assertEquals(100.00, $cf['totalOutflows']);
        $this->assertEquals(500.00, $cf['netCashChange']);
    }

    public function test_ar_aging_buckets(): void
    {
        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Debtor John Doe',
            'phone'     => '08012345678',
        ]);

        // Post Credit Sale: 800 AR
        $sale = Sale::create([
            'branch_id'       => $this->branch->id,
            'customer_id'     => $customer->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-TEST-AR-1',
            'payment_method'  => 'debt',
            'is_debt'         => true,
            'final_total'     => 800.00,
            'amount_paid'     => 0.00,
            'tax_amount'      => 0.00,
        ]);

        SaleOrder::create([
            'sale_id'             => $sale->id,
            'user_id'             => $this->user->id,
            'item_id'             => $this->item->id,
            'item_name'           => $this->item->item_name,
            'qty'                 => 4,
            'unit_cost_snapshot'  => 100.00,
            'selling_price'       => 200.00,
            'total_selling_price' => 800.00,
        ]);
        SalePostingService::postSale($sale);

        $controller = new \App\Modules\Accounting\Controllers\AccountingReportController();
        $req = Request::create('/pos/' . $this->branch->slug . '/accounting/reports/ar-aging', 'GET', [
            'as_of_date' => now()->toDateString(),
        ]);
        $req->setUserResolver(fn() => $this->user);
        app()->instance('current_branch', $this->branch);

        $response = $controller->arAging($req);
        $ar = $response->toResponse($req)->getOriginalContent()['page']['props'];

        $this->assertEquals(800.00, $ar['totalAr']);
        $this->assertEquals(800.00, $ar['total0To30']); // Created today, falls in 0-30 days bucket
    }

    public function test_opening_balances_wizard_records_balanced_entry(): void
    {
        $accounts = Account::where('branch_id', $this->branch->id)->get()->keyBy('code');

        $cashAcc = $accounts->get('1000');
        $capitalAcc = $accounts->get('3000');

        $controller = new \App\Modules\Accounting\Controllers\OpeningBalanceController();
        $req = Request::create('/pos/' . $this->branch->slug . '/accounting/opening-balances', 'POST', [
            'lines' => [
                ['account_id' => $cashAcc->id, 'debit' => 50000.00, 'credit' => 0.00, 'memo' => 'Starting cash'],
                ['account_id' => $capitalAcc->id, 'debit' => 0.00, 'credit' => 50000.00, 'memo' => 'Owner capital contribution'],
            ],
        ]);
        $req->setUserResolver(fn() => $this->user);
        app()->instance('current_branch', $this->branch);

        $response = $controller->store($req);

        $entry = JournalEntry::where('branch_id', $this->branch->id)
            ->where('event', 'opening_balances')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(50000.00, (float) $entry->total_debit);
        $this->assertEquals(50000.00, (float) $entry->total_credit);
        $this->assertTrue($entry->isBalanced());
    }

    public function test_unposted_transactions_retry_posts_successfully(): void
    {
        // Create an unposted sale by temporarily disabling accounting_started_at during save
        $settings = PosSettings::forBranch($this->branch->id);
        $savedDate = $settings->accounting_started_at;

        // Sale created with created_at on today
        $sale = Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-UNPOSTED-1',
            'payment_method'  => 'cash',
            'final_total'     => 400.00,
            'cash'            => 400.00,
            'tax_amount'      => 0.00,
        ]);

        SaleOrder::create([
            'sale_id'             => $sale->id,
            'user_id'             => $this->user->id,
            'item_id'             => $this->item->id,
            'item_name'           => $this->item->item_name,
            'qty'                 => 2,
            'unit_cost_snapshot'  => 100.00,
            'selling_price'       => 200.00,
            'total_selling_price' => 400.00,
        ]);

        // Retry posting via UnpostedTransactionsController
        $controller = new \App\Modules\Accounting\Controllers\UnpostedTransactionsController();
        $req = Request::create('/pos/' . $this->branch->slug . '/accounting/unposted/retry', 'POST', [
            'type' => 'sale',
            'id'   => $sale->id,
        ]);
        $req->setUserResolver(fn() => $this->user);
        app()->instance('current_branch', $this->branch);

        $response = $controller->retry($req);

        // Verify entry was posted
        $entry = JournalEntry::where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('event', 'sale_completed')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals(600.00, (float) $entry->total_credit);
    }
}
