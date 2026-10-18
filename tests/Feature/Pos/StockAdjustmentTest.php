<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\PosSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use DatabaseTransactions;

    protected Organization $org;
    protected Branch $branch;
    protected User $user;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'   => 'Adjustment Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'name'            => 'Adjustment Test Branch ' . uniqid(),
            'slug'            => 'branch-adj-' . substr(uniqid(), 0, 6),
            'organization_id' => $this->org->id,
            'is_active'       => true,
        ]);

        PosSettings::create(['branch_id' => $this->branch->id]);

        $this->user = User::create([
            'name'            => 'Branch Admin User',
            'username'        => 'badmin_' . substr(uniqid(), 0, 6),
            'email'           => 'badmin_' . uniqid() . '@example.com',
            'password'        => bcrypt('password'),
            'organization_id' => $this->org->id,
            'branch_id'       => $this->branch->id,
            'is_active'       => true,
        ]);

        setPermissionsTeamId($this->branch->id);
        $role = Role::firstOrCreate([
            'name'       => 'branch-admin',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $this->user->assignRole($role);

        $this->item = Item::create([
            'branch_id'       => $this->branch->id,
            'item_name'       => 'Test Adjustment Item',
            'barcode_number'  => 'ADJ123456',
            'front_store_qty' => 20,
            'back_store_qty'  => 50,
            'buy_price'       => 100,
            'price'           => 150,
        ]);
    }

    public function test_adjustment_form_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('pos.inventory.adjust', ['branch' => $this->branch->slug])
        );

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Inventory/Adjust')
                ->has('items')
        );
    }

    public function test_process_adjustment_redirects_back_to_adjust_page_with_success(): void
    {
        $response = $this->actingAs($this->user)->post(
            route('pos.inventory.process-adjust', ['branch' => $this->branch->slug]),
            [
                'item_id'  => $this->item->id,
                'location' => 'front_store',
                'type'     => 'Addition',
                'qty'      => 5,
                'reason'   => 'Cycle count addition',
            ]
        );

        // Crucial test: must redirect to pos.inventory.adjust, NOT pos.inventory.index
        $response->assertRedirect(route('pos.inventory.adjust', ['branch' => $this->branch->slug]));
        $response->assertSessionHas('success', 'Inventory successfully adjusted.');

        $this->assertEquals(25, $this->item->fresh()->front_store_qty);
    }
}
