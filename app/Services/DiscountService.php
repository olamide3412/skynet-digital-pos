<?php

namespace App\Services;

use App\Models\DiscountGroup;
use App\Models\Item;
use App\Models\ItemDiscount;
use Carbon\Carbon;

class DiscountService
{
    /**
     * Resolve line-item discount and final unit price following strict hierarchy:
     * 1. Item Discount (Priority 1)
     * 2. Group Discount (Priority 2, only if no Item Discount applies)
     *
     * @param Item $item
     * @param string $unit
     * @param string $purchaseType
     * @param Carbon|string|null $time
     * @param int|null $unitId
     * @return array
     */
    public static function resolveItemDiscount(
        Item $item,
        string $unit = 'unit',
        string $purchaseType = 'Consumer',
        $time = null,
        ?int $unitId = null
    ): array {
        $checkTime = $time ? Carbon::parse($time) : now();
        $originalPrice = (float) $item->getPriceForUnitLevel($unit, $purchaseType, $unitId);
        $multiplier = static::getMultiplierForItemUnit($item, $unit, $unitId);

        // ── 1. Priority 1: Item-Level Discount ──────────────────────────────
        $itemDiscount = ItemDiscount::where('item_id', $item->id)
            ->where('is_active', true)
            ->where('starts_at', '<=', $checkTime)
            ->where('ends_at', '>=', $checkTime)
            ->latest('id')
            ->first();

        if ($itemDiscount) {
            $discountValue = (float) $itemDiscount->discount_value;
            if ($itemDiscount->discount_type === 'percentage') {
                $discountAmount = round(($originalPrice * $discountValue) / 100, 2);
            } else {
                // Fixed discount per base unit scales with unit multiplier
                $discountAmount = round($discountValue * $multiplier, 2);
            }

            $discountAmount = min($originalPrice, max(0, $discountAmount));
            $discountedPrice = round(max(0, $originalPrice - $discountAmount), 2);

            $label = $itemDiscount->discount_type === 'percentage'
                ? "{$discountValue}% Item Promo"
                : "₦" . number_format($discountValue, 2) . " Item Promo";

            return [
                'original_price'   => $originalPrice,
                'discounted_price' => $discountedPrice,
                'discount_amount'  => $discountAmount,
                'discount_layer'   => 'item',
                'discount_type'    => $itemDiscount->discount_type,
                'discount_value'   => $discountValue,
                'discount_name'    => $label,
                'discount_id'      => $itemDiscount->id,
            ];
        }

        // ── 2. Priority 2: Group-Level Discount (Only if no Item Discount) ───
        $groupDiscount = DiscountGroup::whereHas('items', fn($q) => $q->where('items.id', $item->id))
            ->where('is_active', true)
            ->where('starts_at', '<=', $checkTime)
            ->where('ends_at', '>=', $checkTime)
            ->latest('id')
            ->first();

        if ($groupDiscount) {
            $discountValue = (float) $groupDiscount->discount_value;
            if ($groupDiscount->discount_type === 'percentage') {
                $discountAmount = round(($originalPrice * $discountValue) / 100, 2);
            } else {
                // Fixed discount per base unit scales with unit multiplier
                $discountAmount = round($discountValue * $multiplier, 2);
            }

            $discountAmount = min($originalPrice, max(0, $discountAmount));
            $discountedPrice = round(max(0, $originalPrice - $discountAmount), 2);

            $label = $groupDiscount->name . ($groupDiscount->discount_type === 'percentage'
                ? " ({$discountValue}%)"
                : " (₦" . number_format($discountValue, 2) . ")");

            return [
                'original_price'   => $originalPrice,
                'discounted_price' => $discountedPrice,
                'discount_amount'  => $discountAmount,
                'discount_layer'   => 'group',
                'discount_type'    => $groupDiscount->discount_type,
                'discount_value'   => $discountValue,
                'discount_name'    => $label,
                'discount_id'      => $groupDiscount->id,
            ];
        }

        // ── 3. No Line-Level Discount ────────────────────────────────────────
        return [
            'original_price'   => $originalPrice,
            'discounted_price' => $originalPrice,
            'discount_amount'  => 0.0,
            'discount_layer'   => null,
            'discount_type'    => null,
            'discount_value'   => 0.0,
            'discount_name'    => null,
            'discount_id'      => null,
        ];
    }

    /**
     * Get multiplier (number of base units) for a given unit variant.
     */
    public static function getMultiplierForItemUnit(Item $item, string $unit = 'unit', ?int $unitId = null): int
    {
        $normalizedUnit = strtolower(trim($unit));
        if ($normalizedUnit === 'unit' || $normalizedUnit === strtolower($item->unit_label ?? 'unit')) {
            return 1;
        }

        $unitsCollection = $item->relationLoaded('units') ? $item->units : $item->activeUnits;
        if (!$unitsCollection || $unitsCollection->isEmpty()) {
            return 1;
        }

        if ($unitId) {
            $matched = $unitsCollection->firstWhere('id', $unitId);
            if ($matched) {
                return (int) ($matched->quantity_in_base_units ?: 1);
            }
        }

        $matched = $unitsCollection->first(fn($u) => strtolower($u->unit_name) === $normalizedUnit);
        if ($matched) {
            return (int) ($matched->quantity_in_base_units ?: 1);
        }

        return 1;
    }
}
