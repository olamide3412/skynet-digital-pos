<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\PosSettings;
use App\Models\SaleOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuickAccessService
{
    /** Maximum items enforced at the query level for the widget. */
    public const MAX_ITEMS = 10;

    /**
     * Resolve the Quick Access dataset for the checkout screen.
     */
    public static function getQuickAccessData(?Branch $branch, string $purchaseType = 'Consumer', bool $forceFresh = false): array
    {
        if (!$branch) {
            return [
                'mode'       => 'none',
                'title'      => '',
                'subtitle'   => '',
                'items'      => [],
                'categories' => [],
            ];
        }

        $settings = PosSettings::forBranch($branch->id);
        $mode = $settings->quick_access_mode ?: 'top_sellers';

        if ($mode === 'none') {
            return [
                'mode'       => 'none',
                'title'      => '',
                'subtitle'   => '',
                'items'      => [],
                'categories' => [],
            ];
        }

        $cacheKey = "branch_{$branch->id}_quick_access_{$mode}_{$purchaseType}";

        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 1800, function () use ($branch, $settings, $mode, $purchaseType) {
            return match ($mode) {
                'recently_sold'      => self::resolveRecentlySold($branch, $purchaseType),
                'pinned_items'       => self::resolvePinnedItems($branch, $settings, $purchaseType),
                'category_shortcuts' => self::resolveCategoryShortcuts($branch, $settings, $purchaseType),
                default              => self::resolveTopSellers($branch, $purchaseType),
            };
        });
    }

    /**
     * Mode: Rolling 7 days sales rank.
     */
    protected static function resolveTopSellers(Branch $branch, string $purchaseType): array
    {
        // 1. Query past 7 days sales
        $itemIds = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
            ->where('sales.branch_id', $branch->id)
            ->where('sales.created_at', '>=', now()->subDays(7))
            ->select('sale_orders.item_id', DB::raw('SUM(sale_orders.qty) as total_sold'))
            ->groupBy('sale_orders.item_id')
            ->orderByDesc('total_sold')
            ->limit(self::MAX_ITEMS)
            ->pluck('sale_orders.item_id')
            ->filter()
            ->values()
            ->all();

        // 2. Fallback to all-time sales if fewer than 4 items in past 7 days
        if (count($itemIds) < 4) {
            $allTimeIds = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
                ->where('sales.branch_id', $branch->id)
                ->select('sale_orders.item_id', DB::raw('SUM(sale_orders.qty) as total_sold'))
                ->groupBy('sale_orders.item_id')
                ->orderByDesc('total_sold')
                ->limit(self::MAX_ITEMS)
                ->pluck('sale_orders.item_id')
                ->filter()
                ->values()
                ->all();

            $itemIds = array_values(array_unique(array_merge($itemIds, $allTimeIds)));
            $itemIds = array_slice($itemIds, 0, self::MAX_ITEMS);
        }

        // 3. Fallback to latest active items with stock if still empty
        if (empty($itemIds)) {
            $itemIds = Item::where('branch_id', $branch->id)
                ->where('front_store_qty', '>', 0)
                ->latest()
                ->limit(self::MAX_ITEMS)
                ->pluck('id')
                ->all();
        }

        $items = self::loadAndOrderItems($branch, $itemIds, $purchaseType);

        return [
            'mode'       => 'top_sellers',
            'title'      => '⚡ Top Sellers',
            'subtitle'   => 'Most purchased items this week',
            'items'      => $items,
            'categories' => [],
        ];
    }

    /**
     * Mode: Dynamically recently sold items at this branch.
     */
    protected static function resolveRecentlySold(Branch $branch, string $purchaseType): array
    {
        $itemIds = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
            ->where('sales.branch_id', $branch->id)
            ->orderBy('sales.created_at', 'desc')
            ->limit(60)
            ->pluck('sale_orders.item_id')
            ->filter()
            ->unique()
            ->take(self::MAX_ITEMS)
            ->values()
            ->all();

        if (empty($itemIds)) {
            $itemIds = Item::where('branch_id', $branch->id)
                ->where('front_store_qty', '>', 0)
                ->latest()
                ->limit(self::MAX_ITEMS)
                ->pluck('id')
                ->all();
        }

        $items = self::loadAndOrderItems($branch, $itemIds, $purchaseType);

        return [
            'mode'       => 'recently_sold',
            'title'      => '🕒 Recently Sold',
            'subtitle'   => 'Latest products checked out at this branch',
            'items'      => $items,
            'categories' => [],
        ];
    }

    /**
     * Mode: Branch Admin curated pinned shortcut list.
     */
    protected static function resolvePinnedItems(Branch $branch, PosSettings $settings, string $purchaseType): array
    {
        $pinnedIds = (array) ($settings->pinned_quick_access_items ?: []);
        $pinnedIds = array_slice(array_filter(array_map('intval', $pinnedIds)), 0, self::MAX_ITEMS);

        $items = self::loadAndOrderItems($branch, $pinnedIds, $purchaseType);

        return [
            'mode'       => 'pinned_items',
            'title'      => '📌 Pinned Shortcuts',
            'subtitle'   => 'Quick-access items curated by branch admin',
            'items'      => $items,
            'categories' => [],
        ];
    }

    /**
     * Mode: Top category tiles with quick items grid.
     */
    protected static function resolveCategoryShortcuts(Branch $branch, PosSettings $settings, string $purchaseType): array
    {
        $selectedCatIds = (array) ($settings->quick_access_category_ids ?: []);
        $selectedCatIds = array_filter(array_map('intval', $selectedCatIds));

        $catQuery = Category::where(function ($q) use ($branch) {
            $q->where('branch_id', $branch->id)->orWhereNull('branch_id');
        });

        if (!empty($selectedCatIds)) {
            $catQuery->whereIn('id', $selectedCatIds);
        }

        $categories = $catQuery->orderBy('name')
            ->limit(6)
            ->get();

        $formattedCategories = [];
        foreach ($categories as $cat) {
            $catItems = Item::where('branch_id', $branch->id)
                ->where('category_id', $cat->id)
                ->where('front_store_qty', '>', 0)
                ->with([
                    'category:id,name',
                    'activeUnits',
                    'inStockDeviceUnits' => function ($q) use ($branch) {
                        $q->where('branch_id', $branch->id)->select('id', 'item_id', 'imei_or_device_id', 'location', 'status');
                    }
                ])
                ->limit(8)
                ->get()
                ->map(fn($item) => self::formatItem($item, $purchaseType, $branch))
                ->values()
                ->all();

            if (!empty($catItems)) {
                $formattedCategories[] = [
                    'id'    => $cat->id,
                    'name'  => $cat->name,
                    'items' => $catItems,
                ];
            }
        }

        // Active items defaults to first category's items
        $initialItems = !empty($formattedCategories) ? $formattedCategories[0]['items'] : [];

        return [
            'mode'       => 'category_shortcuts',
            'title'      => '🏷️ Category Shortcuts',
            'subtitle'   => 'Browse top categories and quick items',
            'items'      => $initialItems,
            'categories' => $formattedCategories,
        ];
    }

    /**
     * Load items from database while strictly preserving the provided ID list order.
     */
    protected static function loadAndOrderItems(Branch $branch, array $itemIds, string $purchaseType): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $items = Item::where('branch_id', $branch->id)
            ->whereIn('id', $itemIds)
            ->with([
                'category:id,name',
                'activeUnits',
                'inStockDeviceUnits' => function ($query) use ($branch) {
                    $query->where('branch_id', $branch->id)->select('id', 'item_id', 'imei_or_device_id', 'location', 'status');
                },
            ])
            ->get()
            ->keyBy('id');

        $ordered = [];
        foreach ($itemIds as $id) {
            if (isset($items[$id])) {
                $ordered[] = self::formatItem($items[$id], $purchaseType, $branch);
            }
        }

        return $ordered;
    }

    /**
     * Standardized item formatting identical to ProductSearch for seamless cart addition.
     */
    public static function formatItem(Item $item, string $purchaseType = 'Consumer', ?Branch $branch = null): array
    {
        return array_merge($item->toArray(), [
            'qty'             => $item->front_store_qty,
            'total_qty'       => $item->total_qty,
            'display_price'   => $item->getPriceForUnitLevel('unit', $purchaseType),
            'category_name'   => $item->relationLoaded('category') ? $item->category?->name : null,
            'units'           => $item->relationLoaded('activeUnits') ? $item->activeUnits->map(fn($u) => [
                'id'                     => $u->id,
                'unit_name'              => $u->unit_name,
                'quantity_in_base_units' => (int) $u->quantity_in_base_units,
                'selling_price'          => (float) $u->selling_price,
                'wholesale_price'        => (float) $u->wholesale_price,
                'display_price'          => $item->getPriceForUnitLevel($u->unit_name, $purchaseType, $u->id),
            ])->values()->all() : [],
            'image_url'       => $item->image_url,
            'available_imeis' => ($item->is_imei_tracked && $item->relationLoaded('inStockDeviceUnits'))
                ? $item->inStockDeviceUnits->pluck('imei_or_device_id')->values()->all()
                : [],
        ]);
    }

    /**
     * Flush all quick access caches for a specific branch.
     */
    public static function clearBranchCache(int $branchId): void
    {
        $modes = ['top_sellers', 'recently_sold', 'pinned_items', 'category_shortcuts'];
        $types = ['Consumer', 'Wholesale'];

        foreach ($modes as $mode) {
            foreach ($types as $type) {
                Cache::forget("branch_{$branchId}_quick_access_{$mode}_{$type}");
            }
        }
    }
}
