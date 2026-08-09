<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $fillable = [
        'branch_id', 'item_id', 'transaction_type', 'qty', 'previous_qty',
        'new_qty', 'location', 'reference_id', 'notes', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'qty'          => 'integer',
            'previous_qty' => 'integer',
            'new_qty'      => 'integer',
        ];
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
