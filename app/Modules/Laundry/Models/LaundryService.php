<?php

namespace App\Modules\Laundry\Models;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryService extends Model
{
    protected $table = 'laundry_services';

    protected $fillable = [
        'branch_id',
        'category_id',
        'name',
        'pricing_type',
        'price',
        'is_active',
        'sort_order',
    ];

    protected $appends = [
        'category_name',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'price'       => 'decimal:2',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LaundryCategory::class, 'category_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(LaundryOrderItem::class, 'service_id');
    }

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category?->name;
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(LaundryDiscount::class, 'service_id');
    }

    public function activeDiscount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LaundryDiscount::class, 'service_id')
            ->where('scope', 'service')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->latest('id');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}

