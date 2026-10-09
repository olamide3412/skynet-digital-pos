<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\FinancialPeriod as GlobalFinancialPeriod;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PosSettings;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountSetupService;
use App\Modules\Accounting\Services\PostingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected User $adminUser;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        // Create organization with Silver plan (accounting enabled)
        $this->org = Organization::create([
            'name'                => 'Acme Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $plan = PricingPlan::where('name', 'Silver')->first();
        if (!$plan) {
            $plan = PricingPlan::create([
                'name'             => 'Silver',
                'first_year_price' => 100,
                'renewal_price'    => 100,
                'is_active'        => true,
            ]);
        }

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $plan->id],
            ['feature_accounting' => true]
        );

        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => now()->subDays(10),
            'renewal_date'    => now()->addDays(20),
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Accounting Test Branch',
            'slug'            => 'branch-acct-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->adminUser = User::create([
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'name'            => 'Accountant Admin',
            'username'        => 'acct_admin_' . uniqid(),
            'email'           => 'acct_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
        ]);

        // Assign Branch Admin role
        setPermissionsTeamId($this->branch->id);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'Branch Admin',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $this->adminUser->assignRole($role);
    }

    public function test_account_setup_service_activates_branch_and_seeds_17_accounts(): void
    {
        AccountSetupService::activateBranch($this->branch);

        $accounts = Account::where('branch_id', $this->branch->id)->get();
        $this->assertGreaterThanOrEqual(17, $accounts->count());

        $expectedCodes = ['1000', '1010', '1020', '1100', '1200', '1300', '2000', '2100', '2200', '3000', '3100', '4000', '4100', '4200', '5000', '5100', '5200'];
        foreach ($expectedCodes as $code) {
            $this->assertTrue(
                $accounts->contains('code', $code),
                "Expected account code {$code} was not found in branch chart of accounts."
            );
        }

        $settings = PosSettings::where('branch_id', $this->branch->id)->first();
        $this->assertNotNull($settings);
        $this->assertNotNull($settings->accounting_started_at);

        $period = FinancialPeriod::where('branch_id', $this->branch->id)->where('status', 'open')->first();
        $this->assertNotNull($period);
    }

    public function test_activation_command_is_idempotent(): void
    {
        $this->artisan('accounting:activate', ['--branch' => $this->branch->slug])
            ->assertExitCode(0);

        $firstCount = Account::where('branch_id', $this->branch->id)->count();

        // Run second time
        $this->artisan('accounting:activate', ['--branch' => $this->branch->slug])
            ->assertExitCode(0);

        $secondCount = Account::where('branch_id', $this->branch->id)->count();
        $this->assertEquals($firstCount, $secondCount, "Account setup must be idempotent and not create duplicates.");
    }

    public function test_posting_service_posts_balanced_entries(): void
    {
        AccountSetupService::activateBranch($this->branch);

        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1000')->firstOrFail();
        $sales = Account::where('branch_id', $this->branch->id)->where('code', '4000')->firstOrFail();

        $entry = PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => now(),
            'source_type'  => 'test',
            'source_id'    => 999,
            'event'        => 'test_event',
            'description'  => 'Test Cash Sale Post',
            'created_by'   => $this->adminUser->id,
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 5000.00, 'credit' => 0.00],
                ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 5000.00],
            ],
        ]);

        $this->assertNotNull($entry);
        $this->assertEquals('posted', $entry->status);
        $this->assertCount(2, $entry->lines);
    }

    public function test_posting_service_rejects_unbalanced_entries(): void
    {
        AccountSetupService::activateBranch($this->branch);

        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1000')->firstOrFail();
        $sales = Account::where('branch_id', $this->branch->id)->where('code', '4000')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => now(),
            'source_type'  => 'test',
            'source_id'    => 1000,
            'event'        => 'unbalanced_event',
            'description'  => 'Unbalanced Entry',
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 5000.00, 'credit' => 0.00],
                ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 4500.00], // Difference of 500!
            ],
        ]);
    }

    public function test_posting_service_skips_transactions_prior_to_accounting_started_at(): void
    {
        AccountSetupService::activateBranch($this->branch);

        $settings = PosSettings::where('branch_id', $this->branch->id)->first();
        $settings->update(['accounting_started_at' => Carbon::now()->startOfDay()]);

        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1000')->firstOrFail();
        $sales = Account::where('branch_id', $this->branch->id)->where('code', '4000')->firstOrFail();

        // Transaction dated yesterday
        $entry = PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => Carbon::now()->subDays(2),
            'source_type'  => 'historical',
            'source_id'    => 888,
            'event'        => 'historical_sale',
            'description'  => 'Past Sale',
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 1000.00, 'credit' => 0.00],
                ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 1000.00],
            ],
        ]);

        $this->assertNull($entry, "Transactions prior to accounting_started_at must be skipped without error.");
    }

    public function test_posting_service_memo_backdates_into_open_period_when_period_is_closed(): void
    {
        AccountSetupService::activateBranch($this->branch);

        $settings = PosSettings::where('branch_id', $this->branch->id)->first();
        $settings->update(['accounting_started_at' => Carbon::parse('2025-01-01')]);

        // Create a closed period for past month
        $closedPeriod = FinancialPeriod::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => '2025-02',
            'starts_on'       => '2025-02-01',
            'ends_on'         => '2025-02-28',
            'status'          => 'closed',
            'closed_at'       => now(),
        ]);

        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1000')->firstOrFail();
        $sales = Account::where('branch_id', $this->branch->id)->where('code', '4000')->firstOrFail();

        $entry = PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => Carbon::parse('2025-02-15'),
            'source_type'  => 'late_sync',
            'source_id'    => 777,
            'event'        => 'sync',
            'description'  => 'Late Sync Sale',
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 2000.00, 'credit' => 0.00],
                ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 2000.00],
            ],
        ]);

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Late entry for closed period', $entry->description);
    }

    public function test_posting_service_reverses_entry(): void
    {
        AccountSetupService::activateBranch($this->branch);

        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1000')->firstOrFail();
        $sales = Account::where('branch_id', $this->branch->id)->where('code', '4000')->firstOrFail();

        $original = PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => now(),
            'source_type'  => 'correction_test',
            'source_id'    => 555,
            'event'        => 'post',
            'description'  => 'Sale to be reversed',
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 3000.00, 'credit' => 0.00],
                ['account_id' => $sales->id, 'debit' => 0.00, 'credit' => 3000.00],
            ],
        ]);

        $reversal = PostingService::reverseEntry($original, 'Customer entered wrong payment', $this->adminUser);

        $this->assertNotNull($reversal);
        $this->assertEquals($original->id, $reversal->reversal_of_id);
        $this->assertEquals($reversal->id, $original->fresh()->reversedBy->id);
        $this->assertEquals('reversed', $original->fresh()->status);
        $this->assertEquals('posted', $reversal->status);

        // Lines in reversal must be flipped
        $revCashLine = $reversal->lines->where('account_id', $cash->id)->first();
        $this->assertEquals(3000.00, (float) $revCashLine->credit);
        $this->assertEquals(0.00, (float) $revCashLine->debit);
    }

    public function test_plan_capability_gate_denies_starter_plan(): void
    {
        // Change plan capability to false
        $sub = $this->org->subscription()->first();
        $sub->pricingPlan->capabilities->update(['feature_accounting' => false]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('pos.accounting.accounts.index', ['branch' => $this->branch->slug]));

        $response->assertStatus(403);
    }
}
