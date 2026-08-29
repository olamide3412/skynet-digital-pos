<?php

namespace App\Modules\AI\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Item;
use App\Models\SaleOrder;
use Illuminate\Support\Facades\DB;

class ReorderPredictionService
{
    /**
     * Compute reorder predictions for a branch or all branches.
     *
     * @param Branch|null $branch
     * @param int $velocityDays Rolling window in days (default 14)
     * @param int $thresholdDays Warning threshold in days (default 7)
     * @return array
     */
    public static function getPredictions(?Branch $branch = null, int $velocityDays = 14, int $thresholdDays = 7): array
    {
        $velocityDays = max(1, $velocityDays);

        // 1. Fetch sales velocity per item over the period
        $salesQuery = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
            ->where('sales.created_at', '>=', now()->subDays($velocityDays))
            ->when($branch, fn($q) => $q->where('sales.branch_id', $branch->id))
            ->select(
                'sale_orders.item_id',
                DB::raw('SUM(sale_orders.qty) as total_units_sold'),
                DB::raw('COUNT(DISTINCT sales.id) as order_count')
            )
            ->groupBy('sale_orders.item_id');

        $salesVelocity = $salesQuery->get()->keyBy('item_id');

        // 2. Fetch active branch items with relationships
        $itemsQuery = Item::query()
            ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
            ->with([
                'category:id,name',
                'branch:id,name,slug',
                'activeUnits',
                'inStockDeviceUnits' => function ($q) use ($branch) {
                    if ($branch) {
                        $q->where('branch_id', $branch->id);
                    }
                    $q->select('id', 'item_id', 'imei_or_device_id', 'location', 'status');
                },
            ]);

        $items = $itemsQuery->get();

        $predictions = [];
        $criticalCount = 0;
        $warningCount  = 0;
        $safeCount     = 0;
        $totalSuggestedReorderUnits = 0;

        foreach ($items as $item) {
            $frontStock = (int) ($item->front_store_qty ?? 0);
            $backStock  = (int) ($item->back_store_qty ?? 0);
            $totalStock = $frontStock + $backStock;
            $reorderPoint = (int) ($item->reorder_point ?: 10);

            // IMEI item handling
            if ($item->is_imei_tracked) {
                $inStockImeisCount = $item->relationLoaded('inStockDeviceUnits')
                    ? $item->inStockDeviceUnits->count()
                    : $frontStock;

                $urgency = 'safe';
                if ($inStockImeisCount <= 0) {
                    $urgency = 'critical';
                    $criticalCount++;
                } elseif ($inStockImeisCount <= 2) {
                    $urgency = 'warning';
                    $warningCount++;
                } else {
                    $safeCount++;
                }

                $suggestedQty = max(0, $reorderPoint - $inStockImeisCount);
                if ($suggestedQty > 0) {
                    $totalSuggestedReorderUnits += $suggestedQty;
                }

                $predictions[] = [
                    'item_id'                  => $item->id,
                    'item_name'                => $item->item_name,
                    'barcode_number'           => $item->barcode_number,
                    'branch_id'                => $item->branch_id,
                    'branch_name'              => $item->branch?->name ?? 'Default Branch',
                    'category_name'            => $item->category?->name ?? 'Uncategorized',
                    'is_imei_tracked'          => true,
                    'front_store_qty'          => $frontStock,
                    'back_store_qty'           => $backStock,
                    'total_stock'              => $totalStock,
                    'in_stock_imeis_count'     => $inStockImeisCount,
                    'total_sold_in_period'     => 0,
                    'avg_daily_sales'          => 0,
                    'days_left'                => $inStockImeisCount <= 0 ? 0 : 999,
                    'urgency'                  => $urgency,
                    'reorder_point'            => $reorderPoint,
                    'suggested_reorder_units'  => $suggestedQty,
                    'suggested_reorder_display'=> "{$suggestedQty} Units (IMEI Tracked)",
                    'needs_reorder'            => $urgency !== 'safe',
                ];
                continue;
            }

            // Standard bulk velocity item calculation
            $soldRecord = $salesVelocity->get($item->id);
            $totalSold = $soldRecord ? (int) $soldRecord->total_units_sold : 0;
            $avgDailySales = round($totalSold / $velocityDays, 2);

            // Compute estimated days left based on front store stock
            $daysLeft = $avgDailySales > 0 ? round($frontStock / $avgDailySales, 1) : ($frontStock <= 0 ? 0 : 999);

            // Determine urgency
            $urgency = 'safe';
            if ($frontStock <= 0 || ($avgDailySales > 0 && $daysLeft <= 2)) {
                $urgency = 'critical';
                $criticalCount++;
            } elseif ($daysLeft <= $thresholdDays || $frontStock <= $reorderPoint) {
                $urgency = 'warning';
                $warningCount++;
            } else {
                $safeCount++;
            }

            // Target replenishment stock (e.g. 14 days of average sales buffer + reorder point)
            $targetStock = max($reorderPoint * 2, (int) ceil($avgDailySales * 14));
            $suggestedBaseUnits = max(0, $targetStock - $frontStock);

            if ($suggestedBaseUnits > 0) {
                $totalSuggestedReorderUnits += $suggestedBaseUnits;
            }

            // Convert suggested units into highest sensible variant unit
            $formattedReorder = self::formatSuggestedUnitVariant($item, $suggestedBaseUnits);

            $predictions[] = [
                'item_id'                  => $item->id,
                'item_name'                => $item->item_name,
                'barcode_number'           => $item->barcode_number,
                'branch_id'                => $item->branch_id,
                'branch_name'              => $item->branch?->name ?? 'Default Branch',
                'category_name'            => $item->category?->name ?? 'Uncategorized',
                'is_imei_tracked'          => false,
                'front_store_qty'          => $frontStock,
                'back_store_qty'           => $backStock,
                'total_stock'              => $totalStock,
                'in_stock_imeis_count'     => null,
                'total_sold_in_period'     => $totalSold,
                'avg_daily_sales'          => $avgDailySales,
                'days_left'                => $daysLeft,
                'urgency'                  => $urgency,
                'reorder_point'            => $reorderPoint,
                'suggested_reorder_units'  => $suggestedBaseUnits,
                'suggested_reorder_display'=> $formattedReorder,
                'needs_reorder'            => $urgency !== 'safe',
            ];
        }

        // Sort by urgency: Critical first, then Warning, then Safe, then lowest days left
        usort($predictions, function ($a, $b) {
            $order = ['critical' => 1, 'warning' => 2, 'safe' => 3];
            $cmp = ($order[$a['urgency']] ?? 9) <=> ($order[$b['urgency']] ?? 9);
            if ($cmp !== 0) return $cmp;
            return $a['days_left'] <=> $b['days_left'];
        });

        return [
            'summary' => [
                'total_items_analyzed'     => count($items),
                'critical_items_count'     => $criticalCount,
                'warning_items_count'      => $warningCount,
                'safe_items_count'         => $safeCount,
                'needs_reorder_count'      => $criticalCount + $warningCount,
                'total_suggested_units'    => $totalSuggestedReorderUnits,
                'velocity_days'            => $velocityDays,
                'threshold_days'           => $thresholdDays,
            ],
            'predictions' => $predictions,
        ];
    }

