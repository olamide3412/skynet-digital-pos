<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InterBranchTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'reference',
        'source_branch_id',
        'destination_branch_id',
        'from_location',
        'to_location',
        'status',
        'blocked_reason',
        'notes',
        'sent_by_user_id',
        'accepted_by_user_id',
        'cancelled_by_user_id',
        'sent_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'      => 'datetime',
            'accepted_at'  => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InterBranchTransferItem::class);
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->items->sum(function ($item) {
            return (float) $item->sender_cost_price * (int) $item->quantity_sent;
        });
    }

    public function getReceivedCostAttribute(): float
    {
        return (float) $this->items->sum(function ($item) {
            $qty = $item->quantity_received ?? 0;
            return (float) $item->sender_cost_price * (int) $qty;
        });
    }

    public function getTotalShortfallQtyAttribute(): int
    {
        return (int) $this->items->sum('shortfall_quantity');
    }
}
