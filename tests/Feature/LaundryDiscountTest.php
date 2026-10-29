<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosCustomer;
use App\Models\PosSettings;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryCategory;
use App\Modules\Laundry\Models\LaundryDiscount;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use App\Modules\Laundry\Services\LaundryDiscountService;
use App\Modules\Laundry\Services\LaundryOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LaundryDiscountTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $user;
    protected LaundryService $suitService;
    protected LaundryService $duvetService;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Database\Eloquent\Model::preventLazyLoading(false);

        $this->org = Organization::create([
            'name'   => 'Test Org Laundry ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Discount Test Branch ' . uniqid(),
            'slug'            => 'discount-branch-' . uniqid(),
            'address'         => '123 Promo Way',
            'phone'           => '08099887766',
            'email'           => 'promo@testlaundry.com',
            'is_active'       => true,
        ]);

        $this->user = User::create([
            'name'            => 'Discount Staff',
            'username'        => 'staff' . uniqid(),
            'email'           => 'staff' . uniqid() . '@test.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'is_active'       => true,
        ]);

        // Services
        $cat = LaundryCategory::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Dry Cleaning',
            'slug'      => 'dry-cleaning-' . uniqid(),
            'is_active' => true,
        ]);

        $this->suitService = LaundryService::create([
            'branch_id'    => $this->branch->id,
            'category_id'  => $cat->id,
            'name'         => '2-Piece Suit',
            'pricing_type' => 'per_piece',
            'price'        => 3000.00,
            'is_active'    => true,
        ]);

        $this->duvetService = LaundryService::create([
            'branch_id'    => $this->branch->id,
            'category_id'  => $cat->id,
            'name'         => 'Heavy Duvet Wash',
            'pricing_type' => 'per_piece',
            'price'        => 5000.00,
            'is_active'    => true,
        ]);
    }

    /**
     * Test creating a service-level promotional discount with duration window.
     */
    public function test_can_create_service_promo_discount_with_duration(): void
    {
        $discount = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'service',
            'service_id'     => $this->suitService->id,
            'name'           => '15% Off Suit Cleaning',
            'discount_type'  => 'percentage',
            'discount_value' => 15.00,
            'starts_at'      => Carbon::now()->subDay(),
            'ends_at'        => Carbon::now()->addDays(7),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $this->assertDatabaseHas('laundry_discounts', [
            'id'             => $discount->id,
            'name'           => '15% Off Suit Cleaning',
            'scope'          => 'service',
            'service_id'     => $this->suitService->id,
            'discount_value' => 15.00,
        ]);

        $this->assertTrue($discount->isActiveNow());
        $this->assertEquals('active', $discount->status);
    }

    /**
     * Test creating a general order-wide promotional discount with duration window.
     */
    public function test_can_create_general_order_promo_discount_with_duration(): void
    {
        $discount = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'general',
            'service_id'     => null,
            'name'           => '10% Summer Store Promo',
            'discount_type'  => 'percentage',
            'discount_value' => 10.00,
            'starts_at'      => Carbon::now()->subHours(2),
            'ends_at'        => Carbon::now()->addDays(5),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $this->assertDatabaseHas('laundry_discounts', [
            'id'    => $discount->id,
            'name'  => '10% Summer Store Promo',
            'scope' => 'general',
        ]);

        $this->assertTrue($discount->isActiveNow());
        $this->assertEquals(100.0, $discount->computeDiscount(1000.0));
    }

    /**
     * Test discount duration boundaries: active vs upcoming vs expired.
     */
    public function test_discount_duration_status_boundaries(): void
    {
        // Upcoming promo
        $upcoming = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'general',
            'name'           => 'Black Friday Laundry Promo',
            'discount_type'  => 'percentage',
            'discount_value' => 25.00,
            'starts_at'      => Carbon::now()->addDays(3),
            'ends_at'        => Carbon::now()->addDays(6),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $this->assertTrue($upcoming->isUpcoming());
        $this->assertFalse($upcoming->isActiveNow());
        $this->assertEquals('upcoming', $upcoming->status);

        // Expired promo
        $expired = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'general',
            'name'           => 'Last Month Flash Sale',
            'discount_type'  => 'fixed',
            'discount_value' => 500.00,
            'starts_at'      => Carbon::now()->subDays(10),
            'ends_at'        => Carbon::now()->subDay(),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($expired->isActiveNow());
        $this->assertEquals('expired', $expired->status);
    }

    /**
     * Test LaundryDiscountService resolves active service promo pricing.
     */
    public function test_laundry_discount_service_resolves_service_discount(): void
    {
        // 20% off on suit (original price 3000)
        $discount = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'service',
            'service_id'     => $this->suitService->id,
            'name'           => 'Suit 20% Off Promo',
            'discount_type'  => 'percentage',
            'discount_value' => 20.00,
            'starts_at'      => Carbon::now()->subHour(),
            'ends_at'        => Carbon::now()->addDays(2),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $info = LaundryDiscountService::resolveServiceDiscount($this->suitService);

        $this->assertTrue($info['has_discount']);
        $this->assertEquals(3000.0, $info['original_price']);
        $this->assertEquals(2400.0, $info['discounted_price']);
        $this->assertEquals(600.0, $info['discount_amount']);
        $this->assertEquals($discount->id, $info['discount_id']);

        // Check service without discount (duvet)
        $noDiscountInfo = LaundryDiscountService::resolveServiceDiscount($this->duvetService);
        $this->assertFalse($noDiscountInfo['has_discount']);
        $this->assertEquals(5000.0, $noDiscountInfo['original_price']);
        $this->assertEquals(5000.0, $noDiscountInfo['discounted_price']);
        $this->assertEquals(0.0, $noDiscountInfo['discount_amount']);
    }

    /**
     * Test order placement calculates line service discounts and order general discount cleanly.
     */
    public function test_order_creation_with_service_and_general_discounts(): void
    {
        // 1. Service Promo: ₦500 flat discount off Duvet (original 5000 -> 4500)
        $servicePromo = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'service',
            'service_id'     => $this->duvetService->id,
            'name'           => '₦500 Off Duvet Wash',
            'discount_type'  => 'fixed',
            'discount_value' => 500.00,
            'starts_at'      => Carbon::now()->subHour(),
            'ends_at'        => Carbon::now()->addDays(3),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        // 2. General Promo: 10% off entire order
        $generalPromo = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'general',
            'name'           => '10% Storewide Promo',
            'discount_type'  => 'percentage',
            'discount_value' => 10.00,
            'starts_at'      => Carbon::now()->subHour(),
            'ends_at'        => Carbon::now()->addDays(3),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $customer = PosCustomer::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Promo Customer',
            'phone'     => '08055443322',
            'debt_bal'  => 0,
        ]);

        $orderService = app(LaundryOrderService::class);

        // Place order with 1 Duvet (discounted to 4500) and 1 Suit (standard 3000)
        // Subtotal = 4500 + 3000 = 7500
        // General promo 10% of 7500 = 750
        // Total = 7500 - 750 = 6750
        $order = $orderService->createOrder($this->branch, $this->user, [
            'customer_id'         => $customer->id,
            'laundry_discount_id' => $generalPromo->id,
            'items'               => [
                [
                    'service_id'          => $this->duvetService->id,
                    'service_name'        => $this->duvetService->name,
                    'pricing_type'        => 'per_piece',
                    'original_price'      => 5000.00,
                    'unit_price'          => 4500.00,
                    'discount_amount'     => 500.00,
                    'laundry_discount_id' => $servicePromo->id,
                    'quantity'            => 1,
                ],
                [
                    'service_id'          => $this->suitService->id,
                    'service_name'        => $this->suitService->name,
                    'pricing_type'        => 'per_piece',
                    'original_price'      => 3000.00,
                    'unit_price'          => 3000.00,
                    'discount_amount'     => 0,
                    'laundry_discount_id' => null,
                    'quantity'            => 1,
                ],
            ],
            'deposit_amount' => 0,
        ]);

        $this->assertEquals(7500.00, (float) $order->subtotal);
        $this->assertEquals(750.00, (float) $order->discount_amount);
        $this->assertEquals($generalPromo->id, $order->laundry_discount_id);
        $this->assertEquals(6750.00, (float) $order->total);

        // Check duvet line items
        $duvetLine = $order->items->firstWhere('service_id', $this->duvetService->id);
        $this->assertEquals(5000.00, (float) $duvetLine->original_price);
        $this->assertEquals(4500.00, (float) $duvetLine->unit_price);
        $this->assertEquals(500.00, (float) $duvetLine->discount_amount);
        $this->assertEquals($servicePromo->id, $duvetLine->laundry_discount_id);
    }

    /**
     * Test toggling active state on discounts.
     */
    public function test_discount_toggle_and_deactivate(): void
    {
        $discount = LaundryDiscount::create([
            'branch_id'      => $this->branch->id,
            'scope'          => 'general',
            'name'           => 'Toggle Test Discount',
            'discount_type'  => 'fixed',
            'discount_value' => 200.00,
            'starts_at'      => Carbon::now()->subDay(),
            'ends_at'        => Carbon::now()->addDays(5),
            'is_active'      => true,
            'created_by'     => $this->user->id,
        ]);

        $this->assertTrue($discount->is_active);

        $discount->update(['is_active' => false]);
        $discount->refresh();

        $this->assertFalse($discount->is_active);
        $this->assertFalse($discount->isActiveNow());
        $this->assertEquals('inactive', $discount->status);
    }
}
