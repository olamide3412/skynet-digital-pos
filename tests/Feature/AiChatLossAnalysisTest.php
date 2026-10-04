<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\AI\Services\ChatQueryTranslationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiChatLossAnalysisTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $plan = PricingPlan::firstOrCreate(
            ['name' => 'Starter'],
            [
                'first_year_price' => '₦150,000',
                'renewal_price'    => '₦100,000 / year',
                'branches_limit'   => '1 Branch',
                'users_limit'      => 'Up to 3 Users',
                'ai_credits'       => '25 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 1,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $plan->id],
            [
                'feature_ai_chat_assistant' => true,
                'ai_credits_monthly'        => 100,
            ]
        );

        $this->org = Organization::create([
            'name'   => 'Loss Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => now()->subMonths(2)->toDateString(),
            'renewal_date'    => now()->addMonths(10)->toDateString(),
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Loss Test Branch ' . uniqid(),
            'slug'            => 'loss-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->adminUser = User::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Branch Admin ' . uniqid(),
            'username'        => 'admin_' . uniqid(),
            'email'           => 'admin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
    }

    public function test_query_detects_loss_intent_for_various_user_phrasings(): void
    {
        $phrasings = [
            'why am on lost and which item cause that',
            'why am i on loss',
            'did discount given make me loss on profit',
            'check if high buy cost more that the selling cost caused loss',
            'why is my profit negative on sales',
        ];

        foreach ($phrasings as $phrase) {
            $result = ChatQueryTranslationService::process($phrase, $this->branch, $this->adminUser);
            $this->assertEquals(
                'loss_analysis_diagnosis',
                $result['intent'],
                "Failed to detect loss_analysis_diagnosis for phrasing: '{$phrase}'"
            );
        }
    }

    public function test_loss_analysis_identifies_catalog_items_with_buy_cost_higher_than_selling_price(): void
    {
        // 1. Create properly priced item
        Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Healthy Margin Widget',
            'buy_price'       => 2000,
            'price'           => 3500,
            'front_store_qty' => 10,
            'back_store_qty'  => 5,
        ]);

        // 2. Create inverted item: buy_price (5,000) > price (3,500)
        Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Inverted Cost Gadget',
            'buy_price'       => 5000,
            'price'           => 3500,
            'front_store_qty' => 10,
            'back_store_qty'  => 10, // 20 units total * 1500 loss = 30,000 potential loss
        ]);

        $result = ChatQueryTranslationService::process('why am on lost and which item cause that', $this->branch, $this->adminUser);

        $this->assertEquals('loss_analysis_diagnosis', $result['intent']);
        $data = $result['structured_data'];

        $this->assertTrue($data['has_loss_issues']);
        $this->assertEquals(1, $data['catalog_inversions']['count']);
        $this->assertEquals(30000.0, (float)$data['catalog_inversions']['total_potential_loss']);
        $this->assertEquals('Inverted Cost Gadget', $data['catalog_inversions']['items'][0]['item_name']);
        $this->assertEquals(1500.0, (float)$data['catalog_inversions']['items'][0]['retail_deficit']);

        // Check AI or rule-based response contains item name and pricing loss
        $this->assertStringContainsString('Inverted Cost Gadget', $result['ai_response']);
        $this->assertTrue(
            str_contains($result['ai_response'], '5,000') ||
            str_contains($result['ai_response'], '30,000')
        );
    }

    public function test_loss_analysis_identifies_sales_sold_at_a_loss_due_to_excessive_discounts(): void
    {
        // Create an item with positive margin
        $item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Discount Victim Item',
            'buy_price'       => 8000,
            'price'           => 10000, // ₦2,000 positive margin
            'front_store_qty' => 20,
        ]);

        // Sale with excessive discount:
        // amount_cost = 10,000, discount = 4,000 -> final_total = 6,000
        // cost = 8,000 -> profit_made = -2,000 (Loss!)
        $sale = Sale::create([
            'branch_id'         => $this->branch->id,
            'user_id'           => $this->adminUser->id,
            'receipt_id'        => 'RC-LOSS-' . rand(1000, 9999),
            'items_order_count' => 1,
            'amount_cost'       => 10000,
            'discount_amount'   => 4000,
            'final_total'       => 6000,
            'amount_paid'       => 6000,
            'profit_made'       => -2000,
            'payment_method'    => 'Cash',
        ]);

        SaleOrder::create([
            'sale_id'             => $sale->id,
            'item_id'             => $item->id,
            'item_name'           => $item->item_name,
            'selling_price'       => 10000,
            'total_selling_price' => 10000,
            'qty'                 => 1,
            'user_id'             => $this->adminUser->id,
            'sort_date'           => now(),
        ]);

        $result = ChatQueryTranslationService::process('did discount given make me loss on profit', $this->branch, $this->adminUser);

        $this->assertEquals('loss_analysis_diagnosis', $result['intent']);
        $data = $result['structured_data'];

        $this->assertGreaterThanOrEqual(1, $data['historical_loss_sales']['total_loss_sales']);
        $this->assertGreaterThanOrEqual(2000.0, $data['historical_loss_sales']['total_loss_amount']);

        // Check attribution
        $worstSales = $data['historical_loss_sales']['worst_loss_sales'];
        $matchingSale = collect($worstSales)->firstWhere('receipt_id', $sale->receipt_id);
        $this->assertNotNull($matchingSale);
        $this->assertEquals('excessive_discount', $matchingSale['cause_type']);
        $this->assertEquals(2000.0, (float)$matchingSale['net_loss']);
        $this->assertEquals(4000.0, (float)$matchingSale['discount']);

        // Check response text
        $this->assertStringContainsString($sale->receipt_id, $result['ai_response']);
        $this->assertStringContainsString('Excessive Discount', $result['ai_response']);
    }

    public function test_api_endpoint_returns_loss_analysis_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('pos.ai.chat.ask', ['branch' => $this->branch->slug]), [
                'question' => 'why am on lost and which item cause that',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'intent' => 'loss_analysis_diagnosis',
        ]);
        $this->assertNotEmpty($response->json('ai_response'));
        $this->assertArrayHasKey('catalog_inversions', $response->json('structured_data'));
        $this->assertArrayHasKey('historical_loss_sales', $response->json('structured_data'));
        $this->assertArrayHasKey('culprit_items', $response->json('structured_data'));
    }
}
