<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MostSaleItem extends Model
{
    protected $table = 'most_sale_items';

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    protected $fillable = ['branch_id', 'user_id', 'item_id', 'qty', 'date_created_at'];

    protected function casts(): array
    {
        return [
            'date_created_at' => 'date',
            'qty'             => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
