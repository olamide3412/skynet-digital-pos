<?php

namespace App\Modules\Laundry\Models;

use App\Models\Branch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryDiscount extends Model
{
    protected $table = 'laundry_discounts';

    protected $fillable = [
        'branch_id',
        'scope',
        'service_id',
        'name',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
        'created_by',
        'notes',
    ];

    protected $appends = [
        'status',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(LaundryService::class, 'service_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeGeneral($query)
    {
        return $query->where('scope', 'general');
    }

    public function scopeForService($query, $serviceId)
    {
        return $query->where('scope', 'service')->where('service_id', $serviceId);
    }

    public function scopeActiveNow($query, $time = null)
    {
        $checkTime = $time ? Carbon::parse($time) : Carbon::now();
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $checkTime)
            ->where('ends_at', '>=', $checkTime);
    }

    public function isActiveNow($time = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $checkTime = $time ? Carbon::parse($time) : Carbon::now();
        return $checkTime->between($this->starts_at, $this->ends_at);
    }

    public function isExpired($time = null): bool
    {
        $checkTime = $time ? Carbon::parse($time) : Carbon::now();
        return $checkTime->gt($this->ends_at);
    }

    public function isUpcoming($time = null): bool
    {
        $checkTime = $time ? Carbon::parse($time) : Carbon::now();
        return $checkTime->lt($this->starts_at);
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        if ($this->isActiveNow()) {
            return 'active';
        }
        if ($this->isUpcoming()) {
            return 'upcoming';
        }
        return 'expired';
    }

    /**
     * Compute discount amount on a given amount/subtotal.
     */
    public function computeDiscount(float $amount): float
    {
        if ($amount <= 0 || (float) $this->discount_value <= 0) {
            return 0.0;
        }

        $val = (float) $this->discount_value;
        if ($this->discount_type === 'percentage') {
            $deduction = round($amount * (min(100.0, $val) / 100.0), 2);
        } else {
            $deduction = round(min($amount, $val), 2);
        }

        return max(0.0, $deduction);
    }
}
