<?php

namespace Tests\Feature;

use App\Models\PosLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SuperAdminLogsPruneTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::firstOrCreate(
            ['username' => 'superadmin_prune_test'],
            [
                'name'           => 'Super Admin Prune',
                'email'          => 'superadmin_prune_test@skynetpos.com',
                'password'       => bcrypt('password'),
                'is_super_admin' => true,
                'is_active'      => true,
            ]
        );

        $this->regularUser = User::firstOrCreate(
            ['username' => 'regular_user_test'],
            [
                'name'           => 'Regular User',
                'email'          => 'regular_user_test@skynetpos.com',
                'password'       => bcrypt('password'),
                'is_super_admin' => false,
                'is_active'      => true,
            ]
        );
    }

    public function test_superadmin_can_view_logs_index_with_counts(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.logs.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('SuperAdmin/Logs/Index')
                ->has('old_logs_counts')
                ->has('old_logs_counts.30')
                ->has('old_logs_counts.60')
                ->has('old_logs_counts.90')
        );
    }

    public function test_superadmin_can_prune_logs_older_than_30_days(): void
    {
        // Create an old log (40 days old)
        $oldLog = PosLog::create([
            'log'         => 'Old log from 40 days ago',
            'action_type' => 'stock',
            'created_at'  => Carbon::now()->subDays(40),
        ]);

        // Create a recent log (5 days old)
        $recentLog = PosLog::create([
            'log'         => 'Recent log from 5 days ago',
            'action_type' => 'sale',
            'created_at'  => Carbon::now()->subDays(5),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.logs.prune'), [
                'days' => 30,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('pos_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('pos_logs', ['id' => $recentLog->id]);
    }

    public function test_pruning_validates_days_parameter(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->delete(route('superadmin.logs.prune'), [
                'days' => 5, // not in 30, 60, 90, 180
            ]);

        $response->assertSessionHasErrors(['days']);
    }

    public function test_regular_user_cannot_prune_logs(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->delete(route('superadmin.logs.prune'), [
                'days' => 30,
            ]);

        $response->assertStatus(403);
    }
}
