<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryCategory;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryOrderItem;
use App\Modules\Laundry\Models\LaundryOrderStatusHistory;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaundryStaffReportQueryTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $adminUser;
    protected User $cashier1;
    protected User $cashier2;
    protected PosCustomer $customer;
    protected LaundryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'                => 'Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Report Test Branch ' . uniqid(),
            'slug'            => 'report-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        PosSettings::create([
            'branch_id'       => $this->branch->id,
            'laundry_enabled' => true,
        ]);

        LaundrySetting::create([
            'branch_id' => $this->branch->id,
            'unclaimed_after_days' => 14,
        ]);

        $this->branch->ensureDefaultRoles();

        $this->adminUser = User::factory()->create([
            'branch_id'      => $this->branch->id,
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $this->cashier1 = User::factory()->create([
            'branch_id' => $this->branch->id,
            'name'      => 'Alice Cashier',
            'username'  => 'alice_' . uniqid(),
            'is_active' => true,
        ]);

        $this->cashier2 = User::factory()->create([
            'branch_id' => $this->branch->id,
            'name'      => 'Bob Cashier',
            'username'  => 'bob_' . uniqid(),
            'is_active' => true,
        ]);

        $this->customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Customer John',
            'phone'     => '08012345678',
        ]);

        $category = LaundryCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Wash & Iron',
        ]);

        $this->service = LaundryService::create([
            'branch_id'    => $this->branch->id,
            'category_id'  => $category->id,
            'name'         => 'Suit Dry Clean',
            'pricing_type' => 'per_piece',
            'price'        => 3000,
            'is_active'    => true,
        ]);

        app()->instance('current_branch', $this->branch);
    }

    public function test_admin_can_query_laundry_reports_with_date_range(): void
    {
        $today = Carbon::today();

        // Cashier 1 creates order today
        $order = LaundryOrder::create([
            'branch_id'       => $this->branch->id,
            'order_number'    => 'LND-TST-001',
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'customer_phone'  => $this->customer->phone,
            'status'          => 'received',
            'received_at'     => $today->copy()->setTime(10, 0),
            'due_at'          => $today->copy()->addDays(2),
            'created_by'      => $this->cashier1->id,
            'subtotal'        => 6000,
            'total'           => 6000,
            'amount_paid'     => 4000,
            'balance'         => 2000,
        ]);

        LaundryOrderItem::create([
            'order_id'     => $order->id,
            'service_id'   => $this->service->id,
            'service_name' => $this->service->name,
            'pricing_type' => 'per_piece',
            'unit_price'   => 3000,
            'quantity'     => 2,
            'line_total'   => 6000,
        ]);

        // Cashier 1 records payment
        LaundryPayment::create([
            'order_id'    => $order->id,
            'branch_id'   => $this->branch->id,
            'type'        => 'payment',
            'amount'      => 4000,
            'method'      => 'Cash',
            'recorded_by' => $this->cashier1->id,
            'paid_at'     => $today->copy()->setTime(10, 5),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('laundry.reports.index', [
                'branch' => $this->branch->slug,
                'range'  => 'today',
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Laundry/Reports/Index')
            ->has('metrics')
            ->where('metrics.orders_count', 1)
            ->where('metrics.orders_value', 6000)
            ->where('metrics.net_collections', 4000)
            ->has('users')
            ->has('staff_summary')
        );
    }

    public function test_admin_can_query_specific_cashier_actions_and_activity_feed(): void
    {
        $today = Carbon::today();

        // Cashier 1 creates order 1
        $order1 = LaundryOrder::create([
            'branch_id'       => $this->branch->id,
            'order_number'    => 'LND-TST-ALICE-1',
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'customer_phone'  => $this->customer->phone,
            'status'          => 'in_progress',
            'received_at'     => $today->copy()->setTime(9, 30),
            'due_at'          => $today->copy()->addDays(2),
            'created_by'      => $this->cashier1->id,
            'subtotal'        => 9000,
            'total'           => 9000,
            'amount_paid'     => 9000,
            'balance'         => 0,
        ]);

        // Cashier 1 logs status change
        LaundryOrderStatusHistory::create([
            'order_id'    => $order1->id,
            'from_status' => 'received',
            'to_status'   => 'in_progress',
            'changed_by'  => $this->cashier1->id,
            'changed_at'  => $today->copy()->setTime(9, 45),
            'note'        => 'Started washing',
        ]);

        // Cashier 1 records payment
        LaundryPayment::create([
            'order_id'    => $order1->id,
            'branch_id'   => $this->branch->id,
            'type'        => 'payment',
            'amount'      => 9000,
            'method'      => 'Bank Transfer',
            'recorded_by' => $this->cashier1->id,
            'paid_at'     => $today->copy()->setTime(9, 35),
        ]);

        // Cashier 2 creates a separate order 2
        $order2 = LaundryOrder::create([
            'branch_id'       => $this->branch->id,
            'order_number'    => 'LND-TST-BOB-1',
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'customer_phone'  => $this->customer->phone,
            'status'          => 'received',
            'received_at'     => $today->copy()->setTime(11, 0),
            'due_at'          => $today->copy()->addDays(2),
            'created_by'      => $this->cashier2->id,
            'subtotal'        => 3000,
            'total'           => 3000,
            'amount_paid'     => 3000,
            'balance'         => 0,
        ]);

        LaundryPayment::create([
            'order_id'    => $order2->id,
            'branch_id'   => $this->branch->id,
            'type'        => 'payment',
            'amount'      => 3000,
            'method'      => 'Cash',
            'recorded_by' => $this->cashier2->id,
            'paid_at'     => $today->copy()->setTime(11, 5),
        ]);

        // Query filtering specifically for Cashier 1 (Alice)
        $response = $this->actingAs($this->adminUser)
            ->get(route('laundry.reports.index', [
                'branch'  => $this->branch->slug,
                'range'   => 'today',
                'user_id' => $this->cashier1->id,
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Laundry/Reports/Index')
            ->where('filters.user_id', (string) $this->cashier1->id)
            ->where('selected_user.name', 'Alice Cashier')
            ->where('metrics.orders_count', 1)
            ->where('metrics.orders_value', 9000)
            ->where('metrics.net_collections', 9000)
            ->where('metrics.status_changes_count', 1)
            ->has('user_activity_log.data', 3) // 1 order creation + 1 payment + 1 status change
        );

        // Query filtering specifically for Cashier 2 (Bob)
        $responseBob = $this->actingAs($this->adminUser)
            ->get(route('laundry.reports.index', [
                'branch'  => $this->branch->slug,
                'range'   => 'today',
                'user_id' => $this->cashier2->id,
            ]));

        $responseBob->assertOk();
        $responseBob->assertInertia(fn (Assert $page) => $page
            ->component('Laundry/Reports/Index')
            ->where('filters.user_id', (string) $this->cashier2->id)
            ->where('selected_user.name', 'Bob Cashier')
            ->where('metrics.orders_count', 1)
            ->where('metrics.orders_value', 3000)
            ->where('metrics.net_collections', 3000)
        );
    }

    public function test_staff_summary_accurately_compares_multiple_cashiers(): void
    {
        $today = Carbon::today();

        // Alice: 2 orders (5000 + 4000 = 9000), 2 payments (5000 cash, 4000 transfer)
        $orderA1 = LaundryOrder::create([
            'branch_id'       => $this->branch->id,
            'order_number'    => 'LND-TST-ALICE-S1',
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'customer_phone'  => $this->customer->phone,
            'status'          => 'ready',
            'received_at'     => $today->copy()->setTime(8, 0),
            'due_at'          => $today->copy()->addDays(2),
            'created_by'      => $this->cashier1->id,
            'total'           => 5000,
            'amount_paid'     => 5000,
            'balance'         => 0,
        ]);
        LaundryPayment::create([
            'order_id'    => $orderA1->id,
            'branch_id'   => $this->branch->id,
            'type'        => 'payment',
            'amount'      => 5000,
            'method'      => 'Cash',
            'recorded_by' => $this->cashier1->id,
            'paid_at'     => $today->copy()->setTime(8, 5),
        ]);

        $orderA2 = LaundryOrder::create([
            'branch_id'       => $this->branch->id,
            'order_number'    => 'LND-TST-ALICE-S2',
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'customer_phone'  => $this->customer->phone,
            'status'          => 'received',
            'received_at'     => $today->copy()->setTime(8, 30),
            'due_at'          => $today->copy()->addDays(2),
            'created_by'      => $this->cashier1->id,
            'total'           => 4000,
            'amount_paid'     => 4000,
            'balance'         => 0,
        ]);
        LaundryPayment::create([
            'order_id'    => $orderA2->id,
            'branch_id'   => $this->branch->id,
            'type'        => 'payment',
            'amount'      => 4000,
            'method'      => 'Bank Transfer',
            'recorded_by' => $this->cashier1->id,
            'paid_at'     => $today->copy()->setTime(8, 35),
        ]);

        // Bob: 1 order (2500), 1 payment (2500 Cash)
        $orderB1 = LaundryOrder::create([
            'branch_id'       => $this->branch->id,
            'order_number'    => 'LND-TST-BOB-S1',
            'customer_id'     => $this->customer->id,
            'customer_name'   => $this->customer->name,
            'customer_phone'  => $this->customer->phone,
            'status'          => 'received',
            'received_at'     => $today->copy()->setTime(9, 0),
            'due_at'          => $today->copy()->addDays(2),
            'created_by'      => $this->cashier2->id,
            'total'           => 2500,
            'amount_paid'     => 2500,
            'balance'         => 0,
        ]);
        LaundryPayment::create([
            'order_id'    => $orderB1->id,
            'branch_id'   => $this->branch->id,
            'type'        => 'payment',
            'amount'      => 2500,
            'method'      => 'Cash',
            'recorded_by' => $this->cashier2->id,
            'paid_at'     => $today->copy()->setTime(9, 5),
        ]);

        // Alice delivers/hands over Bob's order to customer
        $orderB1->update([
            'status'                => 'collected',
            'collected_at'          => $today->copy()->setTime(14, 0),
            'collected_by_user_id'  => $this->cashier1->id,
            'collected_by_name'     => $this->cashier1->name,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('laundry.reports.index', [
                'branch' => $this->branch->slug,
                'range'  => 'today',
            ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Laundry/Reports/Index')
            ->has('staff_summary')
            ->where('staff_summary.0.name', 'Alice Cashier')
            ->where('staff_summary.0.orders_count', 2)
            ->where('staff_summary.0.net_collections', 9000)
            ->where('staff_summary.0.cash_collected', 5000)
            ->where('staff_summary.0.bank_collected', 4000)
            ->where('staff_summary.0.collected_count', 1) // delivered 1 order
            ->where('staff_summary.1.name', 'Bob Cashier')
            ->where('staff_summary.1.orders_count', 1)
            ->where('staff_summary.1.net_collections', 2500)
        );
    }
}