    /**
     * Map suggested base units to highest packaging variant from item_units.
     */
    protected static function formatSuggestedUnitVariant(Item $item, int $baseUnits): string
    {
        if ($baseUnits <= 0) {
            return "0 " . ($item->unit_label ?: 'Units');
        }

        $units = $item->relationLoaded('activeUnits') ? $item->activeUnits : $item->activeUnits()->get();
        $bestVariant = $units->sortByDesc('quantity_in_base_units')->first(fn($u) => (int)$u->quantity_in_base_units > 1);

        if ($bestVariant && (int)$bestVariant->quantity_in_base_units > 1) {
            $conversion = (int) $bestVariant->quantity_in_base_units;
            $variantCount = floor($baseUnits / $conversion);
            $remainder = $baseUnits % $conversion;

            if ($variantCount > 0 && $remainder > 0) {
                return "{$variantCount} {$bestVariant->unit_name}s + {$remainder} " . ($item->unit_label ?: 'pcs') . " ({$baseUnits} total)";
            } elseif ($variantCount > 0) {
                return "{$variantCount} {$bestVariant->unit_name}s ({$baseUnits} " . ($item->unit_label ?: 'pcs') . ")";
            }
        }

        return "{$baseUnits} " . ($item->unit_label ?: 'Units');
    }

