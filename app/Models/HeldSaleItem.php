<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldSaleItem extends Model
{
    protected $fillable = ['held_sale_id', 'item_id', 'qty', 'price', 'unit_used', 'item_name', 'purchase_type'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'qty'   => 'integer',
        ];
    }

    public function heldSale(): BelongsTo
    {
        return $this->belongsTo(HeldSale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
