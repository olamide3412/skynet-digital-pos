<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\HeldSale;
use App\Models\Item;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\PosSettings;
use App\Models\Sale;
use App\Models\TillSession;
use App\Models\User;
use App\Models\UserBranchHistory;
use App\Services\OfflineSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrgUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $orgA;
    protected Organization $orgB;
    protected Branch $branchA1;
    protected Branch $branchA2;
    protected Branch $branchB1;
    protected User $orgAdminA;
    protected User $orgAdminB;
    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        // Global organization_admin role
        Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);

        // Org A
        $this->orgA = Organization::create([
            'name'                => 'Org Alpha ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
            'can_manage_staff'    => true,
        ]);

        $this->branchA1 = Branch::create([
            'organization_id' => $this->orgA->id,
            'name'            => 'Alpha Main ' . uniqid(),
            'slug'            => 'alpha-main-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $this->branchA1->id]);
        $this->branchA1->ensureDefaultRoles();

        $this->branchA2 = Branch::create([
            'organization_id' => $this->orgA->id,
            'name'            => 'Alpha Annex ' . uniqid(),
            'slug'            => 'alpha-annex-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $this->branchA2->id]);
        $this->branchA2->ensureDefaultRoles();

        // Org B
        $this->orgB = Organization::create([
            'name'                => 'Org Beta ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
            'can_manage_staff'    => true,
        ]);

        $this->branchB1 = Branch::create([
            'organization_id' => $this->orgB->id,
            'name'            => 'Beta Branch ' . uniqid(),
            'slug'            => 'beta-branch-' . uniqid(),
            'is_active'       => true,
        ]);
        PosSettings::create(['branch_id' => $this->branchB1->id]);
        $this->branchB1->ensureDefaultRoles();

        // Org Admins
        $this->orgAdminA = User::create([
            'name'            => 'Org Admin Alpha',
            'username'        => 'admin_a_' . uniqid(),
            'email'           => 'admin_a_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgA->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        $this->orgAdminA->assignRole('organization_admin');

        $this->orgAdminB = User::create([
            'name'            => 'Org Admin Beta',
            'username'        => 'admin_b_' . uniqid(),
            'email'           => 'admin_b_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgB->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        $this->orgAdminB->assignRole('organization_admin');

        // Staff user belonging to Branch A1
        $this->staffUser = User::create([
            'name'            => 'Cashier John',
            'username'        => 'john_' . uniqid(),
            'email'           => 'john_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgA->id,
            'branch_id'       => $this->branchA1->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        setPermissionsTeamId($this->branchA1->id);
        $this->staffUser->assignRole('cashier');
    }

    public function test_org_admin_can_view_users_list_for_their_own_organization(): void
    {
        $this->actingAs($this->orgAdminA);

        $response = $this->get(route('org.users.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Org/Users/Index')
            ->has('users.data')
            ->where('organization.id', $this->orgA->id)
        );
    }

    public function test_organization_isolation_prevents_viewing_or_acting_on_users_of_another_org(): void
    {
        // User of Org B
        $userB = User::create([
            'name'            => 'Staff Beta',
            'username'        => 'beta_staff_' . uniqid(),
            'email'           => 'beta_staff_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->orgB->id,
            'branch_id'       => $this->branchB1->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        $this->actingAs($this->orgAdminA);

        // 1. Show details of another org's user -> 403 Forbidden
        $showResponse = $this->getJson(route('org.users.show', $userB->id));
        $showResponse->assertStatus(403);

        // 2. Precheck move -> 403 Forbidden
        $precheckResponse = $this->getJson(route('org.users.precheck-move', [
            'user'                   => $userB->id,
            'destination_branch_id' => $this->branchA2->id,
        ]));
        $precheckResponse->assertStatus(403);

        // 3. Move another org's user -> 403 Forbidden
        $moveResponse = $this->post(route('org.users.move', $userB->id), [
            'destination_branch_id' => $this->branchA2->id,
            'destination_role'      => 'cashier',
            'reason'                => 'Attempted cross-org move',
        ]);
        $moveResponse->assertStatus(403);

        // 4. Disable another org's user -> 403 Forbidden
        $disableResponse = $this->post(route('org.users.disable', $userB->id), [
            'reason' => 'Attempted cross-org disable',
        ]);
        $disableResponse->assertStatus(403);
    }

    public function test_cross_organization_destination_branch_transfer_is_strictly_blocked(): void
    {
        $this->actingAs($this->orgAdminA);

        // Attempt to move staffUser (Org A) into Branch B1 (Org B)
        $precheck = $this->getJson(route('org.users.precheck-move', [
            'user'                   => $this->staffUser->id,
            'destination_branch_id' => $this->branchB1->id,
        ]));

        $precheck->assertStatus(200);
        $this->assertFalse($precheck->json('can_move'));
        $this->assertStringContainsString('different organization', $precheck->json('blocks.0'));

        $moveResponse = $this->post(route('org.users.move', $this->staffUser->id), [
            'destination_branch_id' => $this->branchB1->id,
            'destination_role'      => 'cashier',
            'reason'                => 'Relocation to Org B',
        ]);

        $moveResponse->assertSessionHasErrors('destination_branch_id');
        $this->staffUser->refresh();
        $this->assertEquals($this->branchA1->id, $this->staffUser->branch_id);
    }

    public function test_org_admin_cannot_move_or_disable_themselves_or_other_org_admins(): void
    {
        $this->actingAs($this->orgAdminA);

        // Attempt to move self
        $moveSelf = $this->post(route('org.users.move', $this->orgAdminA->id), [
            'destination_branch_id' => $this->branchA2->id,
            'destination_role'      => 'cashier',
            'reason'                => 'Move myself',
        ]);
        $moveSelf->assertStatus(403);

        // Attempt to disable self
        $disableSelf = $this->post(route('org.users.disable', $this->orgAdminA->id), [
            'reason' => 'Disable myself',
        ]);
        $disableSelf->assertStatus(403);
    }

    public function test_open_till_session_blocks_branch_transfer_with_clear_message(): void
    {
        // Open a till session for the staff user in Branch A1
        TillSession::create([
            'branch_id'      => $this->branchA1->id,
            'cashier_id'     => $this->staffUser->id,
            'opening_amount' => 5000,
            'opened_at'      => now(),
            'status'         => 'open',
        ]);

        $this->actingAs($this->orgAdminA);

        // Precheck should return block
        $precheck = $this->getJson(route('org.users.precheck-move', [
            'user'                   => $this->staffUser->id,
            'destination_branch_id' => $this->branchA2->id,
        ]));

        $precheck->assertStatus(200);
        $this->assertFalse($precheck->json('can_move'));
        $this->assertStringContainsString('open till session', $precheck->json('blocks.0'));

        // Attempting to move should fail validation
        $response = $this->post(route('org.users.move', $this->staffUser->id), [
            'destination_branch_id' => $this->branchA2->id,
            'destination_role'      => 'cashier',
            'reason'                => 'Relocation',
        ]);

        $response->assertSessionHasErrors('destination_branch_id');
        $this->staffUser->refresh();
        $this->assertEquals($this->branchA1->id, $this->staffUser->branch_id);
    }

    public function test_atomic_branch_transfer_succeeds_and_updates_history_and_notifications(): void
    {
        $this->actingAs($this->orgAdminA);

        $response = $this->post(route('org.users.move', $this->staffUser->id), [
            'destination_branch_id' => $this->branchA2->id,
            'destination_role'      => 'branch-admin',
            'reason'                => 'Promoted to manager at Annex branch',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');
        $response->assertSessionHas('new_login_url', url("/{$this->branchA2->slug}"));

        $this->staffUser->refresh();

        // 1. User branch updated
        $this->assertEquals($this->branchA2->id, $this->staffUser->branch_id);

        // 2. User role in destination branch updated
        setPermissionsTeamId($this->branchA2->id);
        $this->assertTrue($this->staffUser->hasRole('branch-admin'));

        // 3. UserBranchHistory created
        $histories = UserBranchHistory::where('user_id', $this->staffUser->id)
            ->orderBy('started_at', 'asc')
            ->get();

        $this->assertCount(2, $histories);
        // Previous branch history (ended)
        $this->assertEquals($this->branchA1->id, $histories[0]->branch_id);
        $this->assertNotNull($histories[0]->ended_at);
        // Current branch history (open)
        $this->assertEquals($this->branchA2->id, $histories[1]->branch_id);
        $this->assertNull($histories[1]->ended_at);
        $this->assertEquals('Promoted to manager at Annex branch', $histories[1]->reason);

        // 4. Audit Log created in organization_activity_log
        $activityLog = OrganizationActivityLog::where('organization_id', $this->orgA->id)
            ->where('action', 'user_transferred_branch')
            ->latest('id')
            ->first();

        $this->assertNotNull($activityLog);
        $this->assertEquals($this->orgAdminA->id, $activityLog->actor_user_id);
        $this->assertEquals($this->staffUser->id, $activityLog->details['user_id']);
        $this->assertEquals($this->branchA1->id, $activityLog->details['source_branch_id']);
        $this->assertEquals($this->branchA2->id, $activityLog->details['destination_branch_id']);

        // 5. In-app notifications sent to both branches
        $notifySource = AppNotification::where('branch_id', $this->branchA1->id)
            ->where('type', 'staff_transferred_out')
            ->first();
        $this->assertNotNull($notifySource);

        $notifyDest = AppNotification::where('branch_id', $this->branchA2->id)
            ->where('type', 'staff_transferred_in')
            ->first();
        $this->assertNotNull($notifyDest);
    }

    public function test_offline_sales_retain_original_cashier_and_branch_after_user_transfer(): void
    {
        // 1. Create item in Branch A1
        $item = Item::create([
            'branch_id'       => $this->branchA1->id,
            'item_name'       => 'Offline Test Soda',
            'price'           => 500,
            'front_store_qty' => 50,
            'unit_label'      => 'can',
        ]);

        $offlineSaleId = 'OFF-SALE-' . uniqid();

        // 2. Prepare payload representing a sale made offline while staff was in Branch A1
        $offlinePayload = [
            'offline_sale_id' => $offlineSaleId,
            'cashier_id'      => $this->staffUser->id,
            'items'           => [
                [
                    'item_id'   => $item->id,
                    'item_name' => $item->item_name,
                    'price'     => 500,
                    'quantity'  => 2,
                    'unit_used' => 'can',
                ],
            ],
            'created_at'      => now()->subMinutes(10)->toIso8601String(),
        ];

        // 3. Move staff user from Branch A1 to Branch A2
        $this->actingAs($this->orgAdminA);
        $this->post(route('org.users.move', $this->staffUser->id), [
            'destination_branch_id' => $this->branchA2->id,
            'destination_role'      => 'cashier',
            'reason'                => 'Transfer before offline sync',
        ]);

        $this->staffUser->refresh();
        $this->assertEquals($this->branchA2->id, $this->staffUser->branch_id);

        // 4. Now sync Branch A1's queued offline sales
        $results = OfflineSyncService::syncBatch([$offlinePayload], $this->branchA1);

        $this->assertNotEmpty($results);
        $this->assertEquals('synced', $results[0]['status']);

        // 5. Verify the sale record in the database
        $sale = Sale::where('offline_sale_id', $offlineSaleId)->first();
        $this->assertNotNull($sale);

        // The sale must strictly belong to Branch A1 AND be attributed to the original cashier ($staffUser)
        $this->assertEquals($this->branchA1->id, $sale->branch_id);
        $this->assertEquals($this->staffUser->id, $sale->user_id);
    }

    public function test_disable_and_enable_staff_user_with_audit_and_notification(): void
    {
        $this->actingAs($this->orgAdminA);

        // 1. Disable user
        $disableResponse = $this->post(route('org.users.disable', $this->staffUser->id), [
            'reason' => 'Security review pending',
        ]);
        $disableResponse->assertSessionHasNoErrors();

        $this->staffUser->refresh();
        $this->assertFalse((bool) $this->staffUser->is_active);
        $this->assertEquals('Security review pending', $this->staffUser->disabled_reason);
        $this->assertEquals($this->orgAdminA->id, $this->staffUser->disabled_by);
        $this->assertNotNull($this->staffUser->disabled_at);

        // In-app notification for disable
        $disableNotice = AppNotification::where('branch_id', $this->branchA1->id)
            ->where('type', 'staff_disabled')
            ->first();
        $this->assertNotNull($disableNotice);

        // 2. Re-enable user
        $enableResponse = $this->post(route('org.users.enable', $this->staffUser->id), [
            'reason' => 'Review completed and cleared',
        ]);
        $enableResponse->assertSessionHasNoErrors();

        $this->staffUser->refresh();
        $this->assertTrue((bool) $this->staffUser->is_active);
        $this->assertNull($this->staffUser->disabled_reason);
        $this->assertNull($this->staffUser->disabled_by);
        $this->assertNull($this->staffUser->disabled_at);

        // In-app notification for enable
        $enableNotice = AppNotification::where('branch_id', $this->branchA1->id)
            ->where('type', 'staff_enabled')
            ->first();
        $this->assertNotNull($enableNotice);
    }

    public function test_delegation_toggle_restricts_move_and_disable_to_read_only(): void
    {
        // Turn off can_manage_staff for Org A
        $this->orgA->update(['can_manage_staff' => false]);

        $this->actingAs($this->orgAdminA);

        // 1. Listing users is still allowed (read-only)
        $listResponse = $this->get(route('org.users.index'));
        $listResponse->assertStatus(200);
        $listResponse->assertInertia(fn ($page) => $page
            ->where('can_manage_staff', false)
        );

        // 2. Move attempt is rejected with 403 Forbidden
        $moveResponse = $this->post(route('org.users.move', $this->staffUser->id), [
            'destination_branch_id' => $this->branchA2->id,
            'destination_role'      => 'cashier',
            'reason'                => 'Relocation',
        ]);
        $moveResponse->assertStatus(403);

        // 3. Disable attempt is rejected with 403 Forbidden
        $disableResponse = $this->post(route('org.users.disable', $this->staffUser->id), [
            'reason' => 'Unauthorized disable',
        ]);
        $disableResponse->assertStatus(403);
    }

    public function test_org_admin_sees_all_branch_staff_and_cross_branch_roles(): void
    {
        // Create user in Branch A2 where organization_id is NULL initially (mimicking standard POS user creation)
        $userWithoutDirectOrg = User::create([
            'name'            => 'Annex Cashier',
            'username'        => 'annex_cashier_' . uniqid(),
            'email'           => 'annex_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => null,
            'branch_id'       => $this->branchA2->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);
        setPermissionsTeamId($this->branchA2->id);
        $userWithoutDirectOrg->assignRole('cashier');

        $this->actingAs($this->orgAdminA);

        $response = $this->get(route('org.users.index'));
        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($userWithoutDirectOrg) {
            $page->component('Org/Users/Index');
            $data = $page->toArray()['props']['users']['data'];
            $ids = collect($data)->pluck('id')->all();

            $this->assertContains($this->staffUser->id, $ids);
            $this->assertContains($userWithoutDirectOrg->id, $ids);

            // Find Annex Cashier and verify role loaded
            $annex = collect($data)->firstWhere('id', $userWithoutDirectOrg->id);
            $this->assertNotNull($annex);
            $this->assertTrue(collect($annex['roles'])->contains('name', 'cashier'));
        });
    }
}
