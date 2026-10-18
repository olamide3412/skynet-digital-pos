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
        'price_locked', 'is_imei_tracked', 'batch_tracking_enabled',
        'back_store_qty', 'front_store_qty',
        'reorder_point', 'reorder_unit',
        'unit_label',
        'expiry_date', 'item_description', 'image_path',
    ];

    protected $appends = ['image_url', 'total_qty', 'needs_reorder', 'reorder_deficit_base_units'];

    protected function casts(): array
    {
        return [
            'price_locked'           => 'boolean',
            'is_imei_tracked'        => 'boolean',
            'batch_tracking_enabled' => 'boolean',
            'buy_price'              => 'decimal:2',
            'stock_worth'            => 'decimal:2',
            'price'                  => 'decimal:2',
            'wholesale_price'        => 'decimal:2',
            'expiry_date'            => 'date',
            'back_store_qty'         => 'integer',
            'front_store_qty'        => 'integer',
            'reorder_point'          => 'integer',
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

        static::deleting(function (Item $item) {
            // Guard 1: Active reservations
            $hasActiveReservation = \Illuminate\Support\Facades\DB::table('commerce_reservation_items')
                ->join('commerce_reservations', 'commerce_reservation_items.commerce_reservation_id', '=', 'commerce_reservations.id')
                ->where('commerce_reservation_items.item_id', $item->id)
                ->where('commerce_reservations.status', 'pending')
                ->where('commerce_reservations.expires_at', '>', now())
                ->exists();

            if ($hasActiveReservation) {
                throw new \RuntimeException("Cannot delete item '{$item->name}' because it has active e-commerce reservations.");
            }

            // Guard 2: Active catalog mappings
            $hasMapping = \Illuminate\Support\Facades\DB::table('commerce_item_mappings')
                ->where('item_id', $item->id)
                ->exists();

            if ($hasMapping) {
                throw new \RuntimeException("Cannot delete item '{$item->name}' because it is mapped to an external e-commerce catalog. Please remove the e-commerce mapping first.");
            }
        });
    }

    // ── Accessors ─────────────────────────────────────────────────────────────
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function getNameAttribute(): string
    {
        return (string) ($this->attributes['item_name'] ?? '');
    }

    public function getBarcodeAttribute(): ?string
    {
        return $this->attributes['barcode_number'] ?? null;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->attributes['item_description'] ?? null;
    }

    /** Alias for price to support code referencing unit_price. */
    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->price ?? 0);
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
     * Get configured price for a unit level and purchase type ('Consumer'|'Wholesale').
     * Checks item_units variants first, then falls back to base pricing.
     */
    public function getPriceForUnitLevel(string $unit = 'unit', string $purchaseType = 'Consumer', ?int $unitId = null): float
    {
        $isWholesale = strtolower($purchaseType) === 'wholesale';
        $normalizedUnit = strtolower(trim($unit));

        // 1. Try finding in active item_units
        $itemUnit = null;
        if ($unitId) {
            $itemUnit = $this->relationLoaded('units')
                ? $this->units->firstWhere('id', $unitId)
                : $this->units()->where('id', $unitId)->first();
        }

        if (!$itemUnit && $normalizedUnit !== 'unit' && $normalizedUnit !== strtolower($this->unit_label ?? 'unit')) {
            $itemUnit = $this->relationLoaded('units')
                ? $this->units->first(fn($u) => strtolower($u->unit_name) === $normalizedUnit && $u->status === 'active')
                : $this->units()->whereRaw('LOWER(unit_name) = ?', [$normalizedUnit])->where('status', 'active')->first();
        }

        if ($itemUnit) {
            if ($isWholesale && (float) $itemUnit->wholesale_price > 0) {
                return (float) $itemUnit->wholesale_price;
            }
            return (float) $itemUnit->selling_price;
        }

        return (float) (($isWholesale && $this->wholesale_price > 0) ? $this->wholesale_price : $this->price);
    }

    // ── Unit conversion helpers ────────────────────────────────────────────────
    /**
     * Convert a quantity in the given unit to base units.
     * @param int         $qty
     * @param string      $unit   Unit name or 'unit'
     * @param int|null    $unitId Optional ItemUnit PK
     */
    public function toBaseUnits(int $qty, string $unit = 'unit', ?int $unitId = null): int
    {
        $normalizedUnit = strtolower(trim($unit));

        // Base unit is always 1:1
        if ($normalizedUnit === 'unit' || $normalizedUnit === strtolower($this->unit_label ?? 'unit')) {
            return $qty;
        }

        // Try finding in item_units
        $itemUnit = null;
        if ($unitId) {
            $itemUnit = $this->relationLoaded('units')
                ? $this->units->firstWhere('id', $unitId)
                : $this->units()->where('id', $unitId)->first();
        }

        if (!$itemUnit) {
            $itemUnit = $this->relationLoaded('units')
                ? $this->units->first(fn($u) => strtolower($u->unit_name) === $normalizedUnit)
                : $this->units()->whereRaw('LOWER(unit_name) = ?', [$normalizedUnit])->first();
        }

        if ($itemUnit) {
            return $qty * max(1, (int) $itemUnit->quantity_in_base_units);
        }

        return $qty;
    }

    /**
     * Format a base-unit quantity as human-readable.
     */
    public function formatBaseUnits(int $baseQty): string
    {
        $parts = [];
        $remaining = $baseQty;

        // If custom units exist in descending order of size
        $units = $this->relationLoaded('units')
            ? $this->units->where('status', 'active')->sortByDesc('quantity_in_base_units')
            : $this->activeUnits()->orderByDesc('quantity_in_base_units')->get();

        if ($units->isNotEmpty()) {
            foreach ($units as $u) {
                $multiplier = (int) $u->quantity_in_base_units;
                if ($multiplier > 1 && $remaining >= $multiplier) {
                    $count = intdiv($remaining, $multiplier);
                    $remaining %= $multiplier;
                    $parts[] = "{$count} {$u->unit_name}";
                }
            }
        }

        if ($remaining > 0 || empty($parts)) {
            $parts[] = "{$remaining} " . ($this->unit_label ?: 'Unit');
        }

        return implode(' ', $parts);
    }

    // ── Relations ─────────────────────────────────────────────────────────────
    public function units(): HasMany
    {
        return $this->hasMany(ItemUnit::class)->orderBy('sort_order', 'asc');
    }

    public function activeUnits(): HasMany
    {
        return $this->hasMany(ItemUnit::class)->where('status', 'active')->orderBy('sort_order', 'asc');
    }

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

    public function productGroupItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductGroupItem::class);
    }

    public function itemGrids(): HasMany
    {
        return $this->hasMany(ItemGrid::class);
    }

    public function barcodePrintLogs(): HasMany
    {
        return $this->hasMany(BarcodePrintLog::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ItemBatch::class);
    }

    public function batchSyncIssues(): HasMany
    {
        return $this->hasMany(ItemBatchSyncIssue::class);
    }

    public function getActiveBatchesTotalAttribute(): int
    {
        return (int) $this->batches()->where('status', 'active')->sum('quantity');
    }

    public function getBatchVarianceAttribute(): int
    {
        $actualStock = ((int) ($this->front_store_qty ?? 0)) + ((int) ($this->back_store_qty ?? 0));
        return $actualStock - $this->active_batches_total;
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

    // ── Reorder Point Accessors ───────────────────────────────────────────────
    public function getReorderPointBaseUnitsAttribute(): int
    {
        $point = (int) ($this->reorder_point ?? 10);
        $unit  = strtolower($this->reorder_unit ?? 'unit');
        return $this->toBaseUnits($point, $unit);
    }

    public function getNeedsReorderAttribute(): bool
    {
        $totalStockBase = (int) ($this->front_store_qty ?? 0) + (int) ($this->back_store_qty ?? 0);
        return $totalStockBase <= $this->reorder_point_base_units;
    }

    public function getReorderDeficitBaseUnitsAttribute(): int
    {
        $totalStockBase = (int) ($this->front_store_qty ?? 0) + (int) ($this->back_store_qty ?? 0);
        $targetBase     = $this->reorder_point_base_units;
        return max(0, $targetBase - $totalStockBase);
    }

    public function isLowStock(int $threshold = null): bool
    {
        // Uses branch settings threshold if not passed
        return $this->front_store_qty <= ($threshold ?? 0);
    }

    public function deviceUnits(): HasMany
    {
        return $this->hasMany(ItemDeviceUnit::class);
    }

    public function inStockDeviceUnits(): HasMany
    {
        return $this->hasMany(ItemDeviceUnit::class)->where('status', 'in_stock');
    }

    public function itemDiscounts(): HasMany
    {
        return $this->hasMany(ItemDiscount::class);
    }

    public function activeItemDiscount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ItemDiscount::class)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->latestOfMany();
    }

    public function discountGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DiscountGroup::class, 'discount_group_items')
            ->withTimestamps();
    }
}

