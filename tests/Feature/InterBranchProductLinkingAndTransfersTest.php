<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\InterBranchTransfer;
use App\Models\InterBranchTransferItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PosSettings;
use App\Models\PricingPlan;
use App\Models\ProductGroup;
use App\Models\ProductGroupItem;
use App\Models\Subscription;
use App\Models\User;
use App\Services\InterBranchTransferService;
use App\Services\ProductLinkingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InterBranchProductLinkingAndTransfersTest extends TestCase
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

        // Create organization
        $this->org = Organization::create([
            'name'                => 'Test Inter-Branch Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        // Create Branch A
        $this->branchA = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Branch Alpha ' . uniqid(),
            'slug'            => 'branch-alpha-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $this->branchA->id]);

        // Create Branch B
        $this->branchB = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Branch Beta ' . uniqid(),
            'slug'            => 'branch-beta-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $this->branchB->id]);

        // Create User
        $this->user = User::create([
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branchA->id,
            'name'            => 'Test Manager ' . uniqid(),
            'username'        => 'manager_' . uniqid(),
            'email'           => 'mgr_' . uniqid() . '@example.com',
            'password'        => bcrypt('secret'),
            'is_active'       => true,
        ]);
    }

    /**
     * Test 1: Product Group can be created and items linked; enforces 1 item per branch.
     */
    public function test_product_group_creation_and_unique_branch_constraint(): void
    {
        $itemA1 = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Milo 500g',
            'barcode_number'  => '12345678',
            'unit_label'      => 'tin',
            'buy_price'       => 100,
            'price'           => 150,
            'back_store_qty'  => 50,
            'front_store_qty' => 10,
        ]);

        $itemA2 = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Milo 500g Duplicate',
            'barcode_number'  => '12345678-DUP',
            'unit_label'      => 'tin',
            'buy_price'       => 100,
            'price'           => 150,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Milo Chocolate 500g',
            'barcode_number'  => '12345678',
            'unit_label'      => 'tin',
            'buy_price'       => 105,
            'price'           => 160,
        ]);

        // 1. Create group and link itemA1
        $group = ProductLinkingService::createGroup($this->org->id, 'Milo 500g Beverage', $this->user->id, $itemA1->id);
        $this->assertDatabaseHas('product_groups', ['id' => $group->id, 'name' => 'Milo 500g Beverage']);
        $this->assertDatabaseHas('product_group_items', ['product_group_id' => $group->id, 'item_id' => $itemA1->id]);

        // 2. Link itemB from Branch B (success)
        ProductLinkingService::linkItem($group->id, $this->branchB->id, $itemB->id, $this->user->id);
        $this->assertDatabaseHas('product_group_items', ['product_group_id' => $group->id, 'item_id' => $itemB->id]);

        // 3. Attempt to link itemA2 (from Branch A again) into the same group -> must fail (1 item per branch per group rule)
        $this->expectException(\RuntimeException::class);
        ProductLinkingService::linkItem($group->id, $this->branchA->id, $itemA2->id, $this->user->id);
    }

    /**
     * Test 2: Auto-suggest detects exact barcode matches across branches.
     */
    public function test_auto_suggest_detects_exact_barcode_match(): void
    {
        $barcode = 'BARCODE_' . uniqid();

        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Ovaltine 400g',
            'barcode_number'  => $barcode,
            'unit_label'      => 'jar',
            'buy_price'       => 80,
            'price'           => 120,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Ovaltine Malt 400g',
            'barcode_number'  => $barcode,
            'unit_label'      => 'jar',
            'buy_price'       => 85,
            'price'           => 125,
        ]);

        $suggestions = ProductLinkingService::autoSuggest($this->org->id, $this->branchA->id);

        $this->assertNotEmpty($suggestions['barcode_matches']);
        $match = collect($suggestions['barcode_matches'])->firstWhere('template_barcode', $barcode);
        $this->assertNotNull($match);
        $this->assertEquals($itemB->id, $match['target_item_id']);
        $this->assertEquals(100, $match['confidence']);
    }

    /**
     * Test 3: Bulk-confirm creates links and undo-batch reverts them cleanly.
     */
    public function test_bulk_confirm_and_undo_batch(): void
    {
        $barcode = 'BULK_' . uniqid();

        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Corn Flakes 500g',
            'barcode_number'  => $barcode,
            'unit_label'      => 'box',
            'buy_price'       => 200,
            'price'           => 300,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Corn Flakes 500g',
            'barcode_number'  => $barcode,
            'unit_label'      => 'box',
            'buy_price'       => 200,
            'price'           => 300,
        ]);

        $matches = [
            [
                'template_item_id' => $itemA->id,
                'target_item_id'   => $itemB->id,
                'target_branch_id' => $this->branchB->id,
                'match_type'       => 'barcode',
            ],
        ];

        // 1. Bulk confirm
        $result = ProductLinkingService::bulkConfirm($this->org->id, $matches, $this->user->id);
        $batchId = $result['batch_id'];
        $this->assertEquals(1, $result['linked_count']);
        $this->assertDatabaseHas('product_group_items', ['item_id' => $itemB->id]);

        // 2. Undo batch
        $unlinkedCount = ProductLinkingService::undoBatch($this->org->id, $batchId, $this->user->id);
        $this->assertEquals(1, $unlinkedCount);
        $this->assertDatabaseMissing('product_group_items', ['item_id' => $itemB->id]);
    }

    /**
     * Test 4: Copy to other branches copies catalog with prices and joins group.
     */
    public function test_copy_item_to_other_branches_with_prices(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Peak Powdered Milk 400g',
            'barcode_number'  => 'PEAK_' . uniqid(),
            'unit_label'      => 'tin',
            'buy_price'       => 150.00,
            'price'           => 220.00,
            'wholesale_price' => 190.00,
            'back_store_qty'  => 40,
            'front_store_qty' => 10,
        ]);

        $copyResult = ProductLinkingService::copyToBranches(
            $itemA->id,
            [$this->branchB->id],
            true, // include prices
            $this->user->id
        );

        $this->assertNotEmpty($copyResult['created_items']);
        $newItemId = $copyResult['created_items'][0]['item_id'];

        $newItem = Item::find($newItemId);
        $this->assertEquals($this->branchB->id, $newItem->branch_id);
        $this->assertEquals('Peak Powdered Milk 400g', $newItem->item_name);
        $this->assertEquals(150.00, (float) $newItem->buy_price);
        $this->assertEquals(220.00, (float) $newItem->price);
        $this->assertEquals(0, $newItem->front_store_qty);
        $this->assertEquals(0, $newItem->back_store_qty);

        // Both items should be in the same ProductGroup
        $this->assertDatabaseHas('product_group_items', [
            'product_group_id' => $copyResult['product_group_id'],
            'item_id'          => $itemA->id,
        ]);
        $this->assertDatabaseHas('product_group_items', [
            'product_group_id' => $copyResult['product_group_id'],
            'item_id'          => $newItem->id,
            'link_source'      => 'copy',
        ]);
    }

    /**
     * Test 4b: Copy item that has a Category creates/links matching category in target branch and allows safe subsequent copies.
     */
    public function test_copy_item_with_category_to_other_branches_and_subsequent_copy(): void
    {
        $catA = Category::create([
            'branch_id' => $this->branchA->id,
            'name'      => 'Beverages',
            'slug'      => 'beverages-a-' . uniqid(),
        ]);

        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'category_id'     => $catA->id,
            'item_name'       => 'Malta Guinness Can',
            'barcode_number'  => 'MALTA_' . uniqid(),
            'unit_label'      => 'can',
            'buy_price'       => 200.00,
            'price'           => 300.00,
            'wholesale_price' => 260.00,
            'back_store_qty'  => 50,
            'front_store_qty' => 15,
        ]);

        // First copy: should succeed and copy category
        $copyResult1 = ProductLinkingService::copyToBranches(
            $itemA->id,
            [$this->branchB->id],
            true,
            $this->user->id
        );

        $this->assertCount(1, $copyResult1['created_items']);
        $newItemId = $copyResult1['created_items'][0]['item_id'];
        $newItem = Item::find($newItemId);
        $this->assertNotNull($newItem);
        $this->assertEquals($this->branchB->id, $newItem->branch_id);
        $this->assertNotNull($newItem->category_id);

        $targetCat = Category::find($newItem->category_id);
        $this->assertNotNull($targetCat);
        $this->assertEquals($this->branchB->id, $targetCat->branch_id);
        $this->assertEquals('Beverages', $targetCat->name);

        // Second copy: target branch is already linked, so created_items should be empty without SQL error
        $copyResult2 = ProductLinkingService::copyToBranches(
            $itemA->id,
            [$this->branchB->id],
            true,
            $this->user->id
        );

        $this->assertCount(0, $copyResult2['created_items']);
    }

    /**
     * Test 5: Inter-Branch Transfer from Back Store and Front Store decrements originating stock.
     */
    public function test_transfer_creation_from_back_store_and_front_store(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Sugar 1kg',
            'unit_label'      => 'kg',
            'buy_price'       => 50,
            'price'           => 70,
            'back_store_qty'  => 100,
            'front_store_qty' => 30,
        ]);

        // Transfer 20 from back_store
        $transfer1 = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 20]],
            'back_store',
            'Test transfer from back store',
            $this->user->id
        );

        $itemA->refresh();
        $this->assertEquals(80, $itemA->back_store_qty);
        $this->assertEquals(30, $itemA->front_store_qty);
        $this->assertEquals('pending', $transfer1->status);

        // Transfer 10 from front_store
        $transfer2 = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 10]],
            'front_store',
            'Test transfer from front store',
            $this->user->id
        );

        $itemA->refresh();
        $this->assertEquals(80, $itemA->back_store_qty);
        $this->assertEquals(20, $itemA->front_store_qty);
        $this->assertEquals('pending', $transfer2->status);
    }

    /**
     * Test 6: Base unit mismatch blocks transfer from acceptance.
     */
    public function test_base_unit_mismatch_blocks_transfer(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Tissue Roll Single',
            'unit_label'      => 'roll',
            'buy_price'       => 20,
            'price'           => 30,
            'back_store_qty'  => 50,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Tissue Pack (Mismatched unit)',
            'unit_label'      => 'pack', // Mismatched unit!
            'buy_price'       => 200,
            'price'           => 300,
            'back_store_qty'  => 10,
        ]);

        // Link them in a group
        $group = ProductLinkingService::createGroup($this->org->id, 'Tissue Product', $this->user->id, $itemA->id);
        ProductLinkingService::linkItem($group->id, $this->branchB->id, $itemB->id, $this->user->id);

        // Dispatch transfer
        $transfer = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 5]],
            'back_store',
            null,
            $this->user->id
        );

        $this->assertEquals('blocked', $transfer->status);
        $this->assertStringContainsString('Base unit mismatch', $transfer->blocked_reason);

        // Accepting a blocked transfer must throw an exception
        $this->expectException(\RuntimeException::class);
        InterBranchTransferService::acceptTransfer(
            $transfer,
            [['line_id' => $transfer->items->first()->id, 'quantity_received' => 5]],
            'back_store',
            $this->user->id
        );
    }

    /**
     * Test 7: Sender can cancel pending/blocked transfer and stock returns to originating store.
     */
    public function test_sender_cancel_transfer_restores_stock(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Detergent 1kg',
            'unit_label'      => 'pack',
            'buy_price'       => 40,
            'price'           => 60,
            'front_store_qty' => 50,
        ]);

        $transfer = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 15]],
            'front_store',
            null,
            $this->user->id
        );

        $itemA->refresh();
        $this->assertEquals(35, $itemA->front_store_qty);

        // Cancel
        InterBranchTransferService::cancelTransfer($transfer, $this->user->id);

        $transfer->refresh();
        $this->assertEquals('cancelled', $transfer->status);

        $itemA->refresh();
        $this->assertEquals(50, $itemA->front_store_qty); // Fully restored!
    }

    /**
     * Test 8: Receiver accepts transfer with actual quantity and selects destination store location.
     */
    public function test_receiver_accept_transfer_with_shortfall_and_store_choice(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Biscuits Carton',
            'unit_label'      => 'ctn',
            'buy_price'       => 100,
            'price'           => 140,
            'back_store_qty'  => 50,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Biscuits Carton',
            'unit_label'      => 'ctn',
            'buy_price'       => 110, // Own cost price
            'price'           => 150,
            'back_store_qty'  => 5,
            'front_store_qty' => 2,
        ]);

        $group = ProductLinkingService::createGroup($this->org->id, 'Biscuits Group', $this->user->id, $itemA->id);
        ProductLinkingService::linkItem($group->id, $this->branchB->id, $itemB->id, $this->user->id);

        // Send 10 cartons from Branch A
        $transfer = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 10]],
            'back_store',
            null,
            $this->user->id
        );

        $line = $transfer->items->first();

        // Branch B accepts 9 cartons (1 shortfall) directly into Front Store
        InterBranchTransferService::acceptTransfer(
            $transfer,
            [['line_id' => $line->id, 'quantity_received' => 9]],
            'front_store', // Destination store
            $this->user->id
        );

        $transfer->refresh();
        $this->assertEquals('accepted', $transfer->status);
        $this->assertEquals('front_store', $transfer->to_location);

        $line->refresh();
        $this->assertEquals(9, $line->quantity_received);
        $this->assertEquals(1, $line->shortfall_quantity);
        $this->assertEquals('discrepancy', $line->status);

        $itemB->refresh();
        $this->assertEquals(11, $itemB->front_store_qty); // 2 + 9 = 11
        $this->assertEquals(5, $itemB->back_store_qty);   // unchanged
        $this->assertEquals(110, (float) $itemB->buy_price); // Kept own cost price!

        // Verify InventoryTransaction created on both ends
        $senderLog = InventoryTransaction::where('branch_id', $this->branchA->id)
            ->where('reference_id', $transfer->reference)
            ->first();
        $this->assertNotNull($senderLog);
        $this->assertEquals(-10, $senderLog->qty);
        $this->assertEquals(50, $senderLog->previous_qty);
        $this->assertEquals(40, $senderLog->new_qty);
        $this->assertStringContainsString('Shortfall: 1', $senderLog->notes);

        $receiverLog = InventoryTransaction::where('branch_id', $this->branchB->id)
            ->where('reference_id', $transfer->reference)
            ->first();
        $this->assertNotNull($receiverLog);
        $this->assertEquals(9, $receiverLog->qty);
        $this->assertEquals(2, $receiverLog->previous_qty);
        $this->assertEquals(11, $receiverLog->new_qty);
        $this->assertStringContainsString('Shortfall: 1', $receiverLog->notes);
    }

    /**
     * Test 9: Branch move is blocked if pending transfers exist, and unlinks only moved branch on move.
     */
    public function test_branch_move_guard_and_selective_unlinking(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Soap Bar',
            'unit_label'      => 'pcs',
            'buy_price'       => 10,
            'price'           => 15,
            'back_store_qty'  => 50,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Soap Bar',
            'unit_label'      => 'pcs',
            'buy_price'       => 10,
            'price'           => 15,
            'back_store_qty'  => 20,
        ]);

        $group = ProductLinkingService::createGroup($this->org->id, 'Soap Product', $this->user->id, $itemA->id);
        ProductLinkingService::linkItem($group->id, $this->branchB->id, $itemB->id, $this->user->id);

        // Pending transfer in transit
        $transfer = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 5]],
            'back_store',
            null,
            $this->user->id
        );

        $newOrg = Organization::create([
            'name'                => 'New Separate Org ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        // SuperAdmin attempts to move Branch A -> must fail because transfer is pending
        $superAdmin = User::create([
            'name'           => 'Super Admin ' . uniqid(),
            'username'       => 'sa_' . uniqid(),
            'email'          => 'sa_' . uniqid() . '@example.com',
            'password'       => bcrypt('secret'),
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(
            route('superadmin.branches.move-organization', $this->branchA->slug),
            ['organization_id' => $newOrg->id]
        );

        $response->assertSessionHas('error');
        $this->assertEquals($this->org->id, $this->branchA->fresh()->organization_id);

        // Cancel transfer so move can proceed
        InterBranchTransferService::cancelTransfer($transfer, $this->user->id);

        // Try move again -> should succeed
        $response2 = $this->actingAs($superAdmin)->post(
            route('superadmin.branches.move-organization', $this->branchA->slug),
            ['organization_id' => $newOrg->id]
        );

        $response2->assertSessionHas('success');
        $this->assertEquals($newOrg->id, $this->branchA->fresh()->organization_id);

        // Verify Branch A's link was deleted, but Branch B's product group item remains
        $this->assertDatabaseMissing('product_group_items', ['item_id' => $itemA->id]);
        $this->assertDatabaseHas('product_group_items', ['item_id' => $itemB->id]);
    }

    /**
     * Test 10: POS inter-branch transfer web routes render correctly.
     */
    public function test_pos_inter_branch_transfer_web_routes_render(): void
    {
        setPermissionsTeamId($this->branchA->id);
        $this->branchA->ensureDefaultRoles();

        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'canTransferStock', 'guard_name' => 'web']);
        $this->user->givePermissionTo($perm);

        $response = $this->actingAs($this->user)->get(
            route('pos.inventory.transfers.inter-branch.index', ['branch' => $this->branchA->slug])
        );

        $response->assertStatus(200);

        $createResponse = $this->actingAs($this->user)->get(
            route('pos.inventory.transfers.inter-branch.create', ['branch' => $this->branchA->slug])
        );

        $createResponse->assertStatus(200);
    }

    /**
     * Test 11: Organization Admin portal product-links web routes work correctly.
     */
    public function test_org_portal_product_links_routes_render_and_function_correctly(): void
    {
        // Create an Org Admin user
        $orgAdmin = User::create([
            'name'            => 'Org Portal Admin',
            'username'        => 'orgadmin_' . substr(uniqid(), 0, 6),
            'email'           => 'orgadmin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);
        setPermissionsTeamId(0);
        $orgAdmin->assignRole('organization_admin');

        // Create unlinked items in branch A and branch B
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Org Test Item A',
            'barcode_number'  => 'ORG12345',
            'unit_label'      => 'pcs',
            'buy_price'       => 50,
            'price'           => 100,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Org Test Item B',
            'barcode_number'  => 'ORG12345',
            'unit_label'      => 'pcs',
            'buy_price'       => 50,
            'price'           => 100,
        ]);

        // 1. Visit index page
        $response = $this->actingAs($orgAdmin)->get(route('org.product-links.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Org/ProductLinks/Index')
                ->has('organization')
                ->has('branches')
                ->has('groups')
        );

        // 2. Query unlinked items endpoint (default limit 25, search matching and non-matching)
        $unlinkedRes = $this->actingAs($orgAdmin)->get(route('org.product-links.unlinked-items', $this->branchA->id));
        $unlinkedRes->assertStatus(200);
        $unlinkedRes->assertJsonFragment(['item_name' => 'Org Test Item A']);

        // Search matching
        $searchRes = $this->actingAs($orgAdmin)->get(route('org.product-links.unlinked-items', [
            'branch' => $this->branchA->id,
            'search' => 'Item A',
        ]));
        $searchRes->assertStatus(200);
        $searchRes->assertJsonFragment(['item_name' => 'Org Test Item A']);

        // Search non-matching
        $noMatchRes = $this->actingAs($orgAdmin)->get(route('org.product-links.unlinked-items', [
            'branch' => $this->branchA->id,
            'search' => 'NonExistentZzzz',
        ]));
        $noMatchRes->assertStatus(200);
        $this->assertCount(0, $noMatchRes->json());

        // 3. Create group via org route
        $createRes = $this->actingAs($orgAdmin)->post(route('org.product-links.create-group'), [
            'name' => 'Org Test Group',
            'initial_item_id' => $itemA->id,
        ]);
        $createRes->assertRedirect();
        $this->assertDatabaseHas('product_groups', ['organization_id' => $this->org->id, 'name' => 'Org Test Group']);

        $group = ProductGroup::where('organization_id', $this->org->id)->where('name', 'Org Test Group')->first();
        $this->assertNotNull($group);

        // 4. Link item B into the group from branch B
        $linkBRes = $this->actingAs($orgAdmin)->post(route('org.product-links.link'), [
            'product_group_id' => $group->id,
            'branch_id'        => $this->branchB->id,
            'item_id'          => $itemB->id,
            'link_source'      => 'manual',
        ]);
        $linkBRes->assertRedirect();
        $this->assertDatabaseHas('product_group_items', ['product_group_id' => $group->id, 'item_id' => $itemB->id]);

        // 5. Unlink item A (group remains alive because item B is still in it)
        $unlinkRes = $this->actingAs($orgAdmin)->post(route('org.product-links.unlink'), [
            'item_id' => $itemA->id,
        ]);
        $unlinkRes->assertRedirect();
        $this->assertDatabaseMissing('product_group_items', ['product_group_id' => $group->id, 'item_id' => $itemA->id]);

        // 6. Link item A back
        $linkRes = $this->actingAs($orgAdmin)->post(route('org.product-links.link'), [
            'product_group_id' => $group->id,
            'branch_id'        => $this->branchA->id,
            'item_id'          => $itemA->id,
            'link_source'      => 'manual',
        ]);
        $linkRes->assertRedirect();
        $this->assertDatabaseHas('product_group_items', ['product_group_id' => $group->id, 'item_id' => $itemA->id]);

        // 7. Update group name via org portal
        $updateRes = $this->actingAs($orgAdmin)->post(route('org.product-links.update-group'), [
            'product_group_id' => $group->id,
            'name'             => 'Renamed Org Test Group',
        ]);
        $updateRes->assertRedirect();
        $this->assertDatabaseHas('product_groups', ['id' => $group->id, 'name' => 'Renamed Org Test Group']);
        $this->assertDatabaseHas('product_link_logs', [
            'organization_id' => $this->org->id,
            'action'          => 'update_group_name',
        ]);
    }

    /**
     * Test 12: InventoryTransaction logs are created for both sender and receiver after accepting transfer.
     */
    public function test_inventory_transactions_logged_on_both_branches_after_accepting_transfer(): void
    {
        $itemA = Item::create([
            'branch_id'       => $this->branchA->id,
            'item_name'       => 'Indomie Noodles 70g',
            'unit_label'      => 'carton',
            'buy_price'       => 20,
            'price'           => 30,
            'back_store_qty'  => 50,
            'front_store_qty' => 10,
        ]);

        $itemB = Item::create([
            'branch_id'       => $this->branchB->id,
            'item_name'       => 'Indomie Noodles 70g',
            'unit_label'      => 'carton',
            'buy_price'       => 22,
            'price'           => 32,
            'back_store_qty'  => 5,
            'front_store_qty' => 2,
        ]);

        $group = ProductLinkingService::createGroup($this->org->id, 'Indomie Group', $this->user->id, $itemA->id);
        ProductLinkingService::linkItem($group->id, $this->branchB->id, $itemB->id, $this->user->id);

        // Sender dispatches 15 cartons from back_store
        $transfer = InterBranchTransferService::createTransfer(
            $this->branchA->id,
            $this->branchB->id,
            [['item_id' => $itemA->id, 'quantity' => 15, 'notes' => 'Batch A transfer']],
            'back_store',
            'Urgent transfer',
            $this->user->id
        );

        $line = $transfer->items->first();

        // Before accepting: verify no inventory transaction logs exist for this reference
        $this->assertEquals(0, InventoryTransaction::where('reference_id', $transfer->reference)->count());

        // Receiver accepts 15 cartons into front_store
        InterBranchTransferService::acceptTransfer(
            $transfer,
            [['line_id' => $line->id, 'quantity_received' => 15]],
            'front_store',
            $this->user->id
        );

        // 1. Verify Sender Branch Inventory Log
        $senderLog = InventoryTransaction::where('branch_id', $this->branchA->id)
            ->where('reference_id', $transfer->reference)
            ->where('item_id', $itemA->id)
            ->first();

        $this->assertNotNull($senderLog);
        $this->assertEquals('transfer', $senderLog->transaction_type);
        $this->assertEquals(-15, $senderLog->qty);
        $this->assertEquals(50, $senderLog->previous_qty);
        $this->assertEquals(35, $senderLog->new_qty);
        $this->assertEquals('back_store', $senderLog->location);
        $this->assertStringContainsString("Inter-branch transfer out to {$this->branchB->name}", $senderLog->notes);

        // 2. Verify Receiver Branch Inventory Log
        $receiverLog = InventoryTransaction::where('branch_id', $this->branchB->id)
            ->where('reference_id', $transfer->reference)
            ->where('item_id', $itemB->id)
            ->first();

        $this->assertNotNull($receiverLog);
        $this->assertEquals('transfer', $receiverLog->transaction_type);
        $this->assertEquals(15, $receiverLog->qty);
        $this->assertEquals(2, $receiverLog->previous_qty);
        $this->assertEquals(17, $receiverLog->new_qty);
        $this->assertEquals('front_store', $receiverLog->location);
        $this->assertStringContainsString("Inter-branch transfer in from {$this->branchA->name}", $receiverLog->notes);
    }
}

