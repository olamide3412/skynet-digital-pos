<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBatch extends Model
{
    use HasFactory;

    protected $table = 'item_batches';

    protected $fillable = [
        'item_id',
        'branch_id',
        'batch_label',
        'quantity',
        'expiry_date',
        'received_date',
        'cost_price',
        'status', // 'active', 'expired', 'cleared'
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'integer',
            'expiry_date'   => 'date',
            'received_date' => 'date',
            'cost_price'    => 'decimal:2',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // ── Status & Expiry Helpers ───────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expiry_date && Carbon::parse($this->expiry_date)->startOfDay()->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->isExpired() || !$this->expiry_date) {
            return false;
        }

        $now = Carbon::now()->startOfDay();
        $expiry = Carbon::parse($this->expiry_date)->startOfDay();
        $diff = $now->diffInDays($expiry, false);

        return $diff >= 0 && $diff <= $days;
    }

    public function daysUntilExpiry(): int
    {
        if (!$this->expiry_date) {
            return 0;
        }

        $now = Carbon::now()->startOfDay();
        $expiry = Carbon::parse($this->expiry_date)->startOfDay();

        return (int) $now->diffInDays($expiry, false);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('quantity', '>', 0);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        $today = Carbon::today()->toDateString();
        $targetDate = Carbon::today()->addDays($days)->toDateString();

        return $query->where('status', 'active')
            ->where('quantity', '>', 0)
            ->whereBetween('expiry_date', [$today, $targetDate]);
    }

    public function scopeExpired(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->where('status', '!=', 'cleared')
            ->where('expiry_date', '<', $today);
    }
}
