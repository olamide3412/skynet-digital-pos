<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'user_id', 'sale_id',
        'reference', 'amount', 'type', 'narration',
        'balance_before', 'balance_after',
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after'  => 'decimal:2',
            'created_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DebtPayment $payment) {
            if (empty($payment->reference)) {
                $payment->reference = 'DPT-' . strtoupper(substr(uniqid(), -8));
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PosCustomer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
