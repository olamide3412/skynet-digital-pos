<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\PosSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CameraBarcodeScannerSettingTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $branchAdmin;
    protected Branch $branch;
    protected Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        config(['organizations.enabled' => true]);

        $this->org = Organization::create([
            'name'          => 'Scanner Test Org',
            'contact_name'  => 'Scanner Admin',
            'contact_email' => 'scanner@example.com',
            'status'        => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name'            => 'Scanner Branch',
            'slug'            => 'scanner-branch-' . uniqid(),
            'is_active'       => true,
        ]);

        $this->superAdmin = User::firstOrCreate(
            ['username' => 'superadmin_camera_test'],
            [
                'name'           => 'Super Admin Camera Test',
                'email'          => 'superadmin_camera_test@skynetpos.com',
                'password'       => bcrypt('password'),
                'is_super_admin' => true,
                'is_active'      => true,
            ]
        );

        $this->branchAdmin = User::create([
            'username'        => 'ba_camera_' . uniqid(),
            'name'            => 'Branch Admin Camera Test',
            'email'           => 'branchadmin_camera_' . uniqid() . '@skynetpos.com',
            'password'        => bcrypt('password'),
            'branch_id'       => $this->branch->id,
            'organization_id' => $this->org->id,
            'is_super_admin'  => false,
            'is_active'       => true,
        ]);
    }

    public function test_pos_settings_defaults_camera_scanner_to_true(): void
    {
        $settings = $this->branch->getSettings();

        $this->assertTrue($settings->is_camera_scanner_enabled);
    }

    public function test_offline_bootstrap_includes_camera_scanner_setting(): void
    {
        $response = $this->actingAs($this->branchAdmin)->getJson(
            route('pos.api.offline.bootstrap', ['branch' => $this->branch->slug])
        );

        $response->assertStatus(200);
        $response->assertJsonPath('settings.is_camera_scanner_enabled', true);
    }

    public function test_superadmin_can_update_camera_scanner_setting(): void
    {
        $settings = $this->branch->getSettings();

        $response = $this->actingAs($this->superAdmin)->put(
            route('superadmin.branches.settings.update', $this->branch->slug),
            array_merge($settings->toArray(), [
                'business_name'             => $this->branch->name,
                'is_tax_enabled'            => false,
                'tax_percentage'            => 0,
                'receipt_paper_size'        => '80mm',
                'receipt_copies'            => 1,
                'auto_print_receipt'        => false,
                'show_receipt_preview'      => true,
                'is_offline_enabled'        => true,
                'is_turnstile_enabled'      => true,
                'is_auto_fullscreen_enabled'=> false,
                'is_price_editable'         => false,
                'is_qty_deduction'          => true,
                'out_of_stock'              => 25,
                'is_check_expiration'       => false,
                'is_imei_enabled'           => false,
                'is_camera_scanner_enabled' => false,
                'is_show_buy_price'         => false,
                'is_use_profit_percentage'  => false,
                'sell_interface'            => 'classic',
                'business_sector'           => 'commerce',
            ])
        );

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pos_settings', [
            'branch_id'                 => $this->branch->id,
            'is_camera_scanner_enabled' => 0,
        ]);
    }
}
