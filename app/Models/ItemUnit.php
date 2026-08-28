<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUnit extends Model
{
    protected $fillable = [
        'item_id',
        'branch_id',
        'unit_name',
        'quantity_in_base_units',
        'selling_price',
        'wholesale_price',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity_in_base_units' => 'integer',
            'selling_price'          => 'decimal:2',
            'wholesale_price'        => 'decimal:2',
            'sort_order'             => 'integer',
        ];
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
