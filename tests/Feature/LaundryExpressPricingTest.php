<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosCustomer;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryCategory;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Modules\Laundry\Services\LaundryOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaundryExpressPricingTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected Branch $branch;
    protected User $admin;
    protected PosCustomer $customer;
    protected LaundrySetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name'   => 'Express Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Express Test Branch',
            'slug'            => 'express-branch-' . uniqid(),
        ]);

        setPermissionsTeamId($this->branch->id);

        $this->admin = User::create([
            'name'            => 'Express Admin',
            'username'        => 'expressadmin' . uniqid(),
            'email'           => 'admin' . uniqid() . '@testexpress.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'is_active'       => true,
        ]);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'branch-admin', 'team_id' => $this->branch->id, 'guard_name' => 'web']);
        $this->admin->syncRoles([$role]);

        $this->customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Express Customer',
            'phone'     => '08012345678',
            'address'   => 'Lagos',
            'debt_bal'  => 0,
        ]);

        $this->settings = LaundrySetting::forBranch($this->branch->id);
        $this->settings->update([
            'enable_express_service'     => true,
            'express_turnaround_hours'   => 24,
            'default_express_multiplier' => 1.50,
            'default_turnaround_days'    => 2,
        ]);
    }

    public function test_service_model_resolves_custom_and_fallback_express_price()
    {
        // 1. Service with explicit express price
        $suitService = LaundryService::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Executive 2-Piece Suit',
            'pricing_type'  => 'per_piece',
            'price'         => 2000.00,
            'express_price' => 3200.00, // explicit custom rate
            'is_active'     => true,
        ]);

        $this->assertEquals(3200.00, $suitService->resolveExpressPrice());

        // 2. Service without explicit express price (falls back to 1.50x)
        $shirtService = LaundryService::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Plain Shirt Wash & Iron',
            'pricing_type'  => 'per_piece',
            'price'         => 1000.00,
            'express_price' => null,
            'is_active'     => true,
        ]);

        $this->assertEquals(1500.00, $shirtService->resolveExpressPrice(1.50));
        // Fallback with custom multiplier 1.75
        $this->assertEquals(1750.00, $shirtService->resolveExpressPrice(1.75));
    }

    public function test_laundry_settings_updates_express_configurations()
    {
        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'canEditSettings', 'guard_name' => 'web']);
        $this->admin->givePermissionTo($perm);

        $this->actingAs($this->admin);
        session(['current_branch_id' => $this->branch->id]);

        $response = $this->post(route('pos.settings.update', ['branch' => $this->branch->slug]), [
            'tab'                        => 'laundry',
            'default_turnaround_hours'   => 48,
            'unclaimed_after_days'       => 14,
            'enable_express_service'     => true,
            'express_turnaround_hours'   => 12,
            'default_express_multiplier' => 1.60,
            'order_prefix'               => 'RUSH-',
            'whatsapp_template'          => 'Hello {customer_name}',
            'default_country_code'       => '234',
        ]);

        $response->assertSessionHasNoErrors();

        $fresh = LaundrySetting::forBranch($this->branch->id);
        $this->assertTrue((bool) $fresh->enable_express_service);
        $this->assertEquals(12, $fresh->express_turnaround_hours);
        $this->assertEquals(1.60, (float) $fresh->default_express_multiplier);
        $this->assertEquals('RUSH-', $fresh->order_prefix);
    }

    public function test_creating_order_with_express_tier_applies_express_pricing_and_shortened_due_date()
    {
        $service = LaundryService::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Duvet Deep Clean',
            'pricing_type'  => 'per_piece',
            'price'         => 4000.00,
            'express_price' => 5500.00,
            'is_active'     => true,
        ]);

        $orderService = app(LaundryOrderService::class);

        $order = $orderService->createOrder($this->branch, $this->admin, [
            'customer_id' => $this->customer->id,
            'is_express'  => true,
            'speed_tier'  => 'express',
            'items'       => [
                [
                    'service_id'   => $service->id,
                    'service_name' => $service->name,
                    'pricing_type' => 'per_piece',
                    'quantity'     => 2,
                    'is_express'   => true,
                ],
            ],
        ]);

        $this->assertTrue((bool) $order->is_express);
        $this->assertEquals('express', $order->speed_tier);

        // 2 items @ 5500 = 11000
        $this->assertEquals(11000.00, (float) $order->subtotal);
        $this->assertEquals(11000.00, (float) $order->total);

        // Item line checks
        $firstItem = $order->items->first();
        $this->assertTrue((bool) $firstItem->is_express);
        $this->assertEquals(5500.00, (float) $firstItem->unit_price);
        $this->assertEquals(11000.00, (float) $firstItem->line_total);

        // Due date: verified within ~24 hours from now
        $expectedDue = Carbon::now()->addHours(24);
        $this->assertEqualsWithDelta($expectedDue->timestamp, $order->due_at->timestamp, 60);
    }

    public function test_standard_order_uses_standard_pricing_and_standard_due_date()
    {
        $service = LaundryService::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Bedsheet Set',
            'pricing_type'  => 'per_piece',
            'price'         => 1200.00,
            'express_price' => 1800.00,
            'is_active'     => true,
        ]);

        $orderService = app(LaundryOrderService::class);

        $order = $orderService->createOrder($this->branch, $this->admin, [
            'customer_id' => $this->customer->id,
            'is_express'  => false,
            'items'       => [
                [
                    'service_id'   => $service->id,
                    'service_name' => $service->name,
                    'pricing_type' => 'per_piece',
                    'quantity'     => 1,
                    'is_express'   => false,
                ],
            ],
        ]);

        $this->assertFalse((bool) $order->is_express);
        $this->assertEquals(1200.00, (float) $order->total);
        $this->assertEquals(1200.00, (float) $order->items->first()->unit_price);

        // Standard turnaround: 2 days (48 hours)
        $expectedDue = Carbon::now()->addDays(2);
        $this->assertEqualsWithDelta($expectedDue->timestamp, $order->due_at->timestamp, 60);
    }

    public function test_mixed_order_with_individual_express_line_elevates_order_and_prices_lines_accurately()
    {
        $standardSrv = LaundryService::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Normal T-Shirt',
            'pricing_type'  => 'per_piece',
            'price'         => 800.00,
            'express_price' => 1200.00,
            'is_active'     => true,
        ]);

        $expressSrv = LaundryService::create([
            'branch_id'     => $this->branch->id,
            'name'          => 'Wedding Gown Rush',
            'pricing_type'  => 'per_piece',
            'price'         => 10000.00,
            'express_price' => 15000.00,
            'is_active'     => true,
        ]);

        $orderService = app(LaundryOrderService::class);

        $order = $orderService->createOrder($this->branch, $this->admin, [
            'customer_id' => $this->customer->id,
            'is_express'  => false, // global order flag is false
            'items'       => [
                [
                    'service_id'   => $standardSrv->id,
                    'service_name' => $standardSrv->name,
                    'pricing_type' => 'per_piece',
                    'quantity'     => 1,
                    'is_express'   => false, // standard line
                ],
                [
                    'service_id'   => $expressSrv->id,
                    'service_name' => $expressSrv->name,
                    'pricing_type' => 'per_piece',
                    'quantity'     => 1,
                    'is_express'   => true, // express line override
                ],
            ],
        ]);

        // Order is automatically elevated to express so washhouse prioritizes it
        $this->assertTrue((bool) $order->is_express);

        // Subtotal = 800 + 15000 = 15800
        $this->assertEquals(15800.00, (float) $order->subtotal);

        $lines = $order->items->values();
        $this->assertFalse((bool) $lines[0]->is_express);
        $this->assertEquals(800.00, (float) $lines[0]->unit_price);

        $this->assertTrue((bool) $lines[1]->is_express);
        $this->assertEquals(15000.00, (float) $lines[1]->unit_price);
    }
}
