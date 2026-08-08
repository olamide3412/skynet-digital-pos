<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    protected $fillable = [
        'branch_id', 'category_id', 'group_address_id',
        'item_name', 'barcode_number',
        'buy_price', 'stock_worth', 'price', 'wholesale_price',
        'pack_price', 'carton_price', 'pack_wholesale_price', 'carton_wholesale_price',
        'price_locked',
        'back_store_qty', 'front_store_qty',
        'unit_label', 'pack_label', 'carton_label',
        'units_per_pack', 'packs_per_carton',
        'expiry_date', 'item_description', 'image_path',
    ];

    protected $appends = ['image_url', 'total_qty'];

    protected function casts(): array
    {
        return [
            'price_locked'           => 'boolean',
            'buy_price'              => 'decimal:2',
            'stock_worth'            => 'decimal:2',
            'price'                  => 'decimal:2',
            'wholesale_price'        => 'decimal:2',
            'pack_price'             => 'decimal:2',
            'carton_price'           => 'decimal:2',
            'pack_wholesale_price'   => 'decimal:2',
            'carton_wholesale_price' => 'decimal:2',
            'expiry_date'            => 'date',
            'back_store_qty'         => 'integer',
            'front_store_qty'        => 'integer',
            'units_per_pack'         => 'integer',
            'packs_per_carton'       => 'integer',
        ];
    }

    // ── Auto-calculate stock_worth on every save ───────────────────────────────
    protected static function booted(): void
    {
        $recalculate = function (Item $item) {
            $buyPrice  = (float) ($item->buy_price ?? 0);
            $totalQty  = ((int) ($item->front_store_qty ?? 0)) + ((int) ($item->back_store_qty ?? 0));
            $item->stock_worth = round($buyPrice * $totalQty, 2);
        };

        static::creating($recalculate);
        static::updating($recalculate);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    /** Combined total stock (back + front) for display/reporting. */
    public function getTotalQtyAttribute(): int
    {
        return (int) (($this->attributes['back_store_qty'] ?? 0) + ($this->attributes['front_store_qty'] ?? 0));
    }

    /** Front store available stock quantity. */
    public function getQtyAttribute(): int
    {
        return (int) ($this->attributes['front_store_qty'] ?? $this->front_store_qty ?? 0);
    }

    /**
     * Get configured price for a unit level ('unit'|'pack'|'carton') and purchase type ('Consumer'|'Wholesale').
     * If explicit pack/carton price is not configured, falls back to linear calculation.
     */
    public function getPriceForUnitLevel(string $unit = 'unit', string $purchaseType = 'Consumer'): float
    {
        $isWholesale = strtolower($purchaseType) === 'wholesale';

        switch (strtolower($unit)) {
            case 'carton':
                if ($isWholesale && $this->carton_wholesale_price > 0) {
                    return (float) $this->carton_wholesale_price;
                }
                if ($this->carton_price > 0) {
                    return (float) $this->carton_price;
                }
                $basePrice = ($isWholesale && $this->wholesale_price > 0) ? $this->wholesale_price : $this->price;
                return (float) ($basePrice * ($this->packs_per_carton ?: 1) * ($this->units_per_pack ?: 1));

            case 'pack':
                if ($isWholesale && $this->pack_wholesale_price > 0) {
                    return (float) $this->pack_wholesale_price;
                }
                if ($this->pack_price > 0) {
                    return (float) $this->pack_price;
                }
                $basePrice = ($isWholesale && $this->wholesale_price > 0) ? $this->wholesale_price : $this->price;
                return (float) ($basePrice * ($this->units_per_pack ?: 1));

            case 'unit':
            default:
                return (float) (($isWholesale && $this->wholesale_price > 0) ? $this->wholesale_price : $this->price);
        }
    }

    // ── Unit conversion helpers ────────────────────────────────────────────────
    /**
     * Convert a quantity in the given unit to base units.
     * @param int    $qty
     * @param string $unit  'unit'|'pack'|'carton'
     */
    public function toBaseUnits(int $qty, string $unit = 'unit'): int
    {
        $unitsPerPack   = max(1, (int) ($this->units_per_pack ?: 1));
        $packsPerCarton = max(1, (int) ($this->packs_per_carton ?: 1));

        return match (strtolower($unit)) {
            'carton' => $qty * $packsPerCarton * $unitsPerPack,
            'pack'   => $qty * $unitsPerPack,
            default  => $qty,
        };
    }

    /**
     * Format a base-unit quantity as human-readable (e.g., "2 cartons 1 pack 3 units").
     */
    public function formatBaseUnits(int $baseQty): string
    {
        $cartonUnits = $this->packs_per_carton * $this->units_per_pack;
        $parts = [];

        if ($cartonUnits > 1 && $baseQty >= $cartonUnits) {
            $cartons = intdiv($baseQty, $cartonUnits);
            $baseQty %= $cartonUnits;
            $parts[] = "{$cartons} {$this->carton_label}";
        }
        if ($this->units_per_pack > 1 && $baseQty >= $this->units_per_pack) {
            $packs = intdiv($baseQty, $this->units_per_pack);
            $baseQty %= $this->units_per_pack;
            $parts[] = "{$packs} {$this->pack_label}";
        }
        if ($baseQty > 0 || empty($parts)) {
            $parts[] = "{$baseQty} {$this->unit_label}";
        }

        return implode(' ', $parts);
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function groupAddress(): BelongsTo
    {
        return $this->belongsTo(GroupAddress::class);
    }

    public function saleOrders(): HasMany
    {
        return $this->hasMany(SaleOrder::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function stockTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class);
    }

    public function itemGrids(): HasMany
    {
        return $this->hasMany(ItemGrid::class);
    }

    // ── Status helpers ─────────────────────────────────────────────────────────
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date
            && $this->expiry_date->diffInDays(now()) <= $days
            && !$this->isExpired();
    }

    public function isLowStock(int $threshold = null): bool
    {
        // Uses branch settings threshold if not passed
        return $this->front_store_qty <= ($threshold ?? 0);
    }
}
