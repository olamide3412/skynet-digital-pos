<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleOrder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id', 'item_id', 'item_name', 'selling_price',
        'total_selling_price', 'qty', 'purchase_type', 'user_id', 'sort_date',
    ];

    protected function casts(): array
    {
        return [
            'selling_price'       => 'decimal:2',
            'total_selling_price' => 'decimal:2',
            'qty'                 => 'integer',
            'sort_date'           => 'datetime',
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
