<?php

namespace App\Modules\Laundry\Services;

use App\Modules\Laundry\Models\LaundryDiscount;
use App\Modules\Laundry\Models\LaundryService;
use Carbon\Carbon;

class LaundryDiscountService
{
    /**
     * Resolve service-level promotional discount for a specific laundry service.
     */
    public static function resolveServiceDiscount(LaundryService $service, $time = null): array
    {
        $checkTime = $time ? Carbon::parse($time) : Carbon::now();
        $originalPrice = (float) $service->price;

        $discount = LaundryDiscount::query()
            ->where('branch_id', $service->branch_id)
            ->where('scope', 'service')
            ->where('service_id', $service->id)
            ->where('is_active', true)
            ->where('starts_at', '<=', $checkTime)
            ->where('ends_at', '>=', $checkTime)
            ->latest('id')
            ->first();

        if ($discount) {
            $discountValue = (float) $discount->discount_value;
            if ($discount->discount_type === 'percentage') {
                $discountAmount = round(($originalPrice * min(100.0, $discountValue)) / 100.0, 2);
            } else {
                $discountAmount = round(min($originalPrice, $discountValue), 2);
            }

            $discountAmount = max(0.0, min($originalPrice, $discountAmount));
            $discountedPrice = round(max(0.0, $originalPrice - $discountAmount), 2);

            $label = $discount->name ?: ($discount->discount_type === 'percentage'
                ? "{$discountValue}% Promo"
                : "₦" . number_format($discountValue, 2) . " Promo");

            return [
                'has_discount'     => true,
                'original_price'   => $originalPrice,
                'discounted_price' => $discountedPrice,
                'discount_amount'  => $discountAmount,
                'discount_type'    => $discount->discount_type,
                'discount_value'   => $discountValue,
                'discount_name'    => $label,
                'discount_id'      => $discount->id,
            ];
        }

        return [
            'has_discount'     => false,
            'original_price'   => $originalPrice,
            'discounted_price' => $originalPrice,
            'discount_amount'  => 0.0,
            'discount_type'    => null,
            'discount_value'   => 0.0,
            'discount_name'    => null,
            'discount_id'      => null,
        ];
    }

    /**
     * Get the active general (order-level) store discount for a branch.
     */
    public static function getActiveGeneralDiscount(int $branchId, $time = null): ?LaundryDiscount
    {
        $checkTime = $time ? Carbon::parse($time) : Carbon::now();

        return LaundryDiscount::query()
            ->where('branch_id', $branchId)
            ->where('scope', 'general')
            ->where('is_active', true)
            ->where('starts_at', '<=', $checkTime)
            ->where('ends_at', '>=', $checkTime)
            ->latest('id')
            ->first();
    }
}
