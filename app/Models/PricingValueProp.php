<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingValueProp extends Model
{
    protected $table = 'pricing_value_props';

    protected $fillable = [
        'title',
        'subtext',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
