<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Branch;
use App\Models\User;
use App\Models\PosCustomer;
use App\Models\DebtPayment;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Modules\Laundry\Services\LaundryOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LaundryDebtLedgerPaymentSyncTest extends TestCase
{
    use DatabaseTransactions;

    protected \App\Models\Organization $org;
    protected Branch $branch;
    protected User $adminUser;
    protected PosCustomer $customer;
    protected LaundryOrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);

        $this->org = \App\Models\Organization::create([
            'name'   => 'Test Org Laundry ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Main Laundry Branch ' . uniqid(),
            'slug'            => 'main-laundry-' . uniqid(),
            'address'         => '123 Test Street',
            'phone'           => '08012345678',
            'email'           => 'laundry@test.com',
            'is_active'       => true,
        ]);

        $this->adminUser = User::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Admin ' . uniqid(),
            'username'        => 'admin_' . uniqid(),
            'email'           => 'admin_' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'is_super_admin'  => true,
            'is_active'       => true,
        ]);

        $this->customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Chief Obafemi ' . uniqid(),
            'phone'     => '080' . rand(10000000, 99999999),
            'debt_bal'  => 0,
        ]);

        \App\Models\PosSettings::forBranch($this->branch->id)->update([
            'require_till_opening' => false,
            'laundry_enabled'      => true,
        ]);

        $this->orderService = app(LaundryOrderService::class);
    }

    public function test_paying_debt_from_customer_debt_ledger_updates_laundry_order_and_clears_balance(): void
    {
        $this->actingAs($this->adminUser);
        session(['current_branch_id' => $this->branch->id]);

        // 1. Create a laundry order with total 10,000 and 2,000 deposit
        $order = $this->orderService->createOrder($this->branch, $this->adminUser, [
            'customer_id'   => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone'=> $this->customer->phone,
            'due_at'        => now()->addDays(2),
            'items'         => [
                [
                    'service_name' => 'Executive Agbada 3pc Clean',
                    'pricing_type' => 'fixed',
                    'unit_price'   => 10000,
                    'quantity'     => 1,
                    'line_total'   => 10000,
                ],
            ],
            'deposit_amount'=> 2000,
            'payment_method'=> 'Cash',
        ]);

        $this->assertEquals(8000.0, (float) $order->balance);
        $this->assertEquals(2000.0, (float) $order->amount_paid);

        // 2. Mark order as ready and collected (unpaid balance 8,000 moves to customer debt)
        $this->orderService->updateStatus($order, 'ready', $this->adminUser);
        $this->orderService->updateStatus($order, 'collected', $this->adminUser, 'Handed to customer', 'Chief Obafemi');

        $this->customer->refresh();
        $order->refresh();
        $this->assertEquals(8000.0, (float) $this->customer->debt_bal);
        $this->assertEquals(8000.0, (float) $order->balance);

        // Verify debit DebtPayment was logged
        $debitPayment = DebtPayment::where('customer_id', $this->customer->id)
            ->where('type', 'debit')
            ->first();
        $this->assertNotNull($debitPayment);
        $this->assertEquals('laundry', $debitPayment->source);
        $this->assertEquals($order->id, $debitPayment->laundry_order_id);
        $this->assertEquals(8000.0, (float) $debitPayment->amount);

        // 3. Customer makes a partial debt payment of 5,000 on the Customer Debt Ledger page
        $response = $this->post(route('pos.customers.debt', [
            'branch'   => $this->branch->slug,
            'customer' => $this->customer->id,
        ]), [
            'amount'    => 5000,
            'type'      => 'credit',
            'method'    => 'Bank Transfer',
            'narration' => 'Bank transfer deposit at debt ledger',
        ]);

        $response->assertSessionHasNoErrors();

        $this->customer->refresh();
        $order->refresh();

        // Customer debt balance is now 3,000
        $this->assertEquals(3000.0, (float) $this->customer->debt_bal);

        // Laundry order MUST reflect the 5,000 payment: paid = 7000, balance = 3000
        $this->assertEquals(7000.0, (float) $order->amount_paid);
        $this->assertEquals(3000.0, (float) $order->balance);

        // Verify a LaundryPayment was logged on the order
        $laundryPayment = LaundryPayment::where('order_id', $order->id)
            ->where('amount', 5000)
            ->first();
        $this->assertNotNull($laundryPayment);
        $this->assertEquals('Bank Transfer', $laundryPayment->method);
        $this->assertStringContainsString('Debt ledger settlement', $laundryPayment->note);

        // 4. Pay remaining 3,000 from the debt ledger page to fully clear debt
        $response2 = $this->post(route('pos.customers.debt', [
            'branch'   => $this->branch->slug,
            'customer' => $this->customer->id,
        ]), [
            'amount'    => 3000,
            'type'      => 'credit',
            'method'    => 'Cash',
            'narration' => 'Final cash settlement',
        ]);

        $response2->assertSessionHasNoErrors();

        $this->customer->refresh();
        $order->refresh();

        // Customer debt is 0, Laundry order balance is 0 (Paid in full)
        $this->assertEquals(0.0, (float) $this->customer->debt_bal);
        $this->assertEquals(0.0, (float) $order->balance);
        $this->assertEquals(10000.0, (float) $order->amount_paid);
    }

    public function test_paying_debt_from_ledger_prevents_paying_again_on_order(): void
    {
        $this->actingAs($this->adminUser);
        session(['current_branch_id' => $this->branch->id]);

        // Create laundry order for 6,000 with 0 paid
        $order = $this->orderService->createOrder($this->branch, $this->adminUser, [
            'customer_id'   => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone'=> $this->customer->phone,
            'due_at'        => now()->addDays(2),
            'items'         => [
                [
                    'service_name' => 'Blanket Wash',
                    'pricing_type' => 'fixed',
                    'unit_price'   => 6000,
                    'quantity'     => 1,
                    'line_total'   => 6000,
                ],
            ],
            'amount_paid'   => 0,
            'payment_method'=> 'Cash',
        ]);

        $this->orderService->updateStatus($order, 'ready', $this->adminUser);
        $this->orderService->updateStatus($order, 'collected', $this->adminUser);

        $this->customer->refresh();
        $this->assertEquals(6000.0, (float) $this->customer->debt_bal);

        // Pay 6,000 on debt ledger
        $response = $this->post(route('pos.customers.debt', [
            'branch'   => $this->branch->slug,
            'customer' => $this->customer->id,
        ]), [
            'amount' => 6000,
            'type'   => 'credit',
            'method' => 'Cash',
        ]);

        $response->assertSessionHasNoErrors();

        $this->customer->refresh();
        $order->refresh();

        // Customer debt is 0 and the order balance is updated to 0
        $this->assertEquals(0.0, (float) $this->customer->debt_bal);
        $this->assertEquals(0.0, (float) $order->balance);
        $this->assertEquals(6000.0, (float) $order->amount_paid);

        // Now attempt to record payment on the order directly - should fail because balance is 0
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->orderService->recordPayment($order, $this->adminUser, [
            'amount' => 1000,
            'method' => 'Cash',
        ]);
    }
}