    /**
     * Dispatch in-app notifications for items needing urgent replenishment.
     */
    public static function dispatchReorderNotifications(Branch $branch, int $maxAlerts = 5): int
    {
        $data = self::getPredictions($branch);
        $urgentItems = array_filter($data['predictions'], fn($p) => $p['urgency'] === 'critical' || $p['urgency'] === 'warning');

        $dispatched = 0;
        foreach (array_slice($urgentItems, 0, $maxAlerts) as $item) {
            $exists = AppNotification::where('branch_id', $branch->id)
                ->where('type', 'reorder_alert')
                ->where('reference_id', $item['item_id'])
                ->where('created_at', '>=', now()->subHours(12))
                ->exists();

            if (!$exists) {
                AppNotification::create([
                    'branch_id'      => $branch->id,
                    'type'           => 'reorder_alert',
                    'title'          => "⚡ Low Stock: {$item['item_name']}",
                    'message'        => "{$item['item_name']} has only {$item['front_store_qty']} left (approx {$item['days_left']} days). Suggested reorder: {$item['suggested_reorder_display']}.",
                    'reference_type' => 'Item',
                    'reference_id'   => $item['item_id'],
                    'action_url'     => route('pos.inventory.reorder-points', ['branch' => $branch->slug]),
                    'data'           => ['severity' => $item['urgency'] === 'critical' ? 'critical' : 'warning'],
                    'is_read'        => false,
                ]);
                $dispatched++;
            }
        }

        return $dispatched;
    }

    /**
     * Generate an AI-assisted narrative summary of reorder recommendations.
     */
    public static function generateAiInsight(array $predictionData, ?Branch $branch = null): string
    {
        $aiManager = app(AiManager::class);

        if (!$aiManager->isBranchEnabled($branch)) {
            return "AI provider is not configured. Rule-based velocity forecasting is active above.";
        }

        $summary = $predictionData['summary'];
        $criticalItems = array_slice(array_filter($predictionData['predictions'], fn($p) => $p['urgency'] === 'critical'), 0, 5);

        $promptItems = array_map(fn($i) => "- {$i['item_name']}: Stock={$i['front_store_qty']}, Daily Sales={$i['avg_daily_sales']}, Days Left={$i['days_left']}, Suggested Reorder={$i['suggested_reorder_display']}", $criticalItems);
        $itemsText = implode("\n", $promptItems);

        $prompt = <<<PROMPT
You are an expert retail inventory forecasting consultant.
Provide a concise, high-impact 2-paragraph executive recommendation for a branch manager based on this stock velocity data:

Summary:
- Total Products Analyzed: {$summary['total_items_analyzed']}
- Critical Stockouts: {$summary['critical_items_count']}
- Warning Thresholds: {$summary['warning_items_count']}
- Total Suggested Reorder Base Units: {$summary['total_suggested_units']}

Most Critical Items:
{$itemsText}

Format your answer with bullet points for the top 3 immediate purchasing actions.
PROMPT;

        try {
            return $aiManager->generateText($prompt, [
                'temperature' => 0.5,
                'max_tokens'  => 500,
            ]);
        } catch (\Throwable $e) {
            return "Rule-based analysis active: {$summary['critical_items_count']} critical items and {$summary['warning_items_count']} items approaching stockout within {$summary['threshold_days']} days.";
        }
    }
}
