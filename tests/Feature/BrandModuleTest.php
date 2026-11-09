<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalItem;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrandModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $user;
    protected User $superAdmin;
    protected PricingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->plan = PricingPlan::firstOrCreate(
            ['name' => 'Brand Test Enterprise Plan'],
            [
                'first_year_price' => 'Custom',
                'renewal_price'    => 'Custom',
                'branches_limit'   => 'Unlimited',
                'users_limit'      => 'Unlimited',
                'ai_credits'       => '1000',
                'is_active'        => true,
            ]
        );

        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->plan->id],
            [
                'max_branches'                  => null,
                'max_users_per_branch'          => null,
                'feature_multi_branch'          => true,
                'feature_ecommerce_integration' => true,
                'feature_inventory_import'      => true,
            ]
        );

        $this->org = Organization::create([
            'name'   => 'Brand Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $this->org->id,
            'pricing_plan_id' => $this->plan->id,
            'status'          => 'active',
            'started_at'      => now()->subDays(5),
            'renewal_date'    => now()->addYear(),
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Brand Test Branch',
            'slug'            => 'brand-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        setPermissionsTeamId($this->branch->id);
        $perm = Permission::firstOrCreate(['name' => 'canManageItems', 'guard_name' => 'web']);
        $role = Role::firstOrCreate([
            'name'       => 'manager',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $role->givePermissionTo($perm);
        $this->user->assignRole($role);

        // SuperAdmin user
        $this->superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);
    }

    public function test_can_list_brands(): void
    {
        $this->actingAs($this->user);

        Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Nike',
            'slug'      => 'nike-' . uniqid(),
            'is_active' => true,
        ]);

        $response = $this->get(route('pos.brands.index', ['branch' => $this->branch->slug]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Brands/Index')->has('brands.data'));
    }

    public function test_can_create_brand(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('pos.brands.store', ['branch' => $this->branch->slug]), [
            'name'        => 'Adidas',
            'description' => 'Sportswear brand',
            'is_active'   => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('brands', [
            'branch_id' => $this->branch->id,
            'name'      => 'Adidas',
            'slug'      => 'adidas',
        ]);
    }

    public function test_can_update_brand(): void
    {
        $this->actingAs($this->user);

        $brand = Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Puma Old',
            'slug'      => 'puma-old',
            'is_active' => true,
        ]);

        $response = $this->put(route('pos.brands.update', ['branch' => $this->branch->slug, 'brand' => $brand->id]), [
            'name'        => 'Puma New',
            'description' => 'Updated puma description',
            'is_active'   => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('brands', [
            'id'   => $brand->id,
            'name' => 'Puma New',
        ]);
    }

    public function test_can_delete_brand_and_item_brand_is_nulled(): void
    {
        $this->actingAs($this->user);

        $brand = Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Reebok',
            'slug'      => 'reebok-' . uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Shoes ' . uniqid(),
            'slug'      => 'shoes-' . uniqid(),
        ]);

        $item = Item::create([
            'branch_id'       => $this->branch->id,
            'category_id'     => $category->id,
            'brand_id'        => $brand->id,
            'item_name'       => 'Reebok Classic Sneaker',
            'buy_price'       => 5000,
            'price'           => 8000,
            'wholesale_price' => 7000,
            'front_store_qty' => 10,
        ]);

        $this->assertEquals($brand->id, $item->fresh()->brand_id);

        $response = $this->delete(route('pos.brands.destroy', ['branch' => $this->branch->slug, 'brand' => $brand->id]));
        $response->assertRedirect();

        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        $this->assertNull($item->fresh()->brand_id);
    }

    public function test_can_assign_brand_to_item_on_store_and_update(): void
    {
        $this->actingAs($this->user);

        $brand = Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Apple',
            'slug'      => 'apple-' . uniqid(),
            'is_active' => true,
        ]);

        $category = Category::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Gadgets ' . uniqid(),
            'slug'      => 'gadgets-' . uniqid(),
        ]);

        // Create Item with brand
        $response = $this->post(route('pos.items.store', ['branch' => $this->branch->slug]), [
            'item_name'       => 'iPhone 15',
            'category_id'     => $category->id,
            'brand_id'        => $brand->id,
            'buy_price'       => 500000,
            'price'           => 650000,
            'wholesale_price' => 600000,
            'qty'             => 5,
        ]);

        $response->assertRedirect();
        $item = Item::where('item_name', 'iPhone 15')->where('branch_id', $this->branch->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals($brand->id, $item->brand_id);

        // Update brand
        $brand2 = Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Apple Certified',
            'slug'      => 'apple-certified-' . uniqid(),
            'is_active' => true,
        ]);

        $responseUpdate = $this->put(route('pos.items.update', ['branch' => $this->branch->slug, 'item' => $item->id]), [
            'item_name'       => 'iPhone 15 Updated',
            'category_id'     => $category->id,
            'brand_id'        => $brand2->id,
            'buy_price'       => 500000,
            'price'           => 650000,
            'wholesale_price' => 600000,
            'qty'             => 5,
        ]);

        $responseUpdate->assertRedirect();
        $this->assertEquals($brand2->id, $item->fresh()->brand_id);
    }

    public function test_bulk_action_update_brand(): void
    {
        $this->actingAs($this->user);

        $category = Category::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Computers ' . uniqid(),
            'slug'      => 'comp-' . uniqid(),
        ]);

        $item1 = Item::create([
            'branch_id'       => $this->branch->id,
            'category_id'     => $category->id,
            'item_name'       => 'Item One',
            'buy_price'       => 100,
            'price'           => 200,
            'wholesale_price' => 150,
            'front_store_qty' => 5,
        ]);

        $item2 = Item::create([
            'branch_id'       => $this->branch->id,
            'category_id'     => $category->id,
            'item_name'       => 'Item Two',
            'buy_price'       => 100,
            'price'           => 200,
            'wholesale_price' => 150,
            'front_store_qty' => 5,
        ]);

        $brand = Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Dell',
            'slug'      => 'dell-' . uniqid(),
            'is_active' => true,
        ]);

        $response = $this->post(route('pos.items.bulk-action', ['branch' => $this->branch->slug]), [
            'action'   => 'update_brand',
            'item_ids' => [$item1->id, $item2->id],
            'brand_id' => $brand->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($brand->id, $item1->fresh()->brand_id);
        $this->assertEquals($brand->id, $item2->fresh()->brand_id);
    }

    public function test_import_native_csv_with_brand(): void
    {
        $this->actingAs($this->user);

        $csvContent = "item_name,category,brand,cost_price,selling_price,stock_qty\n"
                    . "Galaxy S24,Phones,Samsung,400000,550000,8\n";

        $file = UploadedFile::fake()->createWithContent('items_with_brand.csv', $csvContent);

        $response = $this->post(route('pos.items.import', ['branch' => $this->branch->slug]), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('brands', [
            'branch_id' => $this->branch->id,
            'name'      => 'Samsung',
        ]);

        $samsungBrand = Brand::where('branch_id', $this->branch->id)->where('name', 'Samsung')->first();
        $this->assertNotNull($samsungBrand);

        $this->assertDatabaseHas('items', [
            'branch_id' => $this->branch->id,
            'item_name' => 'Galaxy S24',
            'brand_id'  => $samsungBrand->id,
        ]);
    }

    public function test_commerce_api_brands_and_catalog(): void
    {
        $integration = CommerceIntegration::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'name'            => 'Test Integration',
            'platform'        => 'custom',
            'status'          => 'active',
        ]);
        $plainToken = $integration->createToken('test-token')->plainTextToken;

        $brand = Brand::create([
            'branch_id'   => $this->branch->id,
            'name'        => 'Sony',
            'slug'        => 'sony-' . uniqid(),
            'description' => 'Sony Electronics',
            'is_active'   => true,
        ]);

        $category = Category::create([
            'branch_id' => $this->branch->id,
            'name'      => 'Audio ' . uniqid(),
            'slug'      => 'audio-' . uniqid(),
        ]);

        $item = Item::create([
            'branch_id'       => $this->branch->id,
            'category_id'     => $category->id,
            'brand_id'        => $brand->id,
            'item_name'       => 'Sony WH-1000XM5',
            'buy_price'       => 150000,
            'price'           => 200000,
            'wholesale_price' => 180000,
            'front_store_qty' => 12,
        ]);

        // Test GET /api/commerce/v1/brands
        $brandsResponse = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/commerce/v1/brands');

        $brandsResponse->assertStatus(200);
        $brandsResponse->assertJsonStructure([
            'data' => [
                '*' => ['id', 'pos_brand_id', 'name', 'slug', 'description', 'logo_url', 'is_active'],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
        $brandsResponse->assertJsonFragment(['name' => 'Sony']);

        // Test GET /api/commerce/v1/catalog
        $catalogResponse = $this->withHeader('Authorization', 'Bearer ' . $plainToken)
            ->getJson('/api/commerce/v1/catalog');

        $catalogResponse->assertStatus(200);
        $catalogResponse->assertJsonFragment([
            'brand_id'   => $brand->id,
            'brand_name' => 'Sony',
        ]);
    }

    public function test_superadmin_global_item_crud_and_import_with_brand(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Create GlobalItem with brand_hint
        $response = $this->post(route('superadmin.global-items.store'), [
            'item_name'        => 'Nescafe Classic 200g',
            'barcode_number'   => '7613035987654',
            'buy_price'        => 2200,
            'price'            => 3000,
            'wholesale_price'  => 2800,
            'unit_label'       => 'Jar',
            'category_hint'    => 'Coffee & Beverages',
            'brand_hint'       => 'Nescafe',
            'item_description' => 'Rich blend coffee',
        ]);

        $response->assertRedirect(route('superadmin.global-items.index'));

        $this->assertDatabaseHas('global_items', [
            'item_name'     => 'Nescafe Classic 200g',
            'brand_hint'    => 'Nescafe',
            'category_hint' => 'Coffee & Beverages',
        ]);

        $globalItem = GlobalItem::where('item_name', 'Nescafe Classic 200g')->first();
        $this->assertNotNull($globalItem);

        // 2. Import single global item to branch
        $importResponse = $this->post(route('superadmin.global-items.import', [
            'globalItem' => $globalItem->id,
            'branch'     => $this->branch->slug,
        ]));

        $importResponse->assertRedirect();

        // Brand should be auto-created in branch
        $this->assertDatabaseHas('brands', [
            'branch_id' => $this->branch->id,
            'name'      => 'Nescafe',
        ]);

        $nescafeBrand = Brand::where('branch_id', $this->branch->id)->where('name', 'Nescafe')->first();
        $this->assertNotNull($nescafeBrand);

        // Branch item should be created and linked to the brand
        $this->assertDatabaseHas('items', [
            'branch_id' => $this->branch->id,
            'item_name' => 'Nescafe Classic 200g',
            'brand_id'  => $nescafeBrand->id,
        ]);
    }

    public function test_superadmin_branch_batch_import_and_all_import_with_brand(): void
    {
        $this->actingAs($this->superAdmin);

        $gItem1 = GlobalItem::create([
            'item_name'      => 'Milo 500g Tin',
            'barcode_number' => '1234567890123',
            'buy_price'      => 1500,
            'price'          => 2000,
            'category_hint'  => 'Beverages',
            'brand_hint'     => 'Milo',
        ]);

        $gItem2 = GlobalItem::create([
            'item_name'      => 'Bournvita 500g Refill',
            'barcode_number' => '1234567890124',
            'buy_price'      => 1400,
            'price'          => 1900,
            'category_hint'  => 'Beverages',
            'brand_hint'     => 'Cadbury',
        ]);

        // Batch import to branch
        $response = $this->post(route('superadmin.branches.items.import-batch', ['branch' => $this->branch->slug]), [
            'global_item_ids' => [$gItem1->id, $gItem2->id],
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('brands', [
            'branch_id' => $this->branch->id,
            'name'      => 'Milo',
        ]);

        $this->assertDatabaseHas('brands', [
            'branch_id' => $this->branch->id,
            'name'      => 'Cadbury',
        ]);

        $miloBrand = Brand::where('branch_id', $this->branch->id)->where('name', 'Milo')->first();
        $cadburyBrand = Brand::where('branch_id', $this->branch->id)->where('name', 'Cadbury')->first();

        $this->assertDatabaseHas('items', [
            'branch_id' => $this->branch->id,
            'item_name' => 'Milo 500g Tin',
            'brand_id'  => $miloBrand->id,
        ]);

        $this->assertDatabaseHas('items', [
            'branch_id' => $this->branch->id,
            'item_name' => 'Bournvita 500g Refill',
            'brand_id'  => $cadburyBrand->id,
        ]);
    }

    public function test_superadmin_branch_export_items_includes_brand(): void
    {
        $this->actingAs($this->superAdmin);

        $brand = Brand::create([
            'branch_id' => $this->branch->id,
            'name'      => 'HP',
            'slug'      => 'hp-' . uniqid(),
            'is_active' => true,
        ]);

        Item::create([
            'branch_id'       => $this->branch->id,
            'brand_id'        => $brand->id,
            'item_name'       => 'HP EliteBook 840',
            'buy_price'       => 300000,
            'price'           => 400000,
            'wholesale_price' => 380000,
            'front_store_qty' => 3,
        ]);

        $response = $this->get(route('superadmin.branches.export-items', [
            'branch' => $this->branch->slug,
            'format' => 'csv',
        ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('Brand', $content);
        $this->assertStringContainsString('HP', $content);
        $this->assertStringContainsString('HP EliteBook 840', $content);
    }
}
