<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\ItemUnit;
use App\Models\Organization;
use App\Models\PosSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicBranchCatalogTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branchA;
    protected Branch $branchB;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'                => 'Catalog Test Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $this->branchA = Branch::create([
            'organization_id'        => $this->org->id,
            'name'                   => 'Branch Alpha ' . uniqid(),
            'slug'                   => 'alpha-' . uniqid(),
            'is_active'              => true,
            'public_catalog_enabled' => false,
            'address'                => '123 Market St, Lagos',
            'phone'                  => '08012345678',
        ]);
        PosSettings::create(['branch_id' => $this->branchA->id]);

        $this->branchB = Branch::create([
            'organization_id'        => $this->org->id,
            'name'                   => 'Branch Beta ' . uniqid(),
            'slug'                   => 'beta-' . uniqid(),
            'is_active'              => true,
            'public_catalog_enabled' => true,
        ]);
        PosSettings::create(['branch_id' => $this->branchB->id]);

        $this->user = User::create([
            'name'            => 'Admin User',
            'username'        => 'admin_' . uniqid(),
            'email'           => 'admin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branchA->id,
            'is_active'       => true,
        ]);

        $this->branchA->ensureDefaultRoles();
        setPermissionsTeamId($this->branchA->id);
        $this->user->assignRole('branch-admin');
    }

    public function test_catalog_is_unavailable_by_default_when_disabled(): void
    {
        // Branch A has public_catalog_enabled = false
        $response = $this->get(route('catalog.show', ['slug' => $this->branchA->slug]));

        $response->assertStatus(404);
        $response->assertSee('Catalog Not Available');
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_unknown_slug_and_disabled_branch_return_same_generic_unavailable_page(): void
    {
        // 1. Unknown slug
        $unknownResponse = $this->get(route('catalog.show', ['slug' => 'non-existent-branch-slug']));
        $unknownResponse->assertStatus(404);
        $unknownResponse->assertSee('Catalog Not Available');

        // 2. Disabled branch
        $disabledBranch = Branch::create([
            'organization_id'        => $this->org->id,
            'name'                   => 'Inactive Branch',
            'slug'                   => 'inactive-' . uniqid(),
            'is_active'              => false,
            'public_catalog_enabled' => true,
        ]);
        $disabledResponse = $this->get(route('catalog.show', ['slug' => $disabledBranch->slug]));
        $disabledResponse->assertStatus(404);
        $disabledResponse->assertSee('Catalog Not Available');
    }

    public function test_catalog_is_accessible_when_enabled(): void
    {
        $this->branchA->update(['public_catalog_enabled' => true]);

        $category = Category::create([
            'name'      => 'Beverages',
            'slug'      => 'beverages-' . uniqid(),
            'branch_id' => $this->branchA->id,
        ]);

        Item::create([
            'branch_id'       => $this->branchA->id,
            'category_id'     => $category->id,
            'item_name'       => 'Fresh Orange Juice',
            'buy_price'       => 500,
            'price'           => 800,
            'wholesale_price' => 700,
            'front_store_qty' => 20,
            'back_store_qty'  => 50,
            'unit_label'      => 'Bottle',
        ]);

        $response = $this->get(route('catalog.show', ['slug' => $this->branchA->slug]));

        $response->assertStatus(200);
        $response->assertSee($this->branchA->name);
        $response->assertSee('Fresh Orange Juice');
        $response->assertSee('₦800.00');
        $response->assertSee('Beverages');
        $response->assertSee('123 Market St, Lagos');
        $response->assertSee('08012345678');
        $response->assertSee('Prices and availability may change. Please confirm at the shop.');
    }

    public function test_security_whitelist_never_exposes_internal_data(): void
    {
        $this->branchA->update(['public_catalog_enabled' => true]);

        $item = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Confidential Widget XYZ',
            'barcode_number'  => '9876543210987',
            'buy_price'       => 1234.56,
            'price'           => 2500.00,
            'wholesale_price' => 1800.00,
            'front_store_qty' => 42,
            'back_store_qty'  => 999,
            'unit_label'      => 'Box',
        ]);

        $response = $this->get(route('catalog.show', ['slug' => $this->branchA->slug]));
        $content = $response->getContent();

        // Selling price should appear
        $response->assertSee('₦2,500.00');

        // Whitelisted protection: cost price, wholesale price, barcode, and stock quantities MUST NOT appear
        $this->assertStringNotContainsString('1234.56', $content, 'Cost price leaked in HTML');
        $this->assertStringNotContainsString('1800.00', $content, 'Wholesale price leaked in HTML');
        $this->assertStringNotContainsString('9876543210987', $content, 'Barcode number leaked in HTML');
        $this->assertStringNotContainsString('"front_store_qty"', $content, 'Front store qty key leaked');
        $this->assertStringNotContainsString('"back_store_qty"', $content, 'Back store qty key leaked');
        $this->assertStringNotContainsString('999', $content, 'Back store qty number leaked');
    }

    public function test_item_eligibility_and_variant_thresholds(): void
    {
        $this->branchA->update(['public_catalog_enabled' => true]);

        // 1. Eligible item with variants
        $eligibleItem = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Paracetamol 500mg',
            'price'           => 100,
            'buy_price'       => 50,
            'front_store_qty' => 6, // 6 base units in front store
            'unit_label'      => 'Sachet',
        ]);

        // Variant 1: Strip of 2 (requires 2 units -> eligible since 6 >= 2)
        ItemUnit::create([
            'item_id'                => $eligibleItem->id,
            'branch_id'              => $this->branchA->id,
            'unit_name'              => 'Strip of 2',
            'quantity_in_base_units' => 2,
            'selling_price'          => 190,
            'status'                 => 'active',
            'sort_order'             => 1,
        ]);

        // Variant 2: Pack of 10 (requires 10 units -> NOT eligible since 6 < 10)
        ItemUnit::create([
            'item_id'                => $eligibleItem->id,
            'branch_id'              => $this->branchA->id,
            'unit_name'              => 'Pack of 10',
            'quantity_in_base_units' => 10,
            'selling_price'          => 900,
            'status'                 => 'active',
            'sort_order'             => 2,
        ]);

        // 2. Out of stock item (front_store_qty = 0) -> must NOT appear
        Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Out of Stock Soda',
            'price'           => 300,
            'buy_price'       => 150,
            'front_store_qty' => 0,
            'back_store_qty'  => 50, // Back store stock does NOT make it eligible
        ]);

        // 3. Hidden item (hide_from_public_catalog = true) -> must NOT appear
        Item::create([
            'branch_id'                => $this->branchA->id,
            'item_name'                => 'Secret Prescription Compound',
            'price'                    => 5000,
            'buy_price'                => 2000,
            'front_store_qty'          => 15,
            'hide_from_public_catalog' => true,
        ]);

        // 4. Expired item -> must NOT appear
        Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Expired Milk 2024',
            'price'           => 1200,
            'buy_price'       => 800,
            'front_store_qty' => 10,
            'expiry_date'     => now()->subDay()->toDateString(),
        ]);

        $response = $this->get(route('catalog.show', ['slug' => $this->branchA->slug]));

        $response->assertSee('Paracetamol 500mg');
        $response->assertSee('Strip of 2');
        $response->assertSee('₦190.00');

        // Pack of 10 must NOT appear because 6 units does not cover 10
        $response->assertDontSee('Pack of 10');

        // Excluded items must NOT appear
        $response->assertDontSee('Out of Stock Soda');
        $response->assertDontSee('Secret Prescription Compound');
        $response->assertDontSee('Expired Milk 2024');
    }

    public function test_tenant_isolation_between_branches(): void
    {
        $this->branchA->update(['public_catalog_enabled' => true]);
        $this->branchB->update(['public_catalog_enabled' => true]);

        Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Branch Alpha Unique Product',
            'price'           => 1500,
            'buy_price'       => 1000,
            'front_store_qty' => 10,
        ]);

        Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Branch Beta Unique Product',
            'price'           => 2500,
            'buy_price'       => 2000,
            'front_store_qty' => 10,
        ]);

        // Branch A's catalog
        $responseA = $this->get(route('catalog.show', ['slug' => $this->branchA->slug]));
        $responseA->assertSee('Branch Alpha Unique Product');
        $responseA->assertDontSee('Branch Beta Unique Product');

        // Branch B's catalog
        $responseB = $this->get(route('catalog.show', ['slug' => $this->branchB->slug]));
        $responseB->assertSee('Branch Beta Unique Product');
        $responseB->assertDontSee('Branch Alpha Unique Product');
    }

    public function test_search_and_sorting(): void
    {
        $this->branchA->update(['public_catalog_enabled' => true]);

        Item::create(['branch_id' => $this->branchA->id, 'item_name' => 'Apple iPhone 15', 'price' => 1000, 'buy_price' => 800, 'front_store_qty' => 5]);
        Item::create(['branch_id' => $this->branchA->id, 'item_name' => 'Samsung Galaxy S24', 'price' => 900, 'buy_price' => 700, 'front_store_qty' => 5]);
        Item::create(['branch_id' => $this->branchA->id, 'item_name' => 'Apple MacBook Pro', 'price' => 2500, 'buy_price' => 2000, 'front_store_qty' => 3]);

        // Search for 'apple'
        $searchResponse = $this->get(route('catalog.show', ['slug' => $this->branchA->slug, 'search' => 'apple']));
        $searchResponse->assertSee('Apple iPhone 15');
        $searchResponse->assertSee('Apple MacBook Pro');
        $searchResponse->assertDontSee('Samsung Galaxy S24');

        // Sort by price descending
        $sortResponse = $this->get(route('catalog.show', ['slug' => $this->branchA->slug, 'sort' => 'price_desc']));
        $content = $sortResponse->getContent();
        $posMacBook = strpos($content, 'Apple MacBook Pro');
        $posIphone = strpos($content, 'Apple iPhone 15');
        $posSamsung = strpos($content, 'Samsung Galaxy S24');

        $this->assertTrue($posMacBook < $posIphone, 'MacBook (2500) should appear before iPhone (1000)');
        $this->assertTrue($posIphone < $posSamsung, 'iPhone (1000) should appear before Samsung (900)');
    }

    public function test_branch_settings_controller_can_toggle_public_catalog(): void
    {
        $this->actingAs($this->user);

        // Branch Admin edits settings
        $response = $this->put(route('pos.settings.update', ['branch' => $this->branchA->slug]), [
            'business_name'           => 'Updated Branch Alpha',
            'business_address'        => '456 New Road',
            'business_contact_number' => '08099998888',
            'sell_interface'          => 'classic',
            'quick_access_mode'       => 'top_sellers',
            'out_of_stock'            => 10,
            'wholesale_profit_percent'=> 10,
            'consumer_profit_percent' => 15,
            'business_sector'         => 'commerce',
            'receipt_paper_size'      => '80mm',
            'printer_type'            => 'thermal_80mm',
            'printer_connection'      => 'kiosk_direct',
            'public_catalog_enabled'  => true,
        ]);

        $response->assertSessionHasNoErrors();
        $this->branchA->refresh();
        $this->assertTrue((bool) $this->branchA->public_catalog_enabled);
    }

    public function test_item_controller_can_save_hide_from_public_catalog(): void
    {
        $this->actingAs($this->user);

        $category = Category::create([
            'name'      => 'Pharma',
            'slug'      => 'pharma-' . uniqid(),
            'branch_id' => $this->branchA->id,
        ]);

        // Create item with hide_from_public_catalog = true
        $storeResponse = $this->post(route('pos.items.store', ['branch' => $this->branchA->slug]), [
            'item_name'                => 'Classified Test Medicine',
            'category_id'              => $category->id,
            'buy_price'                => 100,
            'price'                    => 200,
            'front_store_qty'          => 10,
            'hide_from_public_catalog' => true,
        ]);

        $storeResponse->assertSessionHasNoErrors();

        $item = Item::where('branch_id', $this->branchA->id)
            ->where('item_name', 'Classified Test Medicine')
            ->first();

        $this->assertNotNull($item);
        $this->assertTrue((bool) $item->hide_from_public_catalog);
    }

    public function test_catalog_renders_active_system_color_theme(): void
    {
        \App\Models\SystemSetting::set('primary_color_theme', 'rose');

        $response = $this->get(route('catalog.show', ['slug' => $this->branchB->slug]));

        $response->assertStatus(200);
        // Rose theme uses #f43f5e
        $response->assertSee('#f43f5e');
        $response->assertSee('--color-theme: #f43f5e', false);
    }

    public function test_catalog_adapts_to_custom_theme(): void
    {
        \App\Models\SystemSetting::set('primary_color_theme', 'custom');
        \App\Models\SystemSetting::set('custom_primary_hex', '#FF5733');
        \App\Models\SystemSetting::set('custom_secondary_hex', '#33FF57');

        $response = $this->get(route('catalog.show', ['slug' => $this->branchB->slug]));

        $response->assertStatus(200);
        $response->assertSee('#FF5733', false);
        $response->assertSee('#33FF57', false);
        $response->assertSee('--color-theme: #FF5733', false);
    }
}

