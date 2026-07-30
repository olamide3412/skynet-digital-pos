<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceivedItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'user_id',
        'qty',
        'cost',
        'received_date',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'cost'          => 'decimal:2',
            'received_date' => 'date',
            'expiry_date'   => 'date',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
