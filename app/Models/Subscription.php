<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'organization_id',
        'pricing_plan_id',
        'started_at',
        'renewal_date',
        'status',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'started_at'   => 'date',
        'renewal_date' => 'date',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function pricingPlan(): BelongsTo
    {
        return $this->belongsTo(PricingPlan::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ── Renewal & Status Helpers ──────────────────────────────────────────────

    /**
     * Number of calendar days until the renewal date.
     * Positive = days in future, Negative = days past due, 0 = renewal is today.
     */
    public function daysUntilRenewal(): int
    {
        if (!$this->renewal_date) {
            return 0;
        }

        $today = Carbon::today();
        return (int) $today->diffInDays($this->renewal_date, false);
    }

    /**
     * Checks if renewal is approaching within the given warning window (default 14 days).
     */
    public function isExpiringSoon(int $warningDays = 14): bool
    {
        $days = $this->daysUntilRenewal();
        return $days >= 0 && $days <= $warningDays;
    }

    /**
     * Checks if renewal date has already passed.
     */
    public function isPastDue(): bool
    {
        return $this->daysUntilRenewal() < 0;
    }

    /**
     * Compute current operational status based on configured status and renewal date.
     */
    public function computedStatus(): string
    {
        if ($this->status === 'expired') {
            return 'expired';
        }

        $days = $this->daysUntilRenewal();
        if ($days < 0) {
            // Past renewal date: within 14-day soft grace window -> grace_period; beyond -> expired
            return abs($days) <= 14 ? 'grace_period' : 'expired';
        }

        return $this->status ?: 'active';
    }
}
