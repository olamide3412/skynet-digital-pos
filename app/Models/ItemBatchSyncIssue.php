<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemBatchSyncIssue extends Model
{
    use HasFactory;

    protected $table = 'item_batch_sync_issues';

    protected $fillable = [
        'branch_id',
        'item_id',
        'action_type',
        'requested_qty',
        'available_batch_qty',
        'reason',
        'reference_id',
        'status', // 'logged', 'resolved'
    ];

    protected function casts(): array
    {
        return [
            'requested_qty'       => 'integer',
            'available_batch_qty' => 'integer',
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

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeLogged(Builder $query): Builder
    {
        return $query->where('status', 'logged');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function resolve(): void
    {
        $this->update(['status' => 'resolved']);
    }
}
