<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosCustomer;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CustomerCreateAndEditTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::create([
            'name'   => 'Test Org ' . uniqid(),
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name'      => 'Test Branch',
            'slug'      => 'test-branch-' . uniqid(),
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        setPermissionsTeamId($this->branch->id);
        $perm = Permission::firstOrCreate(['name' => 'canManageCustomers', 'guard_name' => 'web']);
        $role = Role::firstOrCreate([
            'name'       => 'manager',
            'guard_name' => 'web',
            'team_id'    => $this->branch->id,
        ]);
        $role->givePermissionTo($perm);
        $this->user->assignRole($role);
    }

    public function test_can_create_customer_with_same_as_phone_flag()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('pos.customers.store', ['branch' => $this->branch->slug]), [
            'name'            => 'Sani Sani',
            'phone'           => '08024004029',
            'email'           => 'sani@example.com',
            'same_as_phone'   => true,
            'whatsapp_number' => '08024004029',
            'address'         => '123 Main Street',
            'gender'          => 'Male',
            'note'            => 'Loyal customer',
        ]);

        $response->assertRedirect(route('pos.customers.index', ['branch' => $this->branch->slug]));
        $response->assertSessionHas('success', 'Customer created.');

        $customer = PosCustomer::where('phone', '08024004029')
            ->where('branch_id', $this->branch->id)
            ->first();

        $this->assertNotNull($customer);
        $this->assertEquals('Sani Sani', $customer->name);
        $this->assertEquals('08024004029', $customer->whatsapp_number);
        $this->assertEquals('123 Main Street', $customer->address);
    }

    public function test_can_create_customer_with_distinct_whatsapp_number()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('pos.customers.store', ['branch' => $this->branch->slug]), [
            'name'            => 'Fatima Bello',
            'phone'           => '08011112222',
            'email'           => 'fatima@example.com',
            'same_as_phone'   => false,
            'whatsapp_number' => '08033334444',
            'address'         => null,
        ]);

        $response->assertRedirect(route('pos.customers.index', ['branch' => $this->branch->slug]));

        $customer = PosCustomer::where('phone', '08011112222')
            ->where('branch_id', $this->branch->id)
            ->first();

        $this->assertNotNull($customer);
        $this->assertEquals('08033334444', $customer->whatsapp_number);
        $this->assertEquals('NA', $customer->address);
    }

    public function test_can_update_customer_with_same_as_phone_flag()
    {
        $this->actingAs($this->user);

        $customer = PosCustomer::create([
            'branch_id'       => $this->branch->id,
            'name'            => 'Initial Name',
            'phone'           => '08055556666',
            'whatsapp_number' => '08055556666',
        ]);

        $response = $this->put(route('pos.customers.update', [
            'branch'   => $this->branch->slug,
            'customer' => $customer->id,
        ]), [
            'name'            => 'Updated Name',
            'phone'           => '08055556666',
            'same_as_phone'   => true,
            'whatsapp_number' => '08055556666',
            'address'         => 'Updated Address',
        ]);

        $response->assertRedirect(route('pos.customers.index', ['branch' => $this->branch->slug]));
        $this->assertEquals('Updated Name', $customer->fresh()->name);
        $this->assertEquals('Updated Address', $customer->fresh()->address);
    }

    public function test_edit_customer_page_renders_with_customer_data()
    {
        $this->actingAs($this->user);

        $customer = PosCustomer::create([
            'branch_id'       => $this->branch->id,
            'name'            => 'Edit Test Customer',
            'phone'           => '08077778888',
            'email'           => 'edittest@example.com',
            'whatsapp_number' => '08077778888',
            'address'         => '10 Victoria Island, Lagos',
            'gender'          => 'Female',
            'note'            => 'Prefers WhatsApp notifications',
        ]);

        $response = $this->get(route('pos.customers.edit', [
            'branch'   => $this->branch->slug,
            'customer' => $customer->id,
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Customers/Edit')
            ->has('customer')
            ->where('customer.name', 'Edit Test Customer')
            ->where('customer.phone', '08077778888')
            ->where('customer.email', 'edittest@example.com')
        );
    }

    public function test_can_update_customer_with_all_fields_and_distinct_whatsapp()
    {
        $this->actingAs($this->user);

        $customer = PosCustomer::create([
            'branch_id'       => $this->branch->id,
            'name'            => 'Original Name',
            'phone'           => '08012341234',
            'email'           => 'orig@example.com',
            'whatsapp_number' => '08012341234',
        ]);

        $response = $this->put(route('pos.customers.update', [
            'branch'   => $this->branch->slug,
            'customer' => $customer->id,
        ]), [
            'name'            => 'Completely New Name',
            'phone'           => '08012341234', // Keeping same phone
            'email'           => 'orig@example.com', // Keeping same email
            'same_as_phone'   => false,
            'whatsapp_number' => '08099990000',
            'address'         => 'Plot 4, Lekki Phase 1',
            'gender'          => 'Male',
            'dob'             => '1990-05-15',
            'note'            => 'VIP Laundry and Retail customer',
            'contact_name'    => 'Guarantor John',
            'contact_phone'   => '08033332222',
            'contact_address' => 'Ikeja, Lagos',
            'watch_list'      => true,
        ]);

        $response->assertRedirect(route('pos.customers.index', ['branch' => $this->branch->slug]));
        $fresh = $customer->fresh();
        $this->assertEquals('Completely New Name', $fresh->name);
        $this->assertEquals('08099990000', $fresh->whatsapp_number);
        $this->assertEquals('Plot 4, Lekki Phase 1', $fresh->address);
        $this->assertEquals('Male', $fresh->gender);
        $this->assertEquals('1990-05-15', $fresh->dob?->format('Y-m-d'));
        $this->assertEquals('VIP Laundry and Retail customer', $fresh->note);
        $this->assertEquals('Guarantor John', $fresh->contact_name);
        $this->assertEquals('08033332222', $fresh->contact_phone);
        $this->assertTrue((bool)$fresh->watch_list);
    }

    public function test_can_update_customer_with_empty_optional_fields()
    {
        $this->actingAs($this->user);

        $customer = PosCustomer::create([
            'branch_id'       => $this->branch->id,
            'name'            => 'Customer With Optional Data',
            'phone'           => '08054321098',
            'email'           => 'optional@example.com',
            'address'         => 'Some Address',
            'note'            => 'Some note',
            'contact_name'    => 'Some Contact',
        ]);

        $response = $this->put(route('pos.customers.update', [
            'branch'   => $this->branch->slug,
            'customer' => $customer->id,
        ]), [
            'name'            => 'Customer With Optional Data',
            'phone'           => '08054321098',
            'email'           => '',
            'same_as_phone'   => true,
            'address'         => null,
            'gender'          => null,
            'dob'             => null,
            'note'            => null,
            'contact_name'    => null,
            'contact_phone'   => null,
            'contact_address' => null,
            'watch_list'      => false,
        ]);

        $response->assertRedirect(route('pos.customers.index', ['branch' => $this->branch->slug]));
        $fresh = $customer->fresh();
        $this->assertNull($fresh->email);
        $this->assertEquals('08054321098', $fresh->whatsapp_number);
        $this->assertEquals('NA', $fresh->address);
        $this->assertFalse((bool)$fresh->watch_list);
    }

    public function test_cannot_edit_customer_from_another_branch()
    {
        $this->actingAs($this->user);

        $otherBranch = Branch::create([
            'organization_id' => $this->branch->organization_id,
            'name'            => 'Other Branch',
            'slug'            => 'other-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $otherCustomer = PosCustomer::create([
            'branch_id'       => $otherBranch->id,
            'name'            => 'Other Branch Customer',
            'phone'           => '08088889999',
            'whatsapp_number' => '08088889999',
        ]);

        $response = $this->get(route('pos.customers.edit', [
            'branch'   => $this->branch->slug,
            'customer' => $otherCustomer->id,
        ]));

        $response->assertNotFound();

        $updateResponse = $this->put(route('pos.customers.update', [
            'branch'   => $this->branch->slug,
            'customer' => $otherCustomer->id,
        ]), [
            'name'  => 'Attempted Hack',
            'phone' => '08088889999',
        ]);

        $updateResponse->assertNotFound();
    }
}

