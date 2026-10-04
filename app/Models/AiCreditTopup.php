<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCreditTopup extends Model
{
    protected $table = 'ai_credit_topups';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'credits',
        'credits_remaining',
        'type',
        'notes',
        'granted_by',
        'expires_at',
    ];

    protected $casts = [
        'credits'           => 'integer',
        'credits_remaining' => 'integer',
        'expires_at'        => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Scope to top-ups that still have remaining credits and are not expired.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('credits_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Check if topup is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
