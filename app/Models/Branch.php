<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Branch extends Model
{
    protected $fillable = [
        'name', 'slug', 'address', 'logo_path',
        'phone', 'email', 'is_active', 'is_ai_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_active'     => 'boolean',
            'is_ai_enabled' => 'boolean',
        ];
    }

    // ── Route model binding via slug ──────────────────────────────────────────
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(PosCustomer::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(PosSettings::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    /**
     * Get or create the POS settings for this branch.
     */
    public function getSettings(): PosSettings
    {
        return $this->settings()->firstOrCreate(
            ['branch_id' => $this->id],
            [
                'sell_interface'  => 'classic',
                'business_sector' => 'commerce',
                'out_of_stock'    => 25,
            ]
        );
    }

    /**
     * Ensure default branch-scoped roles exist with their standard permission sets.
     */
    public function ensureDefaultRoles(): void
    {
        setPermissionsTeamId($this->id);

        // 1. Branch Admin Role
        $adminRole = Role::firstOrCreate([
            'name'       => 'branch-admin',
            'guard_name' => 'web',
            'team_id'    => $this->id,
        ]);
        if ($adminRole->wasRecentlyCreated || $adminRole->permissions()->count() === 0) {
            $allPerms = Permission::where('guard_name', 'web')
                ->where('name', '!=', 'canResetPassword')
                ->get();
            if ($allPerms->isNotEmpty()) {
                $adminRole->syncPermissions($allPerms);
            }
        }

        // 2. Cashier Role
        $cashierRole = Role::firstOrCreate([
            'name'       => 'cashier',
            'guard_name' => 'web',
            'team_id'    => $this->id,
        ]);
        if ($cashierRole->wasRecentlyCreated || $cashierRole->permissions()->count() === 0) {
            $cashierPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'canAccessPos',
                    'canViewEndOfDay',
                    'canProcessReturn',
                    'canManageCustomers',
                ])->get();
            if ($cashierPerms->isNotEmpty()) {
                $cashierRole->syncPermissions($cashierPerms);
            }
        }

        // 3. Store Manager Role
        $managerRole = Role::firstOrCreate([
            'name'       => 'manager',
            'guard_name' => 'web',
            'team_id'    => $this->id,
        ]);
        if ($managerRole->wasRecentlyCreated || $managerRole->permissions()->count() === 0) {
            $managerPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'canAccessPos',
                    'canViewSales',
                    'canProcessReturn',
                    'canApplyDiscount',
                    'canEditPrice',
                    'canViewBuyPrice',
                    'canViewEndOfDay',
                    'canViewReports',
                    'canViewProfitLoss',
                    'canManageDebt',
                    'canGiveDebt',
                    'canAdjustStock',
                    'canTransferStock',
                    'canViewReorderPoints',
                    'canManagePurchases',
                    'canManageItems',
                    'canManageBarcodes',
                    'canManageCustomers',
                    'canManageUsers',
                    'canLogExpense',
                    'canManageExpenses',
                    'canViewExpenseReports',
                    'canViewExpiryReport',
                ])->get();
            if ($managerPerms->isNotEmpty()) {
                $managerRole->syncPermissions($managerPerms);
            }
        }

        // 4. Inventory Manager Role
        $inventoryRole = Role::firstOrCreate([
            'name'       => 'inventory-manager',
            'guard_name' => 'web',
            'team_id'    => $this->id,
        ]);
        if ($inventoryRole->wasRecentlyCreated || $inventoryRole->permissions()->count() === 0) {
            $inventoryPerms = Permission::where('guard_name', 'web')
                ->whereIn('name', [
                    'canAdjustStock',
                    'canTransferStock',
                    'canViewReorderPoints',
                    'canManagePurchases',
                    'canManageItems',
                    'canManageBarcodes',
                ])->get();
            if ($inventoryPerms->isNotEmpty()) {
                $inventoryRole->syncPermissions($inventoryPerms);
            }
        }
    }
}
