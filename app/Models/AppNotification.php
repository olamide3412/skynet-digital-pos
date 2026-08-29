<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'branch_id',
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'reference_type',
        'reference_id',
        'data',
        'is_read',
        'read_at',
        'is_resolved',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'data'        => 'array',
            'is_read'     => 'boolean',
            'read_at'     => 'datetime',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        if (!$branchId) {
            return $query;
        }
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
              ->orWhereNull('branch_id');
        });
    }

    public function scopeForUser(Builder $query, ?int $userId): Builder
    {
        if (!$userId) {
            return $query;
        }
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id');
        });
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function scopeByType(Builder $query, ?string $type): Builder
    {
        if (!$type || $type === 'all') {
            return $query;
        }
        return $query->where('type', $type);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function markAsRead(): self
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
        return $this;
    }

    public function markAsResolved(?int $userId = null): self
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $userId ?? auth()->id(),
            'is_read'     => true,
            'read_at'     => $this->read_at ?? now(),
        ]);
        return $this;
    }
}
