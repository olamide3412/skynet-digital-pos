<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountSetupService;
use App\Modules\Accounting\Services\PostingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AccountingExportTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected User $adminUser;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'                => 'Export Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $plan = PricingPlan::firstOrCreate(
            ['name' => 'Enterprise'],
            ['first_year_price' => 500, 'renewal_price' => 500, 'is_active' => true]
        );

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
            'name'            => 'Export Branch ' . uniqid(),
            'slug'            => 'export-branch-' . uniqid(),
            'organization_id' => $this->org->id,
        ]);

        $this->adminUser = User::create([
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'name'            => 'Accountant Admin',
            'username'        => 'acct_exp_' . uniqid(),
            'email'           => 'acct_exp_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'branch-admin',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $this->adminUser->assignRole($role);

        app(AccountSetupService::class)->setupBranch($this->branch, $this->adminUser);

        // Seed sample journal entry
        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1000')->first();
        $capital = Account::where('branch_id', $this->branch->id)->where('code', '3000')->first();
        if ($cash && $capital) {
            PostingService::post([
                'branch_id'    => $this->branch->id,
                'entry_date'   => now(),
                'source_type'  => 'manual',
                'source_id'    => null,
                'event'        => 'manual_entry',
                'description'  => 'Opening Capital',
                'created_by'   => $this->adminUser->id,
                'lines'        => [
                    ['account_id' => $cash->id, 'debit' => 10000, 'credit' => 0, 'memo' => 'Debit Cash'],
                    ['account_id' => $capital->id, 'debit' => 0, 'credit' => 10000, 'memo' => 'Credit Capital'],
                ],
            ]);
        }
    }

    public function test_can_export_chart_of_accounts(): void
    {
        $responseXlsx = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.accounts.export', ['branch' => $this->branch->slug, 'format' => 'xlsx']));

        $responseXlsx->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $responseXlsx->headers->get('content-type'));

        $responseCsv = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.accounts.export', ['branch' => $this->branch->slug, 'format' => 'csv']));

        $responseCsv->assertStatus(200);
        $this->assertStringContainsString('text/csv', $responseCsv->headers->get('content-type'));
    }

    public function test_can_export_general_ledger(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.ledger.export', [
                'branch'    => $this->branch->slug,
                'format'    => 'xlsx',
                'from_date' => now()->startOfMonth()->toDateString(),
                'to_date'   => now()->toDateString(),
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'));
    }

    public function test_can_export_journal_entries(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.journals.export', [
                'branch' => $this->branch->slug,
                'format' => 'csv',
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_can_export_financial_reports(): void
    {
        $reportTypes = ['trial-balance', 'profit-loss', 'balance-sheet', 'cash-flow', 'ar-aging', 'vat'];

        foreach ($reportTypes as $type) {
            $response = $this->actingAs($this->adminUser)
                ->withSession(['current_branch_id' => $this->branch->id])
                ->get(route('pos.accounting.reports.export', [
                    'branch' => $this->branch->slug,
                    'type'   => $type,
                    'format' => 'xlsx',
                ]));

            $response->assertStatus(200);
            $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('content-type'), "Failed exporting {$type} to xlsx");

            $responseCsv = $this->actingAs($this->adminUser)
                ->withSession(['current_branch_id' => $this->branch->id])
                ->get(route('pos.accounting.reports.export', [
                    'branch' => $this->branch->slug,
                    'type'   => $type,
                    'format' => 'csv',
                ]));

            $responseCsv->assertStatus(200);
            $this->assertStringContainsString('text/csv', $responseCsv->headers->get('content-type'), "Failed exporting {$type} to csv");
        }
    }
}
