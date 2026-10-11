<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportAllTimeTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'   => 'Reports Org ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'name'            => 'Reports Branch ' . uniqid(),
            'slug'            => 'branch-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->org->id,
            'is_active'       => true,
        ]);

        $this->user = User::create([
            'name'            => 'Branch Manager',
            'username'        => 'manager_' . substr(uniqid(), 0, 6),
            'email'           => 'manager_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);
        $role = Role::firstOrCreate([
            'name'       => 'branch-admin',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $this->user->assignRole($role);
    }

    public function test_reports_defaults_to_current_month(): void
    {
        // 1 Sale in previous year, 1 Sale in current month
        $oldSale = Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-OLD-' . uniqid(),
            'final_total'     => 15000,
            'profit_made'     => 5000,
            'discount_amount' => 0,
            'cash'            => 15000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::now()->subMonths(6),
        ]);

        $newSale = Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-NEW-' . uniqid(),
            'final_total'     => 25000,
            'profit_made'     => 8000,
            'discount_amount' => 0,
            'cash'            => 25000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::now()->startOfMonth()->addDays(1),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pos.reports.index', ['branch' => $this->branch->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('periodStats.sales_count', 1)
            ->where('periodStats.revenue', 25000)
            ->where('filters.range', '')
        );
    }

    public function test_reports_all_time_queries_all_historical_sales(): void
    {
        // 1 Sale from 6 months ago, 1 Sale from 2 months ago, 1 Sale from current month
        Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-OLD-1-' . uniqid(),
            'final_total'     => 10000,
            'profit_made'     => 3000,
            'discount_amount' => 0,
            'cash'            => 10000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::now()->subMonths(6),
        ]);

        Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-OLD-2-' . uniqid(),
            'final_total'     => 15000,
            'profit_made'     => 5000,
            'discount_amount' => 0,
            'cash'            => 15000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::now()->subMonths(2),
        ]);

        Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-CURR-' . uniqid(),
            'final_total'     => 20000,
            'profit_made'     => 7000,
            'discount_amount' => 0,
            'cash'            => 20000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::now()->startOfMonth()->addDays(1),
        ]);

        // Request with range=all
        $response = $this->actingAs($this->user)
            ->get(route('pos.reports.index', ['branch' => $this->branch->slug, 'range' => 'all']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('periodStats.sales_count', 3)
            ->where('periodStats.revenue', 45000)
            ->where('periodStats.profit', 15000)
            ->where('filters.range', 'all')
        );

        // Also test with empty from and to (which happens when clicking All Time without range)
        $responseEmptyDates = $this->actingAs($this->user)
            ->get(route('pos.reports.index', ['branch' => $this->branch->slug, 'from' => '', 'to' => '']));

        $responseEmptyDates->assertStatus(200);
        $responseEmptyDates->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('periodStats.sales_count', 3)
            ->where('periodStats.revenue', 45000)
            ->where('periodStats.profit', 15000)
            ->where('filters.range', 'all')
        );
    }

    public function test_reports_today_preset_filters_only_today_sales(): void
    {
        $todayStr = Carbon::today()->format('Y-m-d');

        Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-TODAY-' . uniqid(),
            'final_total'     => 12000,
            'profit_made'     => 4000,
            'discount_amount' => 0,
            'cash'            => 12000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::today()->midDay(),
        ]);

        Sale::create([
            'branch_id'       => $this->branch->id,
            'user_id'         => $this->user->id,
            'receipt_id'      => 'REC-YESTERDAY-' . uniqid(),
            'final_total'     => 8000,
            'profit_made'     => 2000,
            'discount_amount' => 0,
            'cash'            => 8000,
            'payment_method'  => 'cash',
            'created_at'      => Carbon::yesterday()->midDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pos.reports.index', ['branch' => $this->branch->slug, 'from' => $todayStr, 'to' => $todayStr]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->where('periodStats.sales_count', 1)
            ->where('periodStats.revenue', 12000)
            ->where('filters.from', $todayStr)
            ->where('filters.to', $todayStr)
        );
    }
}
