<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DiscountGroup extends Model
{
    protected $fillable = [
        'branch_id',
        'name',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'discount_value' => 'decimal:2',
            'starts_at'      => 'datetime',
            'ends_at'        => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'discount_group_items')
            ->withTimestamps();
    }

    public function isActiveNow($time = null): bool
    {
        $checkTime = $time ? \Carbon\Carbon::parse($time) : now();
        return (bool) $this->is_active
            && $checkTime->between($this->starts_at, $this->ends_at);
    }

    public function scopeActiveNow(Builder $query, $time = null): Builder
    {
        $checkTime = $time ? \Carbon\Carbon::parse($time) : now();
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $checkTime)
            ->where('ends_at', '>=', $checkTime);
    }

    public function scopeForBranch(Builder $query, $branchId): Builder
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)->orWhereNull('branch_id');
        });
    }
}
