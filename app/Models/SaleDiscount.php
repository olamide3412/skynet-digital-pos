<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDiscount extends Model
{
    protected $fillable = [
        'branch_id', 'discount_type', 'discount_value', 'start_date_time', 'end_date_time',
        'applies_to', 'description', 'is_apply', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_apply'        => 'boolean',
            'discount_value'  => 'decimal:2',
            'start_date_time' => 'datetime',
            'end_date_time'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->is_apply
            && now()->between($this->start_date_time, $this->end_date_time);
    }
}
