<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\TillSession;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Modules\Laundry\Services\BranchLandingService;
use App\Modules\Laundry\Services\LaundryOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaundryModuleComprehensiveTest extends TestCase
{
    protected \App\Models\Organization $org;
    protected Branch $branch;
    protected User $branchAdmin;
    protected User $cashier;
    protected PosSettings $posSettings;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);

        $this->org = \App\Models\Organization::create([
            'name'   => 'Test Org Laundry ' . uniqid(),
            'status' => 'active',
        ]);

        // Find or create test branch
        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Test Laundry Branch ' . uniqid(),
            'slug'            => 'test-laundry-' . uniqid() . '-' . rand(1000, 9999),
            'address'         => '123 Test Street',
            'phone'           => '08012345678',
            'email'           => 'laundry@test.com',
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);

        $this->posSettings = PosSettings::forBranch($this->branch->id);
        $this->posSettings->update([
            'laundry_enabled'   => false,
            'pos_enabled'       => true,
            'default_interface' => 'pos',
            'is_tax_enabled'    => true,
            'tax_percentage'    => 7.5,
        ]);

        // Branch Admin user
        $this->branchAdmin = User::create([
            'name'            => 'Laundry Branch Admin',
            'username'        => 'laundryadmin' . uniqid(),
            'email'           => 'admin' . uniqid() . '@testlaundry.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'is_active'       => true,
        ]);
        $adminRole = Role::firstOrCreate(['name' => 'branch-admin', 'team_id' => $this->branch->id, 'guard_name' => 'web']);
        $this->branchAdmin->syncRoles([$adminRole]);

        // Cashier user
        $this->cashier = User::create([
            'name'            => 'Laundry Cashier',
            'username'        => 'laundrycashier' . uniqid(),
            'email'           => 'cashier' . uniqid() . '@testlaundry.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'is_active'       => true,
        ]);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'team_id' => $this->branch->id, 'guard_name' => 'web']);
        $this->cashier->syncRoles([$cashierRole]);
    }

    /**
     * 1. Default mode: Laundry is OFF by default.
     * Accessing laundry routes redirects to POS.
     */
    public function test_laundry_is_disabled_by_default_and_redirects_to_pos(): void
    {
        $this->actingAs($this->branchAdmin, 'web');

        $response = $this->get(route('laundry.home', ['branch' => $this->branch->slug]));
        $response->assertRedirect(route('pos.index', ['branch' => $this->branch->slug]));
    }

    /**
     * 2. Enabling Laundry allows access to Laundry Terminal.
     */
    public function test_laundry_can_be_enabled_and_accessed(): void
    {
        $this->posSettings->update(['laundry_enabled' => true]);

        $this->actingAs($this->branchAdmin, 'web');

        $response = $this->get(route('laundry.home', ['branch' => $this->branch->slug]));
        $response->assertOk();
    }

    /**
     * 3. Landing Resolver honors default_interface.
     */
    public function test_landing_resolver_honors_branch_default_interface(): void
    {
        $this->posSettings->update([
            'laundry_enabled'   => true,
            'pos_enabled'       => true,
            'default_interface' => 'laundry',
        ]);

        $route = BranchLandingService::resolveLandingRoute($this->branch, $this->branchAdmin);
        $this->assertEquals('laundry.home', $route);

        $this->posSettings->update(['default_interface' => 'pos']);
        $routePos = BranchLandingService::resolveLandingRoute($this->branch, $this->branchAdmin);
        $this->assertEquals('pos.index', $routePos);
    }

    /**
     * 4. Laundry Only mode: POS index redirects to Laundry Home.
     */
    public function test_laundry_only_mode_redirects_pos_index_to_laundry_home(): void
    {
        $this->posSettings->update([
            'laundry_enabled'   => true,
            'pos_enabled'       => false,
            'default_interface' => 'laundry',
        ]);

        $this->actingAs($this->branchAdmin, 'web');

        $response = $this->get(route('pos.index', ['branch' => $this->branch->slug]));
        $response->assertRedirect(route('laundry.home', ['branch' => $this->branch->slug]));
    }

    /**
     * 5. Order Intake: Creation with items, tax calculation, and deposit payment in single transaction.
     */
    public function test_order_intake_creates_snapshot_and_payment(): void
    {
        $this->posSettings->update(['laundry_enabled' => true]);

        // Open till session for cash payment
        TillSession::create([
            'branch_id'      => $this->branch->id,
            'cashier_id'     => $this->branchAdmin->id,
            'opening_amount' => 1000,
            'opened_at'      => Carbon::now(),
            'status'         => 'open',
        ]);

        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Adebayo Tunde',
            'phone'     => '08022334455',
            'debt_bal'  => 0,
        ]);

        $service1 = LaundryService::create([
            'branch_id'    => $this->branch->id,
            'name'         => 'Suit Dry Cleaning',
            'pricing_type' => 'per_piece',
            'price'        => 2500,
            'is_active'    => true,
        ]);

        $service2 = LaundryService::create([
            'branch_id'    => $this->branch->id,
            'name'         => 'Wash & Fold Laundry',
            'pricing_type' => 'per_kg',
            'price'        => 800,
            'is_active'    => true,
        ]);

        $this->actingAs($this->branchAdmin, 'web');

        $orderPayload = [
            'customer_id'     => $customer->id,
            'items'           => [
                [
                    'service_id'   => $service1->id,
                    'service_name' => $service1->name,
                    'pricing_type' => 'per_piece',
                    'unit_price'   => 2500,
                    'quantity'     => 2,
                    'note'         => 'Navy blue suit',
                ],
                [
                    'service_id'   => $service2->id,
                    'service_name' => $service2->name,
                    'pricing_type' => 'per_kg',
                    'unit_price'   => 800,
                    'quantity'     => 3.5,
                    'note'         => 'Towels and tees',
                ],
            ],
            'discount_amount' => 500,
            'deposit_amount'  => 3000,
            'payment_method'  => 'Cash',
            'note'            => 'Handle with care',
        ];

        $response = $this->post(route('laundry.orders.store', ['branch' => $this->branch->slug]), $orderPayload);
        $response->assertSessionHasNoErrors();

        $order = LaundryOrder::where('branch_id', $this->branch->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals('Adebayo Tunde', $order->customer_name);
        $this->assertEquals('08022334455', $order->customer_phone);

        // Subtotal = (2 * 2500) + (3.5 * 800) = 5000 + 2800 = 7800
        $this->assertEquals(7800.0, (float)$order->subtotal);
        $this->assertEquals(500.0, (float)$order->discount_amount);
        // Taxable = 7300. Tax at 7.5% = 547.5
        $this->assertEquals(547.5, (float)$order->tax_amount);
        // Total = 7300 + 547.5 = 7847.5
        $this->assertEquals(7847.5, (float)$order->total);
        // Paid = 3000
        $this->assertEquals(3000.0, (float)$order->amount_paid);
        // Balance = 4847.5
        $this->assertEquals(4847.5, (float)$order->balance);

        // Check payment recorded
        $payment = $order->payments()->first();
        $this->assertNotNull($payment);
        $this->assertEquals(3000.0, (float)$payment->amount);
        $this->assertEquals('Cash', $payment->method);
    }

    /**
     * 6. Order Workflow Status Transitions & Lifecycle History.
     */
    public function test_order_status_transitions_record_audit_history(): void
    {
        $this->posSettings->update(['laundry_enabled' => true]);

        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Test Customer',
            'phone'     => '08099887766',
            'debt_bal'  => 0,
        ]);

        $order = LaundryOrder::create([
            'branch_id'      => $this->branch->id,
            'order_number'   => 'LDY-TEST-001',
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'customer_phone' => $customer->phone,
            'status'         => 'received',
            'subtotal'       => 1000,
            'total'          => 1000,
            'due_at'         => Carbon::now()->addDays(2),
            'created_by'     => $this->branchAdmin->id,
            'received_at'    => Carbon::now(),
        ]);

        $this->actingAs($this->branchAdmin, 'web');

        // Transition: Received -> In Progress
        $this->post(route('laundry.orders.update-status', ['branch' => $this->branch->slug, 'order' => $order->id]), [
            'status' => 'in_progress',
            'note'   => 'Started washing',
        ]);
        $order->refresh();
        $this->assertEquals('in_progress', $order->status);

        // Transition: In Progress -> Ready
        $this->post(route('laundry.orders.update-status', ['branch' => $this->branch->slug, 'order' => $order->id]), [
            'status' => 'ready',
            'note'   => 'Ironed and packed',
        ]);
        $order->refresh();
        $this->assertEquals('ready', $order->status);

        // Check status history rows
        $this->assertEquals(2, $order->statusHistory()->count());
    }

    /**
     * 7. Unpaid Pickup: Releasing order with unpaid balance posts to customer debt ledger.
     */
    public function test_unpaid_pickup_posts_balance_to_customer_debt_ledger(): void
    {
        $this->posSettings->update(['laundry_enabled' => true]);

        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Debtor Customer',
            'phone'     => '08077665544',
            'debt_bal'  => 1000, // starting debt
        ]);

        $order = LaundryOrder::create([
            'branch_id'      => $this->branch->id,
            'order_number'   => 'LDY-TEST-002',
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'customer_phone' => $customer->phone,
            'status'         => 'ready',
            'subtotal'       => 5000,
            'total'          => 5000,
            'amount_paid'    => 0,
            'balance'        => 5000,
            'due_at'         => Carbon::now()->addDays(2),
            'created_by'     => $this->branchAdmin->id,
            'received_at'    => Carbon::now(),
        ]);

        $this->actingAs($this->branchAdmin, 'web');

        // Mark collected with 5000 unpaid balance
        $this->post(route('laundry.orders.collect', ['branch' => $this->branch->slug, 'order' => $order->id]), [
            'collected_by_name' => 'John Doe Brother',
            'note'              => 'Allowed on store credit',
        ]);

        $order->refresh();
        $customer->refresh();

        $this->assertEquals('collected', $order->status);
        $this->assertEquals('John Doe Brother', $order->collected_by_name);
        // Customer debt should increase by 5000: 1000 + 5000 = 6000
        $this->assertEquals(6000.0, (float)$customer->debt_bal);
    }

    /**
     * 8. Till Session Integration: Cash payments from laundry increase Expected Cash in active till session.
     */
    public function test_laundry_cash_payments_integrate_into_till_session_expected_cash(): void
    {
        $this->posSettings->update(['laundry_enabled' => true]);

        // Open a Till Session
        $session = TillSession::create([
            'branch_id'      => $this->branch->id,
            'cashier_id'     => $this->branchAdmin->id,
            'opening_amount' => 5000,
            'opened_at'      => Carbon::now(),
            'status'         => 'open',
        ]);

        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Cash Customer',
            'phone'     => '08011223344',
            'debt_bal'  => 0,
        ]);

        $order = LaundryOrder::create([
            'branch_id'      => $this->branch->id,
            'order_number'   => 'LDY-TEST-TILL',
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'customer_phone' => $customer->phone,
            'status'         => 'received',
            'subtotal'       => 4000,
            'total'          => 4000,
            'due_at'         => Carbon::now()->addDays(2),
            'created_by'     => $this->branchAdmin->id,
            'received_at'    => Carbon::now(),
        ]);

        $this->actingAs($this->branchAdmin, 'web');

        // Record Cash Payment of 2500
        $this->post(route('laundry.orders.record-payment', ['branch' => $this->branch->slug, 'order' => $order->id]), [
            'amount' => 2500,
            'method' => 'Cash',
            'note'   => 'Cash deposit at counter',
        ]);

        // Calculate Till Summary
        $summary = $session->calculateSummary();

        // Expected Cash should include Opening Amount (5000) + Laundry Cash Payment (2500) = 7500
        $this->assertEquals(7500.0, (float)$summary['expected_cash']);
    }

    /**
     * 9. WhatsApp Phone Normalization: Converts local leading 0 numbers to international format.
     */
    public function test_whatsapp_phone_normalization(): void
    {
        $phone1 = '08032072831';
        $normalized1 = LaundryOrderService::normalizeWhatsAppPhone($phone1, '234');
        $this->assertEquals('2348032072831', $normalized1);

        $phone2 = '+234 803 207 2831';
        $normalized2 = LaundryOrderService::normalizeWhatsAppPhone($phone2, '234');
        $this->assertEquals('2348032072831', $normalized2);
    }

    /**
     * 10. Safety Rules: Cannot disable both interfaces.
     */
    public function test_cannot_disable_both_pos_and_laundry(): void
    {
        $this->posSettings->update([
            'pos_enabled'     => true,
            'laundry_enabled' => false,
        ]);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);
        $this->actingAs($superAdmin);

        $response = $this->put(route('superadmin.branches.settings.update', ['branch' => $this->branch->slug]), [
            'is_tax_enabled'             => true,
            'tax_percentage'             => 7.5,
            'receipt_paper_size'         => '80mm',
            'receipt_copies'             => 1,
            'auto_print_receipt'         => false,
            'show_receipt_preview'       => false,
            'is_offline_enabled'         => false,
            'is_turnstile_enabled'       => true,
            'is_auto_fullscreen_enabled' => false,
            'is_price_editable'          => false,
            'is_qty_deduction'           => true,
            'out_of_stock'               => 0,
            'is_check_expiration'        => false,
            'is_imei_enabled'            => false,
            'is_show_buy_price'          => false,
            'is_use_profit_percentage'   => false,
            'sell_interface'             => 'classic',
            'business_sector'            => 'commerce',
            'pos_enabled'                => false,
            'laundry_enabled'            => false,
        ]);

        $response->assertSessionHasErrors('interface');
    }

    /**
     * 11. Safety Rules: Cannot disable Laundry while open orders exist.
     */
    public function test_cannot_disable_laundry_while_open_orders_exist(): void
    {
        $this->posSettings->update([
            'pos_enabled'     => true,
            'laundry_enabled' => true,
        ]);

        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Open Order Customer',
            'phone'     => '08099881122',
            'debt_bal'  => 0,
        ]);

        LaundryOrder::create([
            'branch_id'      => $this->branch->id,
            'order_number'   => 'LDY-OPEN-ORDER',
            'customer_id'    => $customer->id,
            'customer_name'  => $customer->name,
            'customer_phone' => $customer->phone,
            'status'         => 'in_progress',
            'subtotal'       => 3000,
            'total'          => 3000,
            'due_at'         => Carbon::now()->addDays(2),
            'created_by'     => $this->branchAdmin->id,
            'received_at'    => Carbon::now(),
        ]);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);
        $this->actingAs($superAdmin);

        $response = $this->put(route('superadmin.branches.settings.update', ['branch' => $this->branch->slug]), [
            'is_tax_enabled'             => true,
            'tax_percentage'             => 7.5,
            'receipt_paper_size'         => '80mm',
            'receipt_copies'             => 1,
            'auto_print_receipt'         => false,
            'show_receipt_preview'       => false,
            'is_offline_enabled'         => false,
            'is_turnstile_enabled'       => true,
            'is_auto_fullscreen_enabled' => false,
            'is_price_editable'          => false,
            'is_qty_deduction'           => true,
            'out_of_stock'               => 0,
            'is_check_expiration'        => false,
            'is_imei_enabled'            => false,
            'is_show_buy_price'          => false,
            'is_use_profit_percentage'   => false,
            'sell_interface'             => 'classic',
            'business_sector'            => 'commerce',
            'pos_enabled'                => true,
            'laundry_enabled'            => false,
        ]);

        $response->assertSessionHasErrors('laundry_enabled');
    }
}
