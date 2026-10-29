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
        'original_price',
        'unit_price',
        'quantity',
        'discount_amount',
        'laundry_discount_id',
        'line_total',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'original_price'  => 'decimal:2',
            'unit_price'       => 'decimal:2',
            'quantity'         => 'float',
            'discount_amount'  => 'decimal:2',
            'line_total'       => 'decimal:2',
        ];
    }

    public function getFormattedQuantityAttribute(): string
    {
        $q = (float) $this->quantity;
        return ($q == (int) $q) ? (string) (int) $q : (string) $q;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(LaundryService::class, 'service_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(LaundryDiscount::class, 'laundry_discount_id');
    }
}
