<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Services\AccountSetupService;
use App\Modules\Accounting\Services\PostingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class JournalControllerTest extends TestCase
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
            'name'                => 'Accounting Journal Test Org ' . uniqid(),
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
            'name'            => 'Journal Branch ' . uniqid(),
            'slug'            => 'journal-branch-' . uniqid(),
            'organization_id' => $this->org->id,
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

        setPermissionsTeamId($this->branch->id);
        $role = \Spatie\Permission\Models\Role::firstOrCreate([
            'name'       => 'branch-admin',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $this->adminUser->assignRole($role);

        app(AccountSetupService::class)->setupBranch($this->branch, $this->adminUser);
    }

    public function test_journal_index_loads_successfully_with_user_relationship()
    {
        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1010')->firstOrFail();
        $capital = Account::where('branch_id', $this->branch->id)->where('code', '3000')->firstOrFail();

        // Create a posted journal entry
        PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => now(),
            'source_type'  => 'manual',
            'source_id'    => null,
            'event'        => 'manual_entry',
            'description'  => 'Initial Capital Contribution',
            'created_by'   => $this->adminUser->id,
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 5000, 'credit' => 0, 'memo' => 'Debit Cash'],
                ['account_id' => $capital->id, 'debit' => 0, 'credit' => 5000, 'memo' => 'Credit Capital'],
            ],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.journals.index', [
                'branch' => $this->branch->slug,
                'search' => 'Initial',
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Accounting/Journals/Index')
                ->has('entries.data')
                ->has('accounts')
        );
    }

    public function test_journal_show_loads_successfully()
    {
        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1010')->firstOrFail();
        $capital = Account::where('branch_id', $this->branch->id)->where('code', '3000')->firstOrFail();

        $entry = PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => now(),
            'source_type'  => 'manual',
            'source_id'    => null,
            'event'        => 'manual_entry',
            'description'  => 'Test Show Entry',
            'created_by'   => $this->adminUser->id,
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0, 'memo' => 'Cash in'],
                ['account_id' => $capital->id, 'debit' => 0, 'credit' => 1000, 'memo' => 'Capital in'],
            ],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.journals.show', [
                'branch'  => $this->branch->slug,
                'journal' => $entry->id,
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Accounting/Journals/Show')
                ->has('entry')
        );
    }

    public function test_journal_reverse_creates_reversing_entry()
    {
        $cash = Account::where('branch_id', $this->branch->id)->where('code', '1010')->firstOrFail();
        $capital = Account::where('branch_id', $this->branch->id)->where('code', '3000')->firstOrFail();

        $entry = PostingService::post([
            'branch_id'    => $this->branch->id,
            'entry_date'   => now(),
            'source_type'  => 'manual',
            'source_id'    => null,
            'event'        => 'manual_entry',
            'description'  => 'Entry to reverse',
            'created_by'   => $this->adminUser->id,
            'lines'        => [
                ['account_id' => $cash->id, 'debit' => 200, 'credit' => 0, 'memo' => ''],
                ['account_id' => $capital->id, 'debit' => 0, 'credit' => 200, 'memo' => ''],
            ],
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('pos.accounting.journals.reverse', [
                'branch'  => $this->branch->slug,
                'journal' => $entry->id,
            ]), [
                'reason' => 'Duplicate entry posted by accident',
            ]);

        $response->assertSessionHas('success');
        $this->assertEquals('reversed', $entry->fresh()->status);
    }
}
