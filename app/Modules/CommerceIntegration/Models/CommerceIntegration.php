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

    public static function generateClientId(?Branch $branch = null): string
    {
        if ($branch) {
            $slug = str_replace('-', '_', \Illuminate\Support\Str::slug($branch->slug ?: $branch->name, '_'));
            $candidate = 'pos_cli_' . ($slug ?: 'branch') . '_' . $branch->id;

            if (!self::where('client_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        do {
            $clientId = 'pos_cli_' . strtoupper(bin2hex(random_bytes(4)));
        } while (self::where('client_id', $clientId)->exists());

        return $clientId;
    }

    protected static function booted(): void
    {
        static::creating(function ($integration) {
            if (empty($integration->client_id)) {
                $branch = $integration->branch ?: ($integration->branch_id ? Branch::find($integration->branch_id) : null);
                $integration->client_id = self::generateClientId($branch);
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

    /**
     * Extract the raw secret portion of a Sanctum plain-text token (strips "{id}|" prefix if present).
     */
    public static function extractRawTokenSecret(string $plainTextToken): string
    {
        $trimmed = trim(preg_replace('/^Bearer\s+/i', '', trim($plainTextToken)));

        return str_contains($trimmed, '|')
            ? explode('|', $trimmed, 2)[1]
            : $trimmed;
    }

    /**
     * Compute the SHA-256 hash of the raw token secret (matches Sanctum's storage format).
     */
    public static function hashTokenSecret(string $plainTextToken): string
    {
        return hash('sha256', self::extractRawTokenSecret($plainTextToken));
    }

    /**
     * Ensure that if this integration has a stored token (webhook_secret / api_key_hash),
     * its corresponding permanent PersonalAccessToken record exists and never expires.
     */
    public function ensureAccessToken(): void
    {
        $expectedHash = $this->api_key_hash;

        if (empty($expectedHash) && !empty($this->webhook_secret)) {
            $expectedHash = self::hashTokenSecret($this->webhook_secret);
            $this->forceFill(['api_key_hash' => $expectedHash])->saveQuietly();
        }

        if (empty($expectedHash)) {
            return;
        }

        $existingToken = $this->tokens()->where('token', $expectedHash)->first();

        if ($existingToken) {
            if ($existingToken->expires_at !== null) {
                $existingToken->forceFill(['expires_at' => null])->saveQuietly();
            }
            return;
        }

        // Self-heal missing PersonalAccessToken row so tokens can never be wiped out
        $this->tokens()->create([
            'name'       => 'commerce_api_access',
            'token'      => $expectedHash,
            'abilities'  => ['commerce:full'],
            'expires_at' => null,
        ]);
    }
}
