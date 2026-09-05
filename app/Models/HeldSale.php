<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeldSale extends Model
{
    protected $fillable = [
        'branch_id',
        'held_sale_uuid',
        'user_id',
        'hold_name',
        'status',
        'customer_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PosCustomer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HeldSaleItem::class);
    }
}
