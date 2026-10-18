<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'status',
        'can_create_branches',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'can_create_branches' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    /**
     * Branches belonging to this organization.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * Users attached directly to this organization.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Dedicated Organization Admin users for this organization.
     */
    public function adminUsers(): HasMany
    {
        return $this->hasMany(User::class)
            ->whereIn('id', function ($q) {
                $q->select('model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('roles.name', 'organization_admin')
                    ->where('model_has_roles.model_type', User::class);
            });
    }

    /**
     * Audit log of actions taken within or against this organization.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(OrganizationActivityLog::class);
    }

    /**
     * Current active subscription for this organization.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Historical audit log of all subscriptions assigned to this organization.
     */
    public function subscriptionHistories(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class)->latest('id');
    }

    /**
     * AI credit top-up allocations for this organization.
     */
    public function aiCreditTopups(): HasMany
    {
        return $this->hasMany(AiCreditTopup::class)->latest('id');
    }

    /**
     * Online subscription payments (renewals and upgrades) for this organization.
     */
    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->latest('id');
    }

    public function productGroups(): HasMany
    {
        return $this->hasMany(ProductGroup::class);
    }

    public function interBranchTransfers(): HasMany
    {
        return $this->hasMany(InterBranchTransfer::class);
    }

    public function productLinkLogs(): HasMany
    {
        return $this->hasMany(ProductLinkLog::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    // ── Plan Feature & Limit Enforcement ───────────────────────────────────────

    public function hasSubscription(): bool
    {
        return $this->subscription()->exists();
    }

    public function hasFeature(string $feature): bool
    {
        $subscription = $this->subscription()->with('pricingPlan.capabilities')->first();
        if (!$subscription) {
            // Grandfathering: Organizations without an assigned plan are unrestricted
            return true;
        }

        $capabilities = $subscription->pricingPlan?->capabilities;
        if (!$capabilities) {
            return true;
        }

        $column = str_starts_with($feature, 'feature_') ? $feature : "feature_{$feature}";
        if ($column === 'feature_accounting' || $column === 'accounting') {
            $planName = strtolower($subscription->pricingPlan?->name ?? '');
            if (in_array($planName, ['silver', 'gold', 'enterprise'])) {
                return true;
            }
        }

        if (isset($capabilities->{$column})) {
            return (bool) $capabilities->{$column};
        }

        return true;
    }

    public function isBranchCreationAllowed(): bool
    {
        return (bool) ($this->can_create_branches ?? true);
    }

    public function getBranchLimit(): ?int
    {
        $subscription = $this->subscription()->with('pricingPlan.capabilities')->first();
        if (!$subscription) {
            return null; // Unrestricted
        }

        return $subscription->pricingPlan?->capabilities?->max_branches;
    }

    public function isBranchLimitReached(): bool
    {
        $limit = $this->getBranchLimit();
        if ($limit === null) {
            return false;
        }

        return $this->branches()->count() >= $limit;
    }

    public function canCreateBranch(): bool
    {
        if (!$this->isBranchCreationAllowed()) {
            return false;
        }

        return !$this->isBranchLimitReached();
    }

    public function getRemainingBranchSlots(): ?int
    {
        $limit = $this->getBranchLimit();
        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->branches()->count());
    }

    public function getUserLimit(): ?int
    {
        $subscription = $this->subscription()->with('pricingPlan.capabilities')->first();
        if (!$subscription) {
            return null; // Unrestricted
        }

        return $subscription->pricingPlan?->capabilities?->max_users_per_branch;
    }

    public function canCreateUser(?Branch $branch = null): bool
    {
        $limit = $this->getUserLimit();
        if ($limit === null) {
            return true;
        }

        if ($branch) {
            return $branch->users()->count() < $limit;
        }

        return true;
    }

    public function getFeatureSummary(): array
    {
        $subscription = $this->subscription()->with('pricingPlan.capabilities')->first();
        if (!$subscription) {
            return [
                'is_restricted'              => false,
                'max_branches'               => null,
                'max_users_per_branch'       => null,
                'multi_branch'               => true,
                'debt_customer_accounts'     => true,
                'discounts'                  => true,
                'batch_expiry_tracking'      => true,
                'imei_tracking'              => true,
                'barcode_label_printing'     => true,
                'inventory_import'           => true,
                'advanced_staff_permissions' => true,
                'ai_chat_assistant'          => true,
                'ai_reorder_prediction'      => true,
                'broadcast_notifications'    => true,
                'feature_accounting'         => true,
                'accounting'                 => true,
            ];
        }

        $cap = $subscription->pricingPlan?->capabilities;
        $planName = strtolower($subscription->pricingPlan?->name ?? '');
        $isAccountingPlan = in_array($planName, ['silver', 'gold', 'enterprise']);
        $hasAccounting = $cap ? (bool) ($cap->feature_accounting || $isAccountingPlan) : $isAccountingPlan;

        return [
            'is_restricted'              => true,
            'max_branches'               => $cap?->max_branches,
            'max_users_per_branch'       => $cap?->max_users_per_branch,
            'multi_branch'               => (bool) ($cap?->feature_multi_branch ?? true),
            'debt_customer_accounts'     => (bool) ($cap?->feature_debt_customer_accounts ?? true),
            'discounts'                  => (bool) ($cap?->feature_discounts ?? true),
            'batch_expiry_tracking'      => (bool) ($cap?->feature_batch_expiry_tracking ?? true),
            'imei_tracking'              => (bool) ($cap?->feature_imei_tracking ?? true),
            'barcode_label_printing'     => (bool) ($cap?->feature_barcode_label_printing ?? true),
            'inventory_import'           => (bool) ($cap?->feature_inventory_import ?? true),
            'advanced_staff_permissions' => (bool) ($cap?->feature_advanced_staff_permissions ?? true),
            'ai_chat_assistant'          => (bool) ($cap?->feature_ai_chat_assistant ?? true),
            'ai_reorder_prediction'      => (bool) ($cap?->feature_ai_reorder_prediction ?? true),
            'broadcast_notifications'    => (bool) ($cap?->feature_broadcast_notifications ?? true),
            'feature_accounting'         => $hasAccounting,
            'accounting'                 => $hasAccounting,
        ];
    }

    // ── AI Credit Helpers ──────────────────────────────────────────────────────

    public function getAiCreditSummary(?Branch $branch = null): array
    {
        return \App\Modules\AI\Services\AiCreditService::getSummary($this, $branch);
    }

    public function hasAiCreditsRemaining(?Branch $branch = null, int $amount = 1): bool
    {
        return \App\Modules\AI\Services\AiCreditService::hasCreditsRemaining($this, $branch, $amount);
    }

    public function consumeAiCredit(?Branch $branch, ?User $user, string $feature = 'chat_query', int $amount = 1, array $metadata = []): bool
    {
        return \App\Modules\AI\Services\AiCreditService::consumeCredit($this, $branch, $user, $feature, $amount, $metadata);
    }
}
