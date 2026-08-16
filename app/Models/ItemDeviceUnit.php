<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ItemDeviceUnit extends Model
{
    protected $table = 'item_device_units';

    protected $fillable = [
        'branch_id',
        'item_id',
        'imei_or_device_id',
        'status',
        'location',
        'purchase_order_id',
        'sale_id',
        'sale_order_id',
        'sold_at',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', 'in_stock');
    }

    public function scopeFrontStore(Builder $query): Builder
    {
        return $query->where('location', 'front_store');
    }

    public function scopeBackStore(Builder $query): Builder
    {
        return $query->where('location', 'back_store');
    }

    public function scopeSold(Builder $query): Builder
    {
        return $query->where('status', 'sold');
    }
}
