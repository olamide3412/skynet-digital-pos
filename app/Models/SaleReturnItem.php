<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_id', 'item_id', 'item_name', 'qty', 'unit_used', 'price',
        'total_price', 'purchase_type', 'return_reason', 'refund_amount', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'price'         => 'decimal:2',
            'total_price'   => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'qty'           => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
