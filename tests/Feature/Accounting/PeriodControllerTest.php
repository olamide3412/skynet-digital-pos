<?php

namespace Tests\Feature\Accounting;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Accounting\Models\FinancialPeriod;
use App\Modules\Accounting\Services\AccountSetupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PeriodControllerTest extends TestCase
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
            'name'                => 'Accounting Period Test Org ' . uniqid(),
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
            'name'            => 'Period Branch ' . uniqid(),
            'slug'            => 'period-branch-' . uniqid(),
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

    public function test_financial_periods_index_loads_successfully()
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->get(route('pos.accounting.periods.index', ['branch' => $this->branch->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Accounting/Periods/Index')
                ->has('periods')
        );
    }

    public function test_can_close_and_reopen_period()
    {
        $period = FinancialPeriod::where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertEquals('open', $period->status);

        // Close
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('pos.accounting.periods.close', [
                'branch' => $this->branch->slug,
                'period' => $period->id,
            ]));

        $response->assertSessionHas('success');
        $this->assertEquals('closed', $period->fresh()->status);
        $this->assertNotNull($period->fresh()->closed_at);

        // Reopen
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_branch_id' => $this->branch->id])
            ->post(route('pos.accounting.periods.reopen', [
                'branch' => $this->branch->slug,
                'period' => $period->id,
            ]));

        $response->assertSessionHas('success');
        $this->assertEquals('open', $period->fresh()->status);
        $this->assertNull($period->fresh()->closed_at);
    }
}
