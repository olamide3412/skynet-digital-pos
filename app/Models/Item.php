<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'category_id', 'group_address_id', 'item_name', 'barcode_number',
        'qty', 'buy_price', 'price', 'wholesale_price', 'expiry_date',
        'item_description', 'image_path', 'price_locked',
    ];

    protected $appends = ['image_url'];

    protected function casts(): array
    {
        return [
            'price_locked'    => 'boolean',
            'buy_price'       => 'decimal:2',
            'price'           => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'expiry_date'     => 'date',
            'qty'             => 'integer',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function groupAddress(): BelongsTo
    {
        return $this->belongsTo(GroupAddress::class);
    }

    public function saleOrders(): HasMany
    {
        return $this->hasMany(SaleOrder::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function itemGrids(): HasMany
    {
        return $this->hasMany(ItemGrid::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date && $this->expiry_date->diffInDays(now()) <= $days && !$this->isExpired();
    }
}
