<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PlanCapability;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\AI\Models\AiConversationLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiConversationPersistenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_conversation_persists_across_visits_and_clears_on_request(): void
    {
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

        $org = Organization::create([
            'name'   => 'Persistence Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subMonths(2)->toDateString(),
            'renewal_date'    => Carbon::now()->addMonths(10)->toDateString(),
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Test Persist Branch ' . uniqid(),
            'slug'            => 'persist-b-' . uniqid(),
            'is_active'       => true,
        ]);

        $user = User::factory()->create([
            'name'            => 'Manager ' . uniqid(),
            'organization_id' => $org->id,
            'branch_id'       => $branch->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        setPermissionsTeamId(0);
        $user->assignRole('organization_admin');

        // 1. User sends a question
        $response = $this->actingAs($user)->postJson(route('pos.ai.chat.ask', ['branch' => $branch->slug]), [
            'question' => 'What is our total revenue?',
        ]);

        $response->assertOk();

        // Verify logs saved in database
        $this->assertEquals(2, AiConversationLog::where('user_id', $user->id)->count());
        $userLog = AiConversationLog::where('user_id', $user->id)->where('role', 'user')->first();
        $assistantLog = AiConversationLog::where('user_id', $user->id)->where('role', 'assistant')->first();

        $this->assertEquals('What is our total revenue?', $userLog->message);
        $this->assertNotNull($assistantLog);

        // 2. User navigates back to index (no session_id query param passed)
        $indexResponse = $this->actingAs($user)->get(route('pos.ai.chat', ['branch' => $branch->slug]));
        $indexResponse->assertOk();

        $page = $indexResponse->original->getData()['page'];
        $initialLogs = $page['props']['initialLogs'];

        $this->assertCount(2, $initialLogs);
        $this->assertEquals('user', $initialLogs[0]['role']);
        $this->assertEquals('What is our total revenue?', $initialLogs[0]['message']);
        $this->assertEquals('assistant', $initialLogs[1]['role']);

        // 3. User clears chat history
        $clearResponse = $this->actingAs($user)->delete(route('pos.ai.chat.clear', ['branch' => $branch->slug]), [
            'session_id' => $page['props']['sessionId'],
        ]);

        $clearResponse->assertRedirect();
        $this->assertEquals(0, AiConversationLog::where('user_id', $user->id)->count());

        // 4. User revisits index after clear
        $afterClearResponse = $this->actingAs($user)->get(route('pos.ai.chat', ['branch' => $branch->slug]));
        $afterClearPage = $afterClearResponse->original->getData()['page'];
        $this->assertCount(0, $afterClearPage['props']['initialLogs']);
    }

    public function test_org_conversation_persists_across_visits_and_clears_on_request(): void
    {
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

        $org = Organization::create([
            'name'   => 'Org Persist ' . uniqid(),
            'status' => 'active',
        ]);

        Subscription::create([
            'organization_id' => $org->id,
            'pricing_plan_id' => $plan->id,
            'status'          => 'active',
            'started_at'      => Carbon::now()->subMonths(2)->toDateString(),
            'renewal_date'    => Carbon::now()->addMonths(10)->toDateString(),
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name'            => 'Org Branch ' . uniqid(),
            'slug'            => 'org-b-' . uniqid(),
            'is_active'       => true,
        ]);

        $user = User::factory()->create([
            'name'            => 'Org Admin ' . uniqid(),
            'organization_id' => $org->id,
            'is_active'       => true,
            'is_super_admin'  => false,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'organization_admin', 'guard_name' => 'web']);
        setPermissionsTeamId(0);
        $user->assignRole('organization_admin');

        // 1. Ask question in Org AI chat
        $askResponse = $this->actingAs($user)->postJson(route('org.ai.chat.ask'), [
            'question'  => 'Summarize branch operations',
            'branch_id' => $branch->id,
        ]);
        $askResponse->assertOk();

        $this->assertEquals(2, AiConversationLog::where('user_id', $user->id)->count());

        // 2. Org admin returns to org AI chat page
        $chatResponse = $this->actingAs($user)->get(route('org.ai.chat'));
        $chatResponse->assertOk();

        $page = $chatResponse->original->getData()['page'];
        $initialLogs = $page['props']['initialLogs'];
        $this->assertCount(2, $initialLogs);
        $this->assertEquals('user', $initialLogs[0]['role']);
        $this->assertEquals('Summarize branch operations', $initialLogs[0]['message']);
        $this->assertEquals('assistant', $initialLogs[1]['role']);

        // 3. Clear conversation
        $clearResponse = $this->actingAs($user)->deleteJson(route('org.ai.chat.clear'), [
            'session_id' => $page['props']['sessionId'],
        ]);
        $clearResponse->assertOk();
        $this->assertEquals(0, AiConversationLog::where('user_id', $user->id)->count());

        // 4. Revisit org AI chat after clear
        $afterClearChat = $this->actingAs($user)->get(route('org.ai.chat'));
        $afterClearPage = $afterClearChat->original->getData()['page'];
        $this->assertCount(0, $afterClearPage['props']['initialLogs']);
    }
}
