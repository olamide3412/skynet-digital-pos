<?php

namespace App\Modules\Laundry\Models;

use App\Models\Branch;
use App\Models\TillSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryPayment extends Model
{
    protected $table = 'laundry_payments';

    protected $fillable = [
        'order_id',
        'branch_id',
        'type', // payment, refund
        'amount',
        'method',
        'till_session_id',
        'recorded_by',
        'paid_at',
        'reference',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount'  => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LaundryOrder::class, 'order_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tillSession(): BelongsTo
    {
        return $this->belongsTo(TillSession::class, 'till_session_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }
}
