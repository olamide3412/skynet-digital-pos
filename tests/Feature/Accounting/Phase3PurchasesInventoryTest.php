<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PosSettings;
use App\Models\PricingPlan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Vendor;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountSetupService;
use App\Modules\Accounting\Services\InventoryPostingService;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Phase3PurchasesInventoryTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected Branch $branch2;
    protected User $user;
    protected Organization $org;
    protected Item $item;
    protected Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'                => 'Phase 3 Test Org ' . uniqid(),
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
            'name'            => 'Inventory Branch 1',
            'slug'            => 'inv-b1-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->branch2 = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Inventory Branch 2',
            'slug'            => 'inv-b2-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->user = User::create([
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'name'            => 'Test Manager',
            'username'        => 'manager_' . uniqid(),
            'email'           => 'manager_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);

        AccountSetupService::activateBranch($this->branch);
        AccountSetupService::activateBranch($this->branch2);

        $this->vendor = Vendor::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Acme Supplies',
            'email'     => 'vendor_' . uniqid() . '@example.com',
        ]);

        $this->item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Test Inventory Item',
            'barcode_number'  => 'ITEM-' . uniqid(),
            'buy_price'       => 150.00,
            'price'           => 250.00,
            'front_store_qty' => 50,
            'back_store_qty'  => 50,
        ]);
    }

    public function test_purchase_order_receipt_posts_dr_inventory_cr_cash(): void
    {
        $po = PurchaseOrder::create([
            'branch_id'    => $this->branch->id,
            'vendor_id'    => $this->vendor->id,
            'user_id'      => $this->user->id,
            'po_number'    => 'PO-' . time(),
            'order_date'   => now()->toDateString(),
            'total_amount' => 1500.00,
            'status'       => 'Pending',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'item_id'           => $this->item->id,
            'qty'               => 10,
            'cost'              => 150.00,
            'received_qty'      => 0,
        ]);

        $entry = InventoryPostingService::postPurchaseOrderReceipt($po, 1500.00, $this->user->id);

        $this->assertNotNull($entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(1500.00, (float) $entry->total_debit);
        $this->assertEquals(1500.00, (float) $entry->total_credit);

        $debitLine = $entry->lines()->where('debit', '>', 0)->first();
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();

        // Dr Inventory (1200)
        $this->assertEquals('1200', $debitLine->account->code);
        $this->assertEquals(1500.00, (float) $debitLine->debit);

        // Cr Cash (1000)
        $this->assertEquals('1000', $creditLine->account->code);
        $this->assertEquals(1500.00, (float) $creditLine->credit);
    }

    public function test_stock_adjustment_decrease_posts_dr_shrinkage_cr_inventory(): void
    {
        // 5 units shrunk @ cost 150.00 = 750.00
        $entry = InventoryPostingService::postStockAdjustment(
            $this->item,
            'subtract',
            5,
            150.00,
            'Damaged in warehouse',
            $this->user->id
        );

        $this->assertNotNull($entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(750.00, (float) $entry->total_debit);
        $this->assertEquals(750.00, (float) $entry->total_credit);

        $debitLine = $entry->lines()->where('debit', '>', 0)->first();
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();

        // Dr Inventory Shrinkage (5100)
        $this->assertEquals('5100', $debitLine->account->code);
        $this->assertEquals(750.00, (float) $debitLine->debit);

        // Cr Inventory (1200)
        $this->assertEquals('1200', $creditLine->account->code);
        $this->assertEquals(750.00, (float) $creditLine->credit);
    }

    public function test_stock_adjustment_increase_posts_dr_inventory_cr_shrinkage(): void
    {
        // 4 units found surplus @ cost 150.00 = 600.00
        $entry = InventoryPostingService::postStockAdjustment(
            $this->item,
            'add',
            4,
            150.00,
            'Found stock during audit',
            $this->user->id
        );

        $this->assertNotNull($entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(600.00, (float) $entry->total_debit);
        $this->assertEquals(600.00, (float) $entry->total_credit);

        $debitLine = $entry->lines()->where('debit', '>', 0)->first();
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();

        // Dr Inventory (1200)
        $this->assertEquals('1200', $debitLine->account->code);
        $this->assertEquals(600.00, (float) $debitLine->debit);

        // Cr Inventory Shrinkage (5100)
        $this->assertEquals('5100', $creditLine->account->code);
        $this->assertEquals(600.00, (float) $creditLine->credit);
    }

    public function test_expense_logging_posts_dr_expense_cr_cash(): void
    {
        $category = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Utilities & Power',
        ]);

        // Re-run activate to seed mapped account for category
        AccountSetupService::activateBranch($this->branch);

        $expense = ExpenseService::logExpense([
            'expense_category_id' => $category->id,
            'amount'              => 450.00,
            'expense_date'        => now()->toDateString(),
            'description'         => 'Monthly Generator Fuel',
        ], $this->branch->id, $this->user);

        $entry = JournalEntry::where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->where('event', 'expense_recorded')
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertEquals(450.00, (float) $entry->total_debit);
        $this->assertEquals(450.00, (float) $entry->total_credit);

        $debitLine = $entry->lines()->where('debit', '>', 0)->first();
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();

        $this->assertEquals('expense', $debitLine->account->type);
        $this->assertEquals(450.00, (float) $debitLine->debit);

        // Cr Cash (1000)
        $this->assertEquals('1000', $creditLine->account->code);
        $this->assertEquals(450.00, (float) $creditLine->credit);
    }

    public function test_expense_deletion_posts_reversing_entry(): void
    {
        $category = ExpenseCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Office Supplies',
        ]);

        $expense = ExpenseService::logExpense([
            'expense_category_id' => $category->id,
            'amount'              => 200.00,
            'expense_date'        => now()->toDateString(),
            'description'         => 'Printer Paper',
        ], $this->branch->id, $this->user);

        $origEntry = JournalEntry::where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->first();

        $this->assertNotNull($origEntry);

        // Now delete expense
        ExpenseService::deleteExpense($expense, $this->user, 'Mistaken entry');

        $reversalEntry = JournalEntry::where('reversal_of_id', $origEntry->id)->first();
        $this->assertNotNull($reversalEntry);
        $this->assertEquals(200.00, (float) $reversalEntry->total_debit);
        $this->assertEquals(200.00, (float) $reversalEntry->total_credit);

        // Reversal flips the debits and credits
        $reversalDebit = $reversalEntry->lines()->where('debit', '>', 0)->first();
        $reversalCredit = $reversalEntry->lines()->where('credit', '>', 0)->first();

        // In reversal: Cash (1000) is debited, Expense is credited
        $this->assertEquals('1000', $reversalDebit->account->code);
        $this->assertEquals('expense', $reversalCredit->account->type);
    }

    public function test_inter_branch_stock_transfer_posts_both_branches(): void
    {
        $res = InventoryPostingService::postStockTransfer(
            $this->branch->id,
            $this->branch2->id,
            3000.00,
            'TRF-1001',
            $this->user->id
        );

        $outEntry = $res['out'];
        $inEntry = $res['in'];

        $this->assertNotNull($outEntry);
        $this->assertNotNull($inEntry);

        // Branch 1 (Sending): Dr Inter-Branch Receivable (1300) / Cr Inventory (1200)
        $this->assertEquals($this->branch->id, $outEntry->branch_id);
        $outDebit = $outEntry->lines()->where('debit', '>', 0)->first();
        $outCredit = $outEntry->lines()->where('credit', '>', 0)->first();
        $this->assertEquals('1300', $outDebit->account->code);
        $this->assertEquals('1200', $outCredit->account->code);

        // Branch 2 (Receiving): Dr Inventory (1200) / Cr Inter-Branch Payable (2200)
        $this->assertEquals($this->branch2->id, $inEntry->branch_id);
        $inDebit = $inEntry->lines()->where('debit', '>', 0)->first();
        $inCredit = $inEntry->lines()->where('credit', '>', 0)->first();
        $this->assertEquals('1200', $inDebit->account->code);
        $this->assertEquals('2200', $inCredit->account->code);
    }

    public function test_cash_drop_posts_dr_bank_cr_cash(): void
    {
        $entry = InventoryPostingService::postCashDrop(
            $this->branch,
            5000.00,
            'DEPOSIT-555',
            $this->user->id
        );

        $this->assertNotNull($entry);
        $this->assertEquals(5000.00, (float) $entry->total_debit);
        $this->assertEquals(5000.00, (float) $entry->total_credit);

        $debitLine = $entry->lines()->where('debit', '>', 0)->first();
        $creditLine = $entry->lines()->where('credit', '>', 0)->first();

        // Dr Bank (1010)
        $this->assertEquals('1010', $debitLine->account->code);
        // Cr Cash (1000)
        $this->assertEquals('1000', $creditLine->account->code);
    }
}
