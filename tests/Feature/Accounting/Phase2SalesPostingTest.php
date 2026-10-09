<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\PricingPlan;
use App\Models\SaleOrder;
use App\Models\Subscription;
use App\Models\TillSession;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountSetupService;
use App\Services\ReturnService;
use App\Services\SaleService;
use App\Services\TillSessionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class Phase2SalesPostingTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected User $cashier;
    protected Organization $org;
    protected Item $itemA;
    protected Item $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'                => 'Phase 2 Test Org ' . uniqid(),
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
            'name'            => 'Sales Accounting Branch',
            'slug'            => 'sales-acct-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->cashier = User::create([
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'name'            => 'Test Cashier',
            'username'        => 'cashier_' . uniqid(),
            'email'           => 'cashier_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'Cashier',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name'       => 'canGiveDebt',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($perm);
        $this->cashier->assignRole($role);

        // Activate accounting on branch
        AccountSetupService::activateBranch($this->branch);

        // Configure branch settings
        $settings = PosSettings::forBranch($this->branch->id);
        $settings->update([
            'is_qty_deduction'            => true,
            'require_till_opening'        => false,
            'is_accounting_vat_enabled'   => false,
            'accounting_started_at'       => now()->subDays(1),
        ]);

        // Create test items
        $this->itemA = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Widget Alpha',
            'barcode_number'  => 'BARCODE-' . uniqid(),
            'buy_price'       => 1500.00,
            'price'           => 2500.00,
            'front_store_qty' => 100,
            'back_store_qty'  => 50,
        ]);

        $this->itemB = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Widget Beta',
            'barcode_number'  => 'BARCODE-' . uniqid(),
            'buy_price'       => 2000.00,
            'price'           => 3500.00,
            'front_store_qty' => 100,
            'back_store_qty'  => 50,
        ]);
    }

    public function test_cash_sale_posts_balanced_double_entry_and_records_cost_snapshot(): void
    {
        $cartData = [
            'payment_method' => 'Cash',
            'amount_paid'    => 5000.00,
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'qty'        => 2,
                    'price'      => 2500.00,
                    'unit_used'  => 'unit',
                ],
            ],
        ];

        $sale = SaleService::process($cartData, $this->cashier);

        // 1. Verify cost snapshot stored on SaleOrder
        $order = SaleOrder::where('sale_id', $sale->id)->firstOrFail();
        $this->assertEquals(1500.00, (float) $order->unit_cost_snapshot);

        // 2. Verify JournalEntry created
        $entry = JournalEntry::with('lines.account')
            ->where('source_type', get_class($sale))
            ->where('source_id', $sale->id)
            ->where('event', 'sale_completed')
            ->first();

        $this->assertNotNull($entry, 'Journal entry for completed sale was not created.');
        $this->assertEquals('posted', $entry->status);
        $this->assertTrue($entry->isBalanced());

        // 3. Verify specific lines
        $cashLine = $entry->lines->firstWhere('account.code', '1000');
        $this->assertNotNull($cashLine);
        $this->assertEquals(5000.00, (float) $cashLine->debit);

        $revenueLine = $entry->lines->firstWhere('account.code', '4000');
        $this->assertNotNull($revenueLine);
        $this->assertEquals(5000.00, (float) $revenueLine->credit);

        // COGS & Inventory (2 items * 1500 = 3000)
        $cogsLine = $entry->lines->firstWhere('account.code', '5000');
        $this->assertNotNull($cogsLine);
        $this->assertEquals(3000.00, (float) $cogsLine->debit);

        $inventoryLine = $entry->lines->firstWhere('account.code', '1200');
        $this->assertNotNull($inventoryLine);
        $this->assertEquals(3000.00, (float) $inventoryLine->credit);
    }

    public function test_sale_with_discount_posts_discount_contra_revenue(): void
    {
        $cartData = [
            'payment_method'  => 'Cash',
            'discount_amount' => 500.00,
            'amount_paid'     => 4500.00,
            'items'           => [
                [
                    'item_id'    => $this->itemA->id,
                    'qty'        => 2,
                    'price'      => 2500.00,
                    'unit_used'  => 'unit',
                ],
            ],
        ];

        $sale = SaleService::process($cartData, $this->cashier);

        $entry = JournalEntry::with('lines.account')
            ->where('source_type', get_class($sale))
            ->where('source_id', $sale->id)
            ->firstOrFail();

        $this->assertTrue($entry->isBalanced());

        $discountLine = $entry->lines->firstWhere('account.code', '4200');
        $this->assertNotNull($discountLine);
        $this->assertEquals(500.00, (float) $discountLine->debit);

        $cashLine = $entry->lines->firstWhere('account.code', '1000');
        $this->assertEquals(4500.00, (float) $cashLine->debit);

        $revenueLine = $entry->lines->firstWhere('account.code', '4000');
        $this->assertEquals(5000.00, (float) $revenueLine->credit);
    }

    public function test_sale_with_vat_enabled_splits_tax_payable(): void
    {
        // Enable VAT in accounting & POS settings
        $settings = PosSettings::forBranch($this->branch->id);
        $settings->update([
            'is_tax_enabled'            => true,
            'tax_percentage'            => 7.5,
            'is_accounting_vat_enabled' => true,
        ]);

        $cartData = [
            'payment_method' => 'Cash',
            'amount_paid'    => 5375.00,
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'qty'        => 2,
                    'price'      => 2500.00,
                    'unit_used'  => 'unit',
                ],
            ],
        ];

        $sale = SaleService::process($cartData, $this->cashier);

        $entry = JournalEntry::with('lines.account')
            ->where('source_type', get_class($sale))
            ->where('source_id', $sale->id)
            ->firstOrFail();

        $this->assertTrue($entry->isBalanced());

        $vatLine = $entry->lines->firstWhere('account.code', '2100');
        $this->assertNotNull($vatLine);
        $this->assertEquals(375.00, (float) $vatLine->credit);

        $revenueLine = $entry->lines->firstWhere('account.code', '4000');
        $this->assertEquals(5000.00, (float) $revenueLine->credit);
    }

    public function test_credit_sale_posts_accounts_receivable(): void
    {
        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Credit Customer Test',
            'phone'     => '08012345678',
            'debt_bal'  => 0,
        ]);

        $cartData = [
            'payment_method' => 'Cash',
            'is_debt'        => true,
            'customer_id'    => $customer->id,
            'amount_paid'    => 1000.00, // 4000 remaining on debt
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'qty'        => 2,
                    'price'      => 2500.00,
                    'unit_used'  => 'unit',
                ],
            ],
        ];

        $sale = SaleService::process($cartData, $this->cashier);

        $entry = JournalEntry::with('lines.account')
            ->where('source_type', get_class($sale))
            ->where('source_id', $sale->id)
            ->firstOrFail();

        $this->assertTrue($entry->isBalanced());

        $arLine = $entry->lines->firstWhere('account.code', '1100');
        $this->assertNotNull($arLine);
        $this->assertEquals(4000.00, (float) $arLine->debit);
        $this->assertEquals($customer->id, $arLine->customer_id);

        $cashLine = $entry->lines->firstWhere('account.code', '1000');
        $this->assertEquals(1000.00, (float) $cashLine->debit);
    }

    public function test_customer_debt_payment_clears_accounts_receivable(): void
    {
        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Paying Customer',
            'phone'     => '08099887766',
            'debt_bal'  => 5000.00,
        ]);

        // Customer pays 3000
        $response = $this->actingAs($this->cashier)->post(
            route('pos.customers.debt', ['branch' => $this->branch->slug, 'customer' => $customer->id]),
            [
                'amount'    => 3000.00,
                'type'      => 'credit',
                'narration' => 'Bank deposit towards debt',
            ]
        );

        $response->assertSessionHasNoErrors();

        $entry = JournalEntry::with('lines.account')
            ->where('event', 'debt_payment')
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($entry->isBalanced());

        $cashLine = $entry->lines->firstWhere('account.code', '1000');
        $this->assertEquals(3000.00, (float) $cashLine->debit);

        $arLine = $entry->lines->firstWhere('account.code', '1100');
        $this->assertEquals(3000.00, (float) $arLine->credit);
        $this->assertEquals($customer->id, $arLine->customer_id);
    }

    public function test_sale_return_posts_contra_revenue_and_restocks_cogs(): void
    {
        // First make a sale
        $cartData = [
            'payment_method' => 'Cash',
            'amount_paid'    => 2500.00,
            'items'          => [
                [
                    'item_id'    => $this->itemA->id,
                    'qty'        => 1,
                    'price'      => 2500.00,
                    'unit_used'  => 'unit',
                ],
            ],
        ];
        $sale = SaleService::process($cartData, $this->cashier);

        // Now return the 1 item
        ReturnService::process(
            $sale->id,
            [
                [
                    'item_id'   => $this->itemA->id,
                    'qty'       => 1,
                    'unit_used' => 'unit',
                    'reason'    => 'Defective item',
                ],
            ],
            $this->cashier
        );

        $entry = JournalEntry::with('lines.account')
            ->where('event', 'sale_return')
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($entry->isBalanced());

        $returnLine = $entry->lines->firstWhere('account.code', '4100');
        $this->assertNotNull($returnLine);
        $this->assertEquals(2500.00, (float) $returnLine->debit);

        $cashLine = $entry->lines->firstWhere('account.code', '1000');
        $this->assertEquals(2500.00, (float) $cashLine->credit);

        $invLine = $entry->lines->firstWhere('account.code', '1200');
        $this->assertEquals(1500.00, (float) $invLine->debit);

        $cogsLine = $entry->lines->firstWhere('account.code', '5000');
        $this->assertEquals(1500.00, (float) $cogsLine->credit);
    }

    public function test_till_session_closure_posts_shortage_or_surplus(): void
    {
        // Open till with 10,000 float
        $session = TillSessionService::openSession($this->branch, $this->cashier, 10000.00);

        // Close till with 9,500 counted (shortage of 500)
        TillSessionService::closeSession($session, 9500.00, $this->cashier);

        $entry = JournalEntry::with('lines.account')
            ->where('source_type', get_class($session))
            ->where('source_id', $session->id)
            ->where('event', 'till_shortage')
            ->firstOrFail();

        $this->assertTrue($entry->isBalanced());

        $overShortLine = $entry->lines->firstWhere('account.code', '5200');
        $this->assertEquals(500.00, (float) $overShortLine->debit);

        $cashLine = $entry->lines->firstWhere('account.code', '1000');
        $this->assertEquals(500.00, (float) $cashLine->credit);
    }
}
