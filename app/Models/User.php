<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'full_name', 'username', 'email', 'password',
        'is_active', 'is_super_admin', 'branch_id', 'organization_id', 'staff_id',
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
            'is_super_admin'    => 'boolean',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
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

    // ── E-commerce relations (kept for storefront) ────────────────────────────
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function cart(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Cart::class);
    }

    // ── Role/Auth helpers ──────────────────────────────────────────────────────
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isOrganizationAdmin(): bool
    {
        if ($this->organization_id === null) {
            return false;
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $this->id)
            ->where('model_has_roles.model_type', get_class($this))
            ->where('roles.name', 'organization_admin')
            ->exists();
    }

    public function isBranchAdmin(): bool
    {
        return $this->hasRole('branch-admin');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function belongsToBranch(int|Branch $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;
        return $this->branch_id === $branchId;
    }
}
