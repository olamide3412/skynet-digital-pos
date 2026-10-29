<?php

namespace App\Modules\Laundry\Models;

use App\Models\Branch;
use App\Models\PosCustomer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaundryOrder extends Model
{
    protected $table = 'laundry_orders';

    protected $fillable = [
        'branch_id',
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'status',
        'received_at',
        'due_at',
        'collected_at',
        'collected_by_user_id',
        'collected_by_name',
        'created_by',
        'subtotal',
        'discount_amount',
        'discount_type',
        'laundry_discount_id',
        'tax_amount',
        'total',
        'amount_paid',
        'balance',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'received_at'     => 'datetime',
            'due_at'          => 'datetime',
            'collected_at'    => 'datetime',
            'subtotal'        => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount'      => 'decimal:2',
            'total'           => 'decimal:2',
            'amount_paid'     => 'decimal:2',
            'balance'         => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LaundryOrder $order) {
            if (!isset($order->attributes['balance']) || $order->attributes['balance'] === null || (float)$order->attributes['balance'] == 0) {
                $paid = (float) ($order->attributes['amount_paid'] ?? 0);
                $total = (float) ($order->attributes['total'] ?? 0);
                if ($total > 0 && $paid == 0) {
                    $order->attributes['balance'] = max(0, round($total - $paid, 2));
                }
            }
        });
    }

    public function getBalanceAttribute($value): float
    {
        $paid = (float) ($this->attributes['amount_paid'] ?? 0);
        $total = (float) ($this->attributes['total'] ?? 0);

        if ($value !== null && $value !== '' && (float)$value > 0) {
            return (float) $value;
        }

        return max(0, round($total - $paid, 2));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(PosCustomer::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LaundryOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LaundryPayment::class, 'order_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LaundryOrderStatusHistory::class, 'order_id')->orderBy('changed_at', 'asc');
    }

    public function isOverdue(): bool
    {
        if (in_array($this->status, ['collected', 'cancelled'])) {
            return false;
        }

        return $this->due_at ? $this->due_at->isPast() : false;
    }

    public function isUnclaimed(int $unclaimedDays = 14): bool
    {
        if ($this->status !== 'ready' || !$this->due_at) {
            return false;
        }

        return $this->due_at->copy()->addDays($unclaimedDays)->isPast();
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function generalDiscount(): BelongsTo
    {
        return $this->belongsTo(LaundryDiscount::class, 'laundry_discount_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(LaundryDiscount::class, 'laundry_discount_id');
    }

    public function getBalanceDueAttribute(): float
    {
        return (float) $this->balance;
    }
}
