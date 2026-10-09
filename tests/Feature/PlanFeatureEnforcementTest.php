<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlanFeatureEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected PricingPlan $starterPlan;
    protected PricingPlan $silverPlan;
    protected PricingPlan $goldPlan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);

        $this->starterPlan = PricingPlan::firstOrCreate(
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
            ['pricing_plan_id' => $this->starterPlan->id],
            [
                'max_branches'                => 1,
                'max_users_per_branch'        => 3,
                'feature_multi_branch'        => false,
                'feature_debt_customer_accounts' => false,
                'feature_discounts'           => false,
                'feature_batch_expiry_tracking' => false,
                'feature_imei_tracking'       => false,
                'feature_barcode_label_printing' => false,
                'feature_inventory_import'    => false,
                'feature_advanced_staff_permissions' => false,
                'feature_ai_chat_assistant'   => false,
                'feature_ai_reorder_prediction' => false,
                'feature_broadcast_notifications' => false,
                'ai_credit_pool_type'         => 'account_wide',
                'ai_credits_monthly'          => 25,
            ]
        );

        $this->silverPlan = PricingPlan::firstOrCreate(
            ['name' => 'Silver'],
            [
                'first_year_price' => '₦450,000',
                'renewal_price'    => '₦300,000 / year',
                'branches_limit'   => 'Up to 7 Branches',
                'users_limit'      => 'Up to 20 Users per branch',
                'ai_credits'       => '150 AI credits monthly',
                'is_active'        => true,
                'sort_order'       => 3,
            ]
        );
        PlanCapability::updateOrCreate(
            ['pricing_plan_id' => $this->silverPlan->id],
            [
                'max_branches'                => 7,
                'max_users_per_branch'        => 20,
                'feature_multi_branch'        => true,
                'feature_debt_customer_accounts' => true,
                'feature_discounts'           => true,
                'feature_batch_expiry_tracking' => true,
                'feature_imei_tracking'       => true,
                'feature_barcode_label_printing' => true,
                'feature_inventory_import'    => true,
                'feature_advanced_staff_permissions' => true,
                'feature_ai_chat_assistant'   => true,
                'feature_ai_reorder_prediction' => true,
                'feature_broadcast_notifications' => true,
                'ai_credit_pool_type'         => 'per_branch',
                'ai_credits_monthly'          => 150,
            ]
        );
    }

    protected function createOrgWithSubscription(?PricingPlan $plan = null, string $prefix = 'Org'): array
    {
        $org = Organization::create([
            'name'   => $prefix . ' ' . uniqid(),
            'status' => 'active',
        ]);

        if ($plan) {
            Subscription::create([
                'organization_id' => $org->id,
                'pricing_plan_id' => $plan->id,
                'status'          => 'active',
                'started_at'      => Carbon::now()->subMonths(1)->toDateString(),
                'renewal_date'    => Carbon::now()->addMonths(11)->toDateString(),
            ]);
        }

        $branch = Branch::create([
            'name'            => 'Branch ' . uniqid(),
            'slug'            => 'branch-' . uniqid(),
            'organization_id' => $org->id,
            'is_active'       => true,
        ]);

        $user = User::factory()->create([
            'name'            => 'Staff ' . uniqid(),
            'organization_id' => $org->id,
            'branch_id'       => $branch->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        setPermissionsTeamId($branch->id);
        $branch->ensureDefaultRoles();
        $user->assignRole('branch-admin');

        return [$org, $branch, $user];
    }

    public function test_grandfathering_unassigned_organization_is_unrestricted(): void
    {
        // Organization with NO subscription assigned yet
        [$org, $branch, $user] = $this->createOrgWithSubscription(null, 'Unassigned Org');

        $this->assertFalse($org->hasSubscription());
        $this->assertTrue($org->hasFeature('imei_tracking'));
        $this->assertTrue($org->hasFeature('batch_expiry_tracking'));
        $this->assertTrue($org->hasFeature('discounts'));
        $this->assertTrue($org->canCreateBranch());
        $this->assertTrue($org->canCreateUser($branch));
        $this->assertTrue($branch->hasFeature('imei_tracking'));

        // Feature summary shows unrestricted
        $summary = $org->getFeatureSummary();
        $this->assertFalse($summary['is_restricted']);
        $this->assertNull($summary['max_branches']);
        $this->assertNull($summary['max_users_per_branch']);
        $this->assertTrue($summary['imei_tracking']);
    }

    public function test_branch_creation_limit_enforced_per_organization(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Starter Org');

        // Starter plan allows max 1 branch
        $this->assertEquals(1, $org->getBranchLimit());
        $this->assertEquals(1, $org->branches()->count());
        $this->assertFalse($org->canCreateBranch());

        // Platform Super Admin attempting to create a second branch for this org
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('superadmin.branches.store'), [
            'organization_id' => $org->id,
            'name'            => 'Branch 2',
            'slug'            => 'branch-2-' . uniqid(),
            'admin_name'      => 'Admin Two',
            'admin_username'  => 'adm_two_' . substr(uniqid(), 0, 5),
            'admin_email'     => 'adm_two_' . uniqid() . '@example.com',
            'admin_password'  => 'password123',
        ]);

        $response->assertSessionHasErrors('organization_id');
        $this->assertEquals(1, $org->fresh()->branches()->count());
    }

    public function test_user_creation_limit_enforced_per_branch(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Starter User Limit');

        // Starter plan allows max 3 users per branch
        // Current user count is 1. Add 2 more to reach capacity of 3
        User::factory()->create(['branch_id' => $branch->id, 'organization_id' => $org->id]);
        User::factory()->create(['branch_id' => $branch->id, 'organization_id' => $org->id]);

        $this->assertEquals(3, $branch->users()->count());
        $this->assertFalse($branch->canCreateUser());

        // Attempt to create a 4th user via Pos UserController
        $response = $this->actingAs($user)->post(route('pos.users.store', ['branch' => $branch->slug]), [
            'name'        => 'User Four',
            'username'    => 'user4_' . substr(uniqid(), 0, 5),
            'email'       => 'user4_' . uniqid() . '@example.com',
            'password'    => 'password123',
            'role'        => 'cashier',
            'is_active'   => true,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertEquals(3, $branch->fresh()->users()->count());
    }

    public function test_grandfathering_existing_branches_and_users_remain_functional(): void
    {
        // Organization with 3 branches created BEFORE plan assignment
        $org = Organization::create([
            'name'   => 'Grandfathered Org ' . uniqid(),
            'status' => 'active',
        ]);

        $b1 = Branch::create(['name' => 'B1', 'slug' => 'b1-' . uniqid(), 'organization_id' => $org->id, 'is_active' => true]);
        $b2 = Branch::create(['name' => 'B2', 'slug' => 'b2-' . uniqid(), 'organization_id' => $org->id, 'is_active' => true]);
        $b3 = Branch::create(['name' => 'B3', 'slug' => 'b3-' . uniqid(), 'organization_id' => $org->id, 'is_active' => true]);

        $this->assertEquals(3, $org->branches()->count());

        // Now manually assign Starter plan (which only allows 1 branch)
        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $this->starterPlan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->toDateString(),
            'renewal_date'    => Carbon::now()->addYear()->toDateString(),
        ]);

        // All 3 branches remain in database and active
        $this->assertEquals(3, $org->branches()->count());
        $this->assertTrue($b1->fresh()->is_active);
        $this->assertTrue($b2->fresh()->is_active);
        $this->assertTrue($b3->fresh()->is_active);

        // However, creating a 4th branch is blocked
        $this->assertFalse($org->canCreateBranch());
    }

    public function test_plan_gated_features_server_side_enforcement(): void
    {
        // Starter Org (does not include IMEI tracking or batch expiry tracking)
        [$starterOrg, $starterBranch, $starterUser] = $this->createOrgWithSubscription($this->starterPlan, 'Starter Gated');

        // Silver Org (includes IMEI and batch expiry tracking)
        [$silverOrg, $silverBranch, $silverUser] = $this->createOrgWithSubscription($this->silverPlan, 'Silver Allowed');

        // Requesting IMEI tracking as Starter branch user is blocked with 403
        $responseStarter = $this->actingAs($starterUser)->get(route('pos.items.imeis', ['branch' => $starterBranch->slug]));
        $responseStarter->assertStatus(403);

        // Requesting IMEI tracking as Silver branch user is allowed
        $responseSilver = $this->actingAs($silverUser)->get(route('pos.items.imeis', ['branch' => $silverBranch->slug]));
        $responseSilver->assertStatus(200);

        // Requesting AI Chat Assistant as Starter branch user is blocked with 403
        $responseAiStarter = $this->actingAs($starterUser)->get(route('pos.ai.chat', ['branch' => $starterBranch->slug]));
        $responseAiStarter->assertStatus(403);
        $responseAiStarter->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', 403)
            ->where('message', "The 'Ai Chat Assistant' feature is not included in your organization's current plan. Please contact your administrator to upgrade your subscription.")
        );

        // Requesting AI Chat Assistant as Silver branch user is allowed with 200
        $responseAiSilver = $this->actingAs($silverUser)->get(route('pos.ai.chat', ['branch' => $silverBranch->slug]));
        $responseAiSilver->assertStatus(200);
    }

    public function test_cross_organization_isolation_in_limits(): void
    {
        [$orgA, $branchA, $userA] = $this->createOrgWithSubscription($this->starterPlan, 'Org Alpha');
        [$orgB, $branchB, $userB] = $this->createOrgWithSubscription($this->silverPlan, 'Org Beta');

        // Org A is at 1/1 capacity
        $this->assertFalse($orgA->canCreateBranch());

        // Org B has 1/7 branches used, can still create branches!
        $this->assertTrue($orgB->canCreateBranch());

        // Branch A user limit reached does not affect Branch B
        User::factory()->create(['branch_id' => $branchA->id, 'organization_id' => $orgA->id]);
        User::factory()->create(['branch_id' => $branchA->id, 'organization_id' => $orgA->id]);
        $this->assertFalse($branchA->canCreateUser());
        $this->assertTrue($branchB->canCreateUser());
    }

    public function test_superadmin_bypasses_feature_gates(): void
    {
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Bypass Org');

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        // SuperAdmin can access gated feature for the branch without 403
        $response = $this->actingAs($superAdmin)->get(route('pos.items.imeis', ['branch' => $branch->slug]));
        $response->assertStatus(200);
    }

    protected function createOrgAdmin(Organization $org): User
    {
        $orgAdmin = User::factory()->create([
            'organization_id' => $org->id,
            'branch_id'       => null,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        setPermissionsTeamId(0);
        $role = Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        $orgAdmin->assignRole($role);

        return $orgAdmin;
    }

    public function test_org_admin_can_create_branch_in_org_portal_within_plan_limit(): void
    {
        config(['organizations.enabled' => true]);

        // Silver Plan allows up to 7 branches. Currently has 1 branch.
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->silverPlan, 'Silver Org Portal');
        $orgAdmin = $this->createOrgAdmin($org);

        $this->assertEquals(1, $org->branches()->count());
        $this->assertTrue($org->canCreateBranch());

        $newSlug = 'lagos-branch-' . uniqid();
        $response = $this->actingAs($orgAdmin)->post(route('org.branches.store'), [
            'name'           => 'Lagos Branch',
            'slug'           => $newSlug,
            'address'        => '12 Marina St, Lagos',
            'phone'          => '08012345678',
            'email'          => 'lagos@store.com',
            'admin_name'     => 'Branch Admin Lagos',
            'admin_username' => 'adm_lagos_' . substr(uniqid(), 0, 5),
            'admin_email'    => 'lagos_' . uniqid() . '@example.com',
            'admin_password' => 'password123',
        ]);

        $response->assertRedirect(route('org.branches'));
        $response->assertSessionHas('success');

        $this->assertEquals(2, $org->fresh()->branches()->count());
        $createdBranch = Branch::where('slug', $newSlug)->first();
        $this->assertNotNull($createdBranch);
        $this->assertEquals($org->id, $createdBranch->organization_id);
    }

    public function test_org_admin_cannot_create_branch_when_plan_limit_reached(): void
    {
        config(['organizations.enabled' => true]);

        // Starter Plan allows max 1 branch. Currently has 1 branch.
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->starterPlan, 'Starter Org Portal');
        $orgAdmin = $this->createOrgAdmin($org);

        $this->assertEquals(1, $org->branches()->count());
        $this->assertFalse($org->canCreateBranch());

        $response = $this->actingAs($orgAdmin)->post(route('org.branches.store'), [
            'name'           => 'Exceeding Branch',
            'slug'           => 'exceeding-branch-' . uniqid(),
            'admin_name'     => 'Admin Over',
            'admin_username' => 'adm_over_' . substr(uniqid(), 0, 5),
            'admin_email'    => 'over_' . uniqid() . '@example.com',
            'admin_password' => 'password123',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertEquals(1, $org->fresh()->branches()->count());
    }

    public function test_superadmin_can_disable_and_enable_org_branch_creation(): void
    {
        config(['organizations.enabled' => true]);

        // Silver Plan allows 7 branches. Currently has 1 branch.
        [$org, $branch, $user] = $this->createOrgWithSubscription($this->silverPlan, 'Toggle Branch Org');
        $orgAdmin = $this->createOrgAdmin($org);

        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        // By default, feature is enabled
        $this->assertTrue($org->fresh()->can_create_branches);
        $this->assertTrue($org->canCreateBranch());

        // SuperAdmin disables branch creation for this org via toggle endpoint
        $toggleResp = $this->actingAs($superAdmin)->post(route('superadmin.organizations.toggle-branch-creation', $org->id));
        $toggleResp->assertSessionHas('success');
        $this->assertFalse($org->fresh()->can_create_branches);
        $this->assertFalse($org->fresh()->canCreateBranch());

        // Org Admin attempts to create branch -> blocked even though plan limit has slots!
        $responseBlocked = $this->actingAs($orgAdmin)->post(route('org.branches.store'), [
            'name'           => 'Blocked Branch',
            'slug'           => 'blocked-branch-' . uniqid(),
            'admin_name'     => 'Admin Blocked',
            'admin_username' => 'adm_blk_' . substr(uniqid(), 0, 5),
            'admin_email'    => 'blk_' . uniqid() . '@example.com',
            'admin_password' => 'password123',
        ]);
        $responseBlocked->assertSessionHasErrors('name');
        $this->assertEquals(1, $org->fresh()->branches()->count());

        // SuperAdmin can still provision branch for the org from SuperAdmin portal
        $saCreateResp = $this->actingAs($superAdmin)->post(route('superadmin.branches.store'), [
            'organization_id' => $org->id,
            'name'            => 'SuperAdmin Created Branch',
            'slug'            => 'sa-created-' . uniqid(),
            'admin_name'      => 'SA Branch Admin',
            'admin_username'  => 'sa_admin_' . substr(uniqid(), 0, 5),
            'admin_email'     => 'sa_admin_' . uniqid() . '@example.com',
            'admin_password'  => 'password123',
        ]);
        $saCreateResp->assertSessionHasNoErrors();
        $this->assertEquals(2, $org->fresh()->branches()->count());

        // SuperAdmin re-enables branch creation via organization update or toggle
        $this->actingAs($superAdmin)->post(route('superadmin.organizations.toggle-branch-creation', $org->id));
        $this->assertTrue($org->fresh()->can_create_branches);
        $this->assertTrue($org->fresh()->canCreateBranch());

        // Org Admin can now successfully create branch
        $responseAllowed = $this->actingAs($orgAdmin)->post(route('org.branches.store'), [
            'name'           => 'Allowed Branch',
            'slug'           => 'allowed-branch-' . uniqid(),
            'admin_name'     => 'Admin Allowed',
            'admin_username' => 'adm_allow_' . substr(uniqid(), 0, 5),
            'admin_email'    => 'allow_' . uniqid() . '@example.com',
            'admin_password' => 'password123',
        ]);
        $responseAllowed->assertRedirect(route('org.branches'));
        $this->assertEquals(3, $org->fresh()->branches()->count());
    }

    public function test_superadmin_can_create_organization_admin_user(): void
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active'      => true,
        ]);

        $org = Organization::create([
            'name'                => 'Org For Admin ' . uniqid(),
            'status'              => 'active',
            'can_create_branches' => true,
        ]);

        $username = 'org_adm_' . substr(uniqid(), 0, 8);
        $email = 'adm_' . uniqid() . '@example.com';

        $response = $this->actingAs($superAdmin)
            ->post(route('superadmin.organizations.users.store', $org->id), [
                'name'     => 'Jane Org Admin',
                'username' => $username,
                'email'    => $email,
                'password' => 'secretPassword123!',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $createdUser = User::where('username', $username)->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals($org->id, $createdUser->organization_id);
        $this->assertTrue($createdUser->isOrganizationAdmin());
    }
}
