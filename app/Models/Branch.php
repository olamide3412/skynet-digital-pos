<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Branch extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'slug', 'address', 'logo_path',
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
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the active subscription of the branch's parent organization.
     */
    public function getSubscriptionAttribute(): ?Subscription
    {
        return $this->organization?->subscription;
    }

    /**
     * AI credit top-ups allocated specifically to this branch.
     */
    public function aiCreditTopups(): HasMany
    {
        return $this->hasMany(AiCreditTopup::class)->latest('id');
    }

    public function getAiCreditSummary(): array
    {
        if (!$this->organization) {
            return [
                'plan_name'     => 'Unset',
                'pool_type'     => 'per_branch',
                'monthly_quota' => 0,
                'used'          => 0,
                'remaining'     => 0,
                'is_unmetered'  => false,
                'is_exhausted'  => true,
                'resets_at'     => now()->addMonth()->startOfMonth()->format('M j, Y'),
            ];
        }

        return \App\Modules\AI\Services\AiCreditService::getSummary($this->organization, $this);
    }

    public function hasAiCreditsRemaining(int $amount = 1): bool
    {
        if (!$this->organization) {
            return false;
        }

        return \App\Modules\AI\Services\AiCreditService::hasCreditsRemaining($this->organization, $this, $amount);
    }

    public function consumeAiCredit(?User $user, string $feature = 'chat_query', int $amount = 1, array $metadata = []): bool
    {
        if (!$this->organization) {
            return false;
        }

        return \App\Modules\AI\Services\AiCreditService::consumeCredit($this->organization, $this, $user, $feature, $amount, $metadata);
    }

    /**
     * Scope query to branches visible to the given user.
     *
     * Platform Super Admin -> all branches
     * Organization Admin -> branches belonging to their organization
     * Branch users -> their own branch
     */
    public function scopeVisibleTo($query, ?User $user = null)
    {
        $user = $user ?: auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isOrganizationAdmin()) {
            return $query->where('organization_id', $user->organization_id);
        }

        if ($user->branch_id) {
            return $query->where('id', $user->branch_id);
        }

        return $query->whereRaw('1 = 0');
    }

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
                    'canViewBatchExpiryReport',
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
                    'canViewExpiryReport',
                    'canViewBatchExpiryReport',
                ])->get();
            if ($inventoryPerms->isNotEmpty()) {
                $inventoryRole->syncPermissions($inventoryPerms);
            }
        }
    }

    // ── Plan Feature & Limit Delegation ────────────────────────────────────────

    public function hasFeature(string $feature): bool
    {
        return $this->organization ? $this->organization->hasFeature($feature) : true;
    }

    public function getUserLimit(): ?int
    {
        return $this->organization?->getUserLimit();
    }

    public function canCreateUser(): bool
    {
        return $this->organization ? $this->organization->canCreateUser($this) : true;
    }
}
