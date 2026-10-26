<?php

namespace App\Modules\Laundry\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryOrderItem extends Model
{
    protected $table = 'laundry_order_items';

    protected $fillable = [
        'order_id',
        'service_id',
        'service_name',
        'pricing_type',
        'unit_price',
        'quantity',
        'line_total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity'   => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(LaundryService::class, 'service_id');
    }
}
