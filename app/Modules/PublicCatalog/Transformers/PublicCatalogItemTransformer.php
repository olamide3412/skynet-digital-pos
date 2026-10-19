<?php

namespace App\Modules\PublicCatalog\Transformers;

use App\Models\Branch;
use App\Models\Item;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class PublicCatalogItemTransformer
{
    /**
     * Transform a Branch model into a strict public whitelist array.
     */
    public static function transformBranch(Branch $branch): array
    {
        return [
            'name'     => (string) $branch->name,
            'slug'     => (string) $branch->slug,
            'address'  => $branch->address ? (string) $branch->address : null,
            'phone'    => $branch->phone ? (string) $branch->phone : null,
            'logo_url' => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
        ];
    }

    /**
     * Transform an Item model into a strict public whitelist array.
     * Note: $availableQty is used only server-side to filter which variants appear,
     * and is NEVER passed into the output array.
     */
    public static function transformItem(Item $item, int $availableQty, string $currencySymbol = '₦'): array
    {
        $basePrice = (float) $item->price;
        $baseUnitLabel = !empty($item->unit_label) ? $item->unit_label : 'Unit';

        // Filter variants: only active units where available stock covers at least 1 unit multiplier
        $variants = [];
        if ($item->relationLoaded('units')) {
            $sortedUnits = $item->units->where('status', 'active')->sortBy('sort_order');
            foreach ($sortedUnits as $unit) {
                $multiplier = max(1, (int) $unit->quantity_in_base_units);
                $sellingPrice = (float) $unit->selling_price;

                // Rule: show variant only when available stock >= multiplier and selling_price > 0
                if ($availableQty >= $multiplier && $sellingPrice > 0) {
                    $multiplierLabel = $multiplier > 1
                        ? "({$multiplier} {$baseUnitLabel}s)"
                        : null;

                    $variants[] = [
                        'unit_name'        => (string) $unit->unit_name,
                        'price'            => $sellingPrice,
                        'price_formatted'  => $currencySymbol . number_format($sellingPrice, 2),
                        'multiplier_label' => $multiplierLabel,
                    ];
                }
            }
        }

        return [
            'name'            => (string) ($item->item_name ?? ''),
            'category_name'   => $item->category ? (string) $item->category->name : 'Other',
            'image_url'       => $item->image_path ? asset('storage/' . $item->image_path) : null,
            'base_unit'       => [
                'unit_name'       => $baseUnitLabel,
                'price'           => $basePrice,
                'price_formatted' => $currencySymbol . number_format($basePrice, 2),
            ],
            'variants'        => $variants,
        ];
    }
}
