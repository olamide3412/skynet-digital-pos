<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiCreditPack extends Model
{
    protected $table = 'ai_credit_packs';

    protected $fillable = [
        'name',
        'credits',
        'price',
        'currency',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'credits'    => 'integer',
        'price'      => 'decimal:2',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function topups(): HasMany
    {
        return $this->hasMany(AiCreditTopup::class, 'credit_pack_id');
    }

    /**
     * Scope to active packs sorted by sort_order.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('credits');
    }
}
