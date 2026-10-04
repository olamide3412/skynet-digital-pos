<?php

namespace App\Modules\CommerceIntegration\Models;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class CommerceIntegration extends Model
{
    use HasFactory, HasApiTokens;

    protected $table = 'commerce_integrations';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'name',
        'platform',
        'status',
        'client_id',
        'api_key_hash',
        'settings',
        'sync_cursor',
        'last_synced_at',
        'webhook_url',
        'webhook_secret',
        'created_by',
    ];

    public static function generateClientId(): string
    {
        do {
            $clientId = 'pos_cli_' . strtoupper(bin2hex(random_bytes(4)));
        } while (self::where('client_id', $clientId)->exists());

        return $clientId;
    }

    protected static function booted(): void
    {
        static::creating(function ($integration) {
            if (empty($integration->client_id)) {
                $integration->client_id = self::generateClientId();
            }
        });
    }

    protected $casts = [
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(CommerceReservation::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CommerceInventoryMovement::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(CommerceItemMapping::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function getReservationTtlMinutes(): int
    {
        $customTtl = $this->settings['reservation_ttl_minutes'] ?? null;
        if ($customTtl && is_numeric($customTtl) && $customTtl > 0) {
            return (int) min($customTtl, config('commerce.max_reservation_ttl_minutes', 120));
        }

        return (int) config('commerce.reservation_ttl_minutes', 30);
    }
}
