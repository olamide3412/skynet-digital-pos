<?php

namespace App\Modules\CommerceIntegration\Models;

use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommerceReservation extends Model
{
    use HasFactory;

    protected $table = 'commerce_reservations';

    protected $fillable = [
        'commerce_integration_id',
        'organization_id',
        'branch_id',
        'external_order_reference',
        'status',
        'expires_at',
        'confirmed_at',
        'released_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'released_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommerceIntegration::class, 'commerce_integration_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommerceReservationItem::class, 'commerce_reservation_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CommerceInventoryMovement::class, 'commerce_reservation_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '>', now());
    }

    public function scopeExpiredPending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '<=', now());
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->status === 'pending' && $this->expires_at <= now());
    }
}
