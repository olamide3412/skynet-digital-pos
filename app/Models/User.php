<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'full_name', 'username', 'email', 'password',
        'phone', 'address', 'role', 'status',
        'is_active', 'is_admin', 'acct_tier', 'staff_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'is_admin'          => 'boolean',
            'acct_tier'         => 'integer',
        ];
    }

    // ── E-commerce relations (keep) ───────────────────────────────────
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    // ── POS relations ────────────────────────────────────────────────
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'users_roles', 'user_id', 'role_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function heldSales(): HasMany
    {
        return $this->hasMany(HeldSale::class);
    }

    public function posLogs(): HasMany
    {
        return $this->hasMany(PosLog::class);
    }

    // ── Role helpers ─────────────────────────────────────────────────
    public function hasRole(string $role): bool
    {
        if ($this->is_admin) return true;
        return $this->roles()->where('role', $role)->exists();
    }

    public function isTier(int $min): bool
    {
        return $this->acct_tier >= $min;
    }
}
