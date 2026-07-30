<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemGrid extends Model
{
    protected $fillable = [
        'item_id', 'menu_name', 'menu_index', 'fore_color', 'back_color', 'font',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
