<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Item;
use App\Models\PosSettings;
use App\Models\Sale;
use App\Models\TillCashMovement;
use App\Models\TillSession;
use App\Models\User;
use App\Services\RoleService;
use App\Services\SaleService;
use App\Services\TillSessionService;
use App\Services\OfflineSyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TillSessionTest extends TestCase
{
    use DatabaseTransactions;

    protected Branch $branch;
    protected User $cashier;
    protected User $supervisor;
    protected PosSettings $settings;
    protected Item $item;

    protected \App\Models\Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = \App\Models\Organization::create([
            'name' => 'Test Org Till ' . uniqid(),
            'status' => 'active',
        ]);

        // Setup branch
        $this->branch = Branch::create([
            'organization_id' => $this->org->id,
            'name' => 'Test Branch Till ' . uniqid(),
            'slug' => 'test-till-' . substr(uniqid(), 0, 6),
            'is_active' => true,
        ]);

        // Setup permissions
        setPermissionsTeamId($this->branch->id);

        Permission::firstOrCreate(['name' => 'canAccessPos', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'canManageTillCashMovements', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'canViewEndOfDay', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'canEditSettings', 'guard_name' => 'web']);

        $cashierRole = Role::firstOrCreate([
            'name' => 'Cashier Test ' . uniqid(),
            'team_id' => $this->branch->id,
            'guard_name' => 'web',
        ]);
        $cashierRole->givePermissionTo(['canAccessPos']);

        $supervisorRole = Role::firstOrCreate([
            'name' => 'Supervisor Test ' . uniqid(),
            'team_id' => $this->branch->id,
            'guard_name' => 'web',
        ]);
        $supervisorRole->givePermissionTo(['canAccessPos', 'canManageTillCashMovements', 'canViewEndOfDay', 'canEditSettings']);

        // Cashier user
        $this->cashier = User::factory()->create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->cashier->assignRole($cashierRole);

        // Supervisor user
        $this->supervisor = User::factory()->create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->supervisor->assignRole($supervisorRole);

        // Settings
        $this->settings = PosSettings::updateOrCreate(
            ['branch_id' => $this->branch->id],
            [
                'require_till_opening' => true,
                'print_till_slip' => false,
                'is_qty_deduction' => true,
            ]
        );

        // Sample product
        $this->item = Item::create([
            'branch_id' => $this->branch->id,
            'item_name' => 'Test Shampoo ' . uniqid(),
            'barcode_number' => '99' . rand(100000, 999999),
            'buy_price' => 500,
            'price' => 1000,
            'front_store_qty' => 50,
            'back_store_qty' => 0,
        ]);
    }

    public function test_cashier_can_open_till_session()
    {
        $this->actingAs($this->cashier);

        $response = $this->post(route('pos.till.open', ['branch' => $this->branch->slug]), [
            'opening_amount' => 15000,
            'opening_note' => 'Morning shift starting float',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Till opened successfully.');
        $response->assertJsonPath('session.status', 'open');
        $this->assertEquals(15000, (float) $response->json('session.opening_amount'));

        $this->assertDatabaseHas('till_sessions', [
            'branch_id' => $this->branch->id,
            'cashier_id' => $this->cashier->id,
            'status' => 'open',
            'opening_amount' => 15000,
        ]);
    }

    public function test_cannot_open_second_till_session_when_one_is_active()
    {
        $this->actingAs($this->cashier);

        TillSessionService::openSession($this->branch, $this->cashier, 10000);

        $this->expectException(\InvalidArgumentException::class);
        TillSessionService::openSession($this->branch, $this->cashier, 5000);
    }

    public function test_cash_movements_require_permission_and_update_expected_cash()
    {
        // 1. Open session
        $session = TillSessionService::openSession($this->branch, $this->cashier, 10000);

        // 2. Cashier without canManageTillCashMovements cannot post cash movement
        $this->actingAs($this->cashier);
        $res = $this->post(route('pos.till.movements.store', ['branch' => $this->branch->slug]), [
            'till_session_id' => $session->id,
            'type' => 'add',
            'amount' => 2000,
            'reason' => 'Top-up float',
        ]);
        $res->assertStatus(403);

        // 3. Supervisor with permission can post cash movement
        $this->actingAs($this->supervisor);
        $resAdd = $this->post(route('pos.till.movements.store', ['branch' => $this->branch->slug]), [
            'till_session_id' => $session->id,
            'type' => 'add',
            'amount' => 5000,
            'reason' => 'Add change notes',
        ]);
        $resAdd->assertStatus(200);

        $resRemove = $this->post(route('pos.till.movements.store', ['branch' => $this->branch->slug]), [
            'till_session_id' => $session->id,
            'type' => 'remove',
            'amount' => 3000,
            'reason' => 'Mid-day safe drop',
        ]);
        $resRemove->assertStatus(200);

        $summary = $session->calculateSummary();
        $this->assertEquals(10000, $summary['opening_amount']);
        $this->assertEquals(5000, $summary['cash_added']);
        $this->assertEquals(3000, $summary['cash_removed']);
        // Expected Cash = 10000 + 5000 - 3000 = 12000
        $this->assertEquals(12000, $summary['expected_cash']);
    }

    public function test_sale_requires_open_till_when_setting_is_enabled_and_tags_till_session()
    {
        $this->actingAs($this->cashier);

        // Attempting to sell when require_till_opening = true and no session exists throws exception
        $cartData = [
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 2,
                    'price' => 1000,
                    'unit_used' => 'unit',
                ],
            ],
            'payment_method' => 'Cash',
            'amount_paid' => 2000,
            'cash' => 2000,
        ];

        try {
            SaleService::process($cartData, $this->cashier);
            $this->fail('Expected sale to fail when no till session is open.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('till session is required', $e->getMessage());
        }

        // Open till session
        $session = TillSessionService::openSession($this->branch, $this->cashier, 5000);

        // Process sale now
        $sale = SaleService::process($cartData, $this->cashier);
        $this->assertNotNull($sale);
        $this->assertEquals($session->id, $sale->till_session_id);

        // Verify summary incorporates cash sale (2 * 1000 = 2000)
        $summary = $session->calculateSummary();
        $this->assertEquals(2000, $summary['cash_sales']);
        // Expected cash = 5000 + 2000 = 7000
        $this->assertEquals(7000, $summary['expected_cash']);
    }

    public function test_offline_sale_sync_resolves_and_tags_till_session()
    {
        $session = TillSessionService::openSession($this->branch, $this->cashier, 8000, 'Offline test session');

        $offlineSalePayload = [
            'offline_sale_id' => 'off_test_' . uniqid(),
            'till_session_id' => $session->id,
            'cashier_id' => $this->cashier->id,
            'payment_method' => 'Cash',
            'amount_paid' => 1000,
            'cash' => 1000,
            'final_total' => 1000,
            'created_at' => now()->toIso8601String(),
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'price' => 1000,
                    'unit_used' => 'unit',
                ],
            ],
        ];

        $syncResult = OfflineSyncService::syncSingleSale($offlineSalePayload, $this->branch);
        $this->assertEquals('synced', $syncResult['status']);

        $syncedSale = Sale::find($syncResult['sale_id']);
        $this->assertNotNull($syncedSale);
        $this->assertEquals($session->id, $syncedSale->till_session_id);
    }

    public function test_cashier_can_close_till_session_and_calculates_variance()
    {
        $this->actingAs($this->cashier);

        // Open till with float 10,000
        $session = TillSessionService::openSession($this->branch, $this->cashier, 10000);

        // Process cash sale of 3,000
        SaleService::process([
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 3,
                    'price' => 1000,
                    'unit_used' => 'unit',
                ],
            ],
            'payment_method' => 'Cash',
            'amount_paid' => 3000,
            'cash' => 3000,
        ], $this->cashier);

        // Expected cash = 10,000 + 3,000 = 13,000
        // Cashier counts 12,800 (shortage of 200)
        $response = $this->post(route('pos.till.close', ['branch' => $this->branch->slug]), [
            'closing_amount' => 12800,
            'closing_note' => 'Minor change discrepancy of ₦200',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('session.status', 'closed');
        $this->assertEquals(12800, (float) $response->json('session.closing_amount'));
        $this->assertEquals(13000, (float) $response->json('session.expected_amount'));
        $this->assertEquals(-200, (float) $response->json('session.variance'));

        $this->assertDatabaseHas('till_sessions', [
            'id' => $session->id,
            'status' => 'closed',
            'closing_amount' => 12800,
            'expected_amount' => 13000,
            'variance' => -200,
        ]);
    }

    public function test_supervisor_can_force_close_till_session()
    {
        // Cashier opens session and leaves workstation
        $session = TillSessionService::openSession($this->branch, $this->cashier, 10000, 'Abandoned session');

        $this->actingAs($this->supervisor);

        $res = $this->post(route('pos.till.sessions.force-close', [
            'branch' => $this->branch->slug,
            'session' => $session->id,
        ]), [
            'closing_amount' => 10000,
            'closing_note' => 'Cashier forgot to close before leaving',
        ]);

        $res->assertStatus(200);
        $session->refresh();
        $this->assertEquals('closed', $session->status);
        $this->assertEquals($this->supervisor->id, $session->closed_by);
        $this->assertStringContainsString('Force-closed by', $session->closing_note);
    }

    public function test_offline_till_session_sync_endpoint_creates_and_links_session_and_sales()
    {
        $this->actingAs($this->cashier);

        $offlineTillId = 'offline_till_' . uniqid();
        $offlineSaleId = 'offline_sale_' . uniqid();

        // 1. Sync offline till session and offline sale together
        $response = $this->post(route('pos.api.offline.sync', ['branch' => $this->branch->slug]), [
            'till_sessions' => [
                [
                    'offline_session_id' => $offlineTillId,
                    'cashier_id' => $this->cashier->id,
                    'opening_amount' => 5000,
                    'status' => 'open',
                    'opened_at' => now()->subHours(2)->toIso8601String(),
                    'opening_note' => 'Offline morning float',
                ],
            ],
            'sales' => [
                [
                    'offline_sale_id' => $offlineSaleId,
                    'offline_session_id' => $offlineTillId,
                    'cashier_id' => $this->cashier->id,
                    'payment_method' => 'Cash',
                    'amount_paid' => 1000,
                    'cash' => 1000,
                    'final_total' => 1000,
                    'created_at' => now()->subHour()->toIso8601String(),
                    'items' => [
                        [
                            'item_id' => $this->item->id,
                            'qty' => 1,
                            'price' => 1000,
                            'unit_used' => 'unit',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('till_results.0.sync_status', 'synced');
        $response->assertJsonPath('results.0.status', 'synced');

        $syncedSessionId = $response->json('till_results.0.id');
        $this->assertNotNull($syncedSessionId);

        $tillSession = TillSession::find($syncedSessionId);
        $this->assertNotNull($tillSession);
        $this->assertEquals($offlineTillId, $tillSession->offline_session_id);
        $this->assertEquals('open', $tillSession->status);

        $sale = Sale::where('offline_sale_id', $offlineSaleId)->first();
        $this->assertNotNull($sale);
        $this->assertEquals($syncedSessionId, $sale->till_session_id);

        // 2. Sync closing of that offline till session
        $closeResponse = $this->post(route('pos.api.offline.sync', ['branch' => $this->branch->slug]), [
            'till_sessions' => [
                [
                    'offline_session_id' => $offlineTillId,
                    'id' => $syncedSessionId,
                    'status' => 'closed',
                    'closing_amount' => 6000,
                    'closing_note' => 'Offline shift closed',
                    'closed_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $closeResponse->assertStatus(200);
        $tillSession->refresh();
        $this->assertEquals('closed', $tillSession->status);
        $this->assertEquals(6000, (float) $tillSession->closing_amount);
    }

    public function test_sale_proceeds_without_till_session_when_require_till_opening_is_disabled()
    {
        $this->actingAs($this->cashier);

        // Turn OFF require_till_opening
        $this->settings->update(['require_till_opening' => false]);

        $cartData = [
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'qty' => 1,
                    'price' => 1000,
                    'unit_used' => 'unit',
                ],
            ],
            'payment_method' => 'Cash',
            'amount_paid' => 1000,
            'cash' => 1000,
        ];

        // Direct checkout succeeds without active till session
        $sale = SaleService::process($cartData, $this->cashier);
        $this->assertNotNull($sale);
        $this->assertNull($sale->till_session_id);
    }

    public function test_branch_admin_can_turn_on_and_off_require_till_opening_in_pos_settings()
    {
        $this->actingAs($this->supervisor);

        $baseData = [
            'business_name' => $this->branch->name,
            'business_address' => '123 Main St',
            'business_contact_number' => '08012345678',
            'sell_interface' => 'classic',
            'quick_access_mode' => 'none',
            'wholesale_profit_percent' => 15,
            'consumer_profit_percent' => 25,
            'business_sector' => 'commerce',
            'receipt_paper_size' => '80mm',
            'printer_type' => 'thermal_80mm',
            'printer_connection' => 'kiosk_direct',
            'out_of_stock' => 20,
            'expiry_alert_days' => 30,
        ];

        // Turn OFF
        $response = $this->post(route('pos.settings.update', ['branch' => $this->branch->slug]), array_merge($baseData, [
            'require_till_opening' => false,
            'print_till_slip' => false,
        ]));

        $response->assertSessionHasNoErrors();
        $this->settings->refresh();
        $this->assertFalse((bool) $this->settings->require_till_opening);

        // Turn ON
        $response = $this->post(route('pos.settings.update', ['branch' => $this->branch->slug]), array_merge($baseData, [
            'require_till_opening' => true,
            'print_till_slip' => true,
        ]));

        $response->assertSessionHasNoErrors();
        $this->settings->refresh();
        $this->assertTrue((bool) $this->settings->require_till_opening);
        $this->assertTrue((bool) $this->settings->print_till_slip);
    }

    public function test_super_admin_can_toggle_till_opening_via_dedicated_endpoint()
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin);

        // Branch starts with require_till_opening = true
        $this->assertTrue((bool) $this->settings->require_till_opening);

        // Toggle OFF
        $res = $this->post(route('superadmin.branches.toggle-till-opening', ['branch' => $this->branch->slug]));
        $res->assertSessionHas('success');
        $this->settings->refresh();
        $this->assertFalse((bool) $this->settings->require_till_opening);

        // Toggle back ON
        $res = $this->post(route('superadmin.branches.toggle-till-opening', ['branch' => $this->branch->slug]));
        $res->assertSessionHas('success');
        $this->settings->refresh();
        $this->assertTrue((bool) $this->settings->require_till_opening);
    }

    public function test_super_admin_can_update_till_settings_via_branch_settings_update()
    {
        $superAdmin = User::factory()->create([
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin);

        $res = $this->put(route('superadmin.branches.settings.update', ['branch' => $this->branch->slug]), [
            'is_tax_enabled' => false,
            'tax_percentage' => 0,
            'receipt_paper_size' => '80mm',
            'receipt_copies' => 1,
            'auto_print_receipt' => false,
            'show_receipt_preview' => true,
            'is_offline_enabled' => true,
            'is_turnstile_enabled' => true,
            'is_auto_fullscreen_enabled' => false,
            'is_price_editable' => false,
            'is_qty_deduction' => true,
            'out_of_stock' => 10,
            'is_check_expiration' => false,
            'is_imei_enabled' => false,
            'is_show_buy_price' => false,
            'is_use_profit_percentage' => false,
            'sell_interface' => 'classic',
            'business_sector' => 'commerce',
            'require_till_opening' => false,
            'print_till_slip' => true,
        ]);

        $res->assertSessionHas('success');
        $this->settings->refresh();
        $this->assertFalse((bool) $this->settings->require_till_opening);
        $this->assertTrue((bool) $this->settings->print_till_slip);
    }
}


