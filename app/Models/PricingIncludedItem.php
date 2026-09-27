<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingIncludedItem extends Model
{
    protected $table = 'pricing_included_items';

    protected $fillable = [
        'item_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
