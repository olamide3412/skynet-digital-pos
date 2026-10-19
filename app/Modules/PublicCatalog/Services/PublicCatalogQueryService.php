<?php

namespace App\Modules\PublicCatalog\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\SystemSetting;
use App\Modules\CommerceIntegration\Services\InventoryAvailabilityService;
use App\Modules\PublicCatalog\Transformers\PublicCatalogItemTransformer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PublicCatalogQueryService
{
    public function __construct(
        protected ?InventoryAvailabilityService $inventoryService = null
    ) {
        $this->inventoryService = $inventoryService ?? new InventoryAvailabilityService();
    }

    /**
     * Resolve an active branch with public catalog enabled.
     * NEVER cached: evaluated on every live request.
     */
    public function resolveBranch(string $slug): ?Branch
    {
        return Branch::where('slug', $slug)
            ->where('is_active', true)
            ->where('public_catalog_enabled', true)
            ->first();
    }

    /**
     * Get the catalog data (categories, items, pagination, currency) for a branch.
     * Cached per branch and query parameters for short TTL (60s).
     */
    public function getCatalogData(Branch $branch, array $filters = []): array
    {
        $search   = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $sort     = trim((string) ($filters['sort'] ?? 'name_asc'));
        $page     = max(1, (int) ($filters['page'] ?? 1));

        $defaultPerPage = (int) config('public_catalog.default_per_page', 24);
        $maxPerPage     = (int) config('public_catalog.max_per_page', 48);
        $perPage        = min($maxPerPage, max(1, (int) ($filters['per_page'] ?? $defaultPerPage)));

        $ttl = (int) config('public_catalog.cache_ttl_seconds', 60);

        $themeConfig = $this->getThemeConfig();
        $themeKey    = $themeConfig['key'] ?? 'skynet';

        // Normalize filter values for consistent cache key
        $cacheKey = sprintf(
            'public_catalog:branch:%d:t:%s:q:%s:cat:%s:sort:%s:p:%d:pp:%d',
            $branch->id,
            $themeKey,
            md5(strtolower($search)),
            $category,
            $sort,
            $page,
            $perPage
        );

        return Cache::remember($cacheKey, $ttl, function () use ($branch, $search, $category, $sort, $page, $perPage, $themeConfig) {
            $data = $this->buildCatalogData($branch, $search, $category, $sort, $page, $perPage);
            $data['theme'] = $themeConfig;
            return $data;
        });
    }

    /**
     * Execute the query and transform data without exposing internal models.
     */
    protected function buildCatalogData(Branch $branch, string $search, string $category, string $sort, int $page, int $perPage): array
    {
        $currencySymbol = (string) SystemSetting::get('currency_symbol', '₦');
        $companyName    = (string) SystemSetting::get('company_name', config('app.name', 'Skynet POS'));

        // 1. Query all eligible base items for this branch
        // Criteria (§6):
        // - active item
        // - price > 0
        // - not hidden
        // - front_store_qty > 0
        // - not blocked by expiry_date
        $query = Item::where('branch_id', $branch->id)
            ->where('hide_from_public_catalog', false)
            ->where('price', '>', 0)
            ->where('front_store_qty', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', now()->toDateString());
            });

        // 2. Fetch candidate items with units and category
        $items = $query->with([
            'category:id,name',
            'units' => fn($q) => $q->where('status', 'active')->orderBy('sort_order', 'asc'),
        ])->get();

        if ($items->isEmpty()) {
            return [
                'branch'         => PublicCatalogItemTransformer::transformBranch($branch),
                'items'          => [],
                'categories'     => [],
                'total_count'    => 0,
                'current_page'   => 1,
                'last_page'      => 1,
                'per_page'       => $perPage,
                'from'           => 0,
                'to'             => 0,
                'currency_symbol'=> $currencySymbol,
                'company_name'   => $companyName,
                'filters'        => [
                    'search'   => $search,
                    'category' => $category,
                    'sort'     => $sort,
                ],
            ];
        }

        // 3. Calculate accurate available front-store stock for each candidate item
        // (handling IMEI count and ecommerce active reservations)
        $itemIds = $items->pluck('id')->all();
        $batchStock = $this->inventoryService->getBatchAvailableStock($itemIds, $branch->id);

        // Fetch IMEI counts for IMEI-tracked candidate items
        $imeiItemIds = $items->where('is_imei_tracked', true)->pluck('id')->all();
        $imeiCounts = [];
        if (!empty($imeiItemIds)) {
            $imeiCounts = ItemDeviceUnit::where('branch_id', $branch->id)
                ->whereIn('item_id', $imeiItemIds)
                ->where('status', 'in_stock')
                ->where('location', 'front_store')
                ->groupBy('item_id')
                ->select('item_id', DB::raw('COUNT(*) as total'))
                ->pluck('total', 'item_id')
                ->all();
        }

        // 4. Filter strictly eligible items (available stock >= 1) and collect available categories
        /** @var Collection<int, array{item: Item, available_qty: int}> $eligibleItems */
        $eligibleItems = collect();
        $categoryMap = [];
        $hasOtherCategory = false;

        foreach ($items as $item) {
            if ($item->is_imei_tracked) {
                $available = (int) ($imeiCounts[$item->id] ?? 0);
            } else {
                $stockData = $batchStock->get($item->id);
                $available = $stockData ? (int) $stockData['available_qty'] : (int) $item->front_store_qty;
            }

            // §6: Available stock must be at least 1 base unit
            if ($available < 1) {
                continue;
            }

            $eligibleItems->push([
                'item'          => $item,
                'available_qty' => $available,
            ]);

            // Track categories with visible items
            if ($item->category) {
                $categoryMap[$item->category->id] = (string) $item->category->name;
            } else {
                $hasOtherCategory = true;
            }
        }

        // Build sorted categories list
        asort($categoryMap);
        $categoriesList = [];
        foreach ($categoryMap as $id => $name) {
            $categoriesList[] = ['id' => (string) $id, 'name' => $name];
        }
        if ($hasOtherCategory) {
            $categoriesList[] = ['id' => 'other', 'name' => 'Other'];
        }

        // 5. Apply Search filter
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $eligibleItems = $eligibleItems->filter(function ($entry) use ($searchLower) {
                $name = mb_strtolower($entry['item']->item_name ?? '');
                return str_contains($name, $searchLower);
            });
        }

        // 6. Apply Category filter
        if ($category !== '' && $category !== 'all') {
            $eligibleItems = $eligibleItems->filter(function ($entry) use ($category) {
                if ($category === 'other') {
                    return empty($entry['item']->category_id);
                }
                return (string) $entry['item']->category_id === (string) $category;
            });
        }

        // 7. Apply Sorting
        $eligibleItems = match ($sort) {
            'name_desc'  => $eligibleItems->sortByDesc(fn($entry) => mb_strtolower($entry['item']->item_name ?? '')),
            'price_asc'  => $eligibleItems->sortBy(fn($entry) => (float) $entry['item']->price),
            'price_desc' => $eligibleItems->sortByDesc(fn($entry) => (float) $entry['item']->price),
            default      => $eligibleItems->sortBy(fn($entry) => mb_strtolower($entry['item']->item_name ?? '')),
        };

        // 8. Pagination
        $total = $eligibleItems->count();
        $offset = ($page - 1) * $perPage;
        $pagedSlice = $eligibleItems->slice($offset, $perPage)->values();

        // 9. Transform items through strict whitelist transformer
        $transformedItems = $pagedSlice->map(function ($entry) use ($currencySymbol) {
            return PublicCatalogItemTransformer::transformItem(
                $entry['item'],
                $entry['available_qty'],
                $currencySymbol
            );
        })->all();

        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = $total > 0 ? $offset + 1 : 0;
        $to   = min($total, $offset + $perPage);

        return [
            'branch'         => PublicCatalogItemTransformer::transformBranch($branch),
            'items'          => $transformedItems,
            'categories'     => $categoriesList,
            'total_count'    => $total,
            'current_page'   => $page,
            'last_page'      => $lastPage,
            'per_page'       => $perPage,
            'from'           => $from,
            'to'             => $to,
            'currency_symbol'=> $currencySymbol,
            'company_name'   => $companyName,
            'theme'          => $this->getThemeConfig(),
            'filters'        => [
                'search'   => $search,
                'category' => $category,
                'sort'     => $sort,
            ],
        ];
    }

    /**
     * Resolve the active system color theme configuration for public catalog styling.
     */
    public function getThemeConfig(): array
    {
        $themeKey          = (string) SystemSetting::get('primary_color_theme', 'skynet');
        $customPrimary     = (string) SystemSetting::get('custom_primary_hex', '#7B00FF');
        $customSecondary   = (string) SystemSetting::get('custom_secondary_hex', '#FBA43D');
        $darkModeAccent    = (string) SystemSetting::get('dark_mode_accent_mode', 'white');
        $customDarkPrimary = (string) SystemSetting::get('custom_dark_primary_hex', '#FFFFFF');

        $palettes = [
            'skynet' => [
                'theme'            => '#7b00ff',
                'hover'            => '#6200cc',
                'light'            => '#f2e6ff',
                'rgb'              => '123, 0, 255',
                'text'             => '#5900b3',
                'dark'             => '#c084fc',
                'secondary'        => '#fba43d',
                'secondary_hover'  => '#e08b26',
            ],
            'emerald' => [
                'theme'            => '#10b981',
                'hover'            => '#059669',
                'light'            => '#d1fae5',
                'rgb'              => '16, 185, 129',
                'text'             => '#047857',
                'dark'             => '#34d399',
                'secondary'        => '#f59e0b',
                'secondary_hover'  => '#d97706',
            ],
            'blue' => [
                'theme'            => '#3b82f6',
                'hover'            => '#2563eb',
                'light'            => '#dbeafe',
                'rgb'              => '59, 130, 246',
                'text'             => '#1d4ed8',
                'dark'             => '#60a5fa',
                'secondary'        => '#f59e0b',
                'secondary_hover'  => '#d97706',
            ],
            'indigo' => [
                'theme'            => '#6366f1',
                'hover'            => '#4f46e5',
                'light'            => '#e0e7ff',
                'rgb'              => '99, 102, 241',
                'text'             => '#4338ca',
                'dark'             => '#818cf8',
                'secondary'        => '#ec4899',
                'secondary_hover'  => '#db2777',
            ],
            'violet' => [
                'theme'            => '#8b5cf6',
                'hover'            => '#7c3aed',
                'light'            => '#ede9fe',
                'rgb'              => '139, 92, 246',
                'text'             => '#6d28d9',
                'dark'             => '#a78bfa',
                'secondary'        => '#f59e0b',
                'secondary_hover'  => '#d97706',
            ],
            'teal' => [
                'theme'            => '#14b8a6',
                'hover'            => '#0d9488',
                'light'            => '#ccfbf1',
                'rgb'              => '20, 184, 166',
                'text'             => '#0f766e',
                'dark'             => '#2dd4bf',
                'secondary'        => '#f97316',
                'secondary_hover'  => '#ea580c',
            ],
            'cyan' => [
                'theme'            => '#06b6d4',
                'hover'            => '#0891b2',
                'light'            => '#cffafe',
                'rgb'              => '6, 182, 212',
                'text'             => '#0e7490',
                'dark'             => '#22d3ee',
                'secondary'        => '#f59e0b',
                'secondary_hover'  => '#d97706',
            ],
            'amber' => [
                'theme'            => '#f59e0b',
                'hover'            => '#d97706',
                'light'            => '#fef3c7',
                'rgb'              => '245, 158, 11',
                'text'             => '#b45309',
                'dark'             => '#fbbf24',
                'secondary'        => '#6366f1',
                'secondary_hover'  => '#4f46e5',
            ],
            'rose' => [
                'theme'            => '#f43f5e',
                'hover'            => '#e11d48',
                'light'            => '#ffe4e6',
                'rgb'              => '244, 63, 94',
                'text'             => '#be123c',
                'dark'             => '#fb7185',
                'secondary'        => '#3b82f6',
                'secondary_hover'  => '#2563eb',
            ],
        ];

        if ($themeKey === 'custom') {
            $hex = ltrim($customPrimary, '#');
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }
            $r = hexdec(substr($hex, 0, 2)) ?: 123;
            $g = hexdec(substr($hex, 2, 2)) ?: 0;
            $b = hexdec(substr($hex, 4, 2)) ?: 255;
            $hover = sprintf('#%02x%02x%02x', max(0, (int)($r * 0.85)), max(0, (int)($g * 0.85)), max(0, (int)($b * 0.85)));
            $light = sprintf('#%02x%02x%02x', min(255, (int)($r * 0.15 + 255 * 0.85)), min(255, (int)($g * 0.15 + 255 * 0.85)), min(255, (int)($b * 0.15 + 255 * 0.85)));

            $darkHex = $darkModeAccent === 'custom' && $customDarkPrimary ? $customDarkPrimary : ($customDarkPrimary ?: sprintf('#%02x%02x%02x', min(255, (int)($r * 1.35) + 40), min(255, (int)($g * 1.35) + 40), min(255, (int)($b * 1.35) + 40)));

            return [
                'key'             => 'custom',
                'theme'           => '#' . $hex,
                'hover'           => $hover,
                'light'           => $light,
                'rgb'             => "$r, $g, $b",
                'text'            => $hover,
                'dark'            => $darkHex,
                'secondary'       => $customSecondary,
                'secondary_hover' => $customSecondary,
            ];
        }

        $selected = $palettes[$themeKey] ?? $palettes['skynet'];
        $selected['key'] = $themeKey;
        return $selected;
    }
}
