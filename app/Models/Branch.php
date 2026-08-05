<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    protected $fillable = [
        'name', 'slug', 'address', 'logo_path',
        'phone', 'email', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ── Route model binding via slug ──────────────────────────────────────────
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(PosCustomer::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(PosSettings::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    /**
     * Get or create the POS settings for this branch.
     */
    public function getSettings(): PosSettings
    {
        return $this->settings()->firstOrCreate(
            ['branch_id' => $this->id],
            [
                'sell_interface'  => 'classic',
                'business_sector' => 'commerce',
                'out_of_stock'    => 25,
            ]
        );
    }
}
