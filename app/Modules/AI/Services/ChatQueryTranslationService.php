<?php

namespace App\Modules\AI\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosCustomer;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\User;
use App\Modules\AI\Models\AiConversationLog;
use Illuminate\Support\Facades\DB;

class ChatQueryTranslationService
{
    /**
     * Process a plain-language question, execute real database queries, and synthesize response.
     *
     * @param string $question
     * @param Branch|null $branch Context branch (null for Super Admin cross-branch)
     * @param User $user The querying user
     * @param string|null $sessionId
     * @return array
     */
    public static function process(string $question, ?Branch $branch, User $user, ?string $sessionId = null): array
    {
        $sessionId = $sessionId ?: 'session_' . uniqid();
        $startTime = microtime(true);

        $q = strtolower(trim($question));

        // 1. Detect Intent and execute structured DB queries
        $intentData = self::resolveIntentAndData($q, $branch, $user);
        $intent     = $intentData['intent'];
        $payload    = $intentData['data'];
        $grounding  = $intentData['grounding'];

        // 2. Synthesize with AI Provider (or fallback template if unconfigured)
        $aiManager = app(AiManager::class);
        $aiResponse = '';
        $providerUsed = 'template_fallback';
        $modelUsed = 'local_rules';

        if ($aiManager->isBranchEnabled($branch)) {
            try {
                $provider = $aiManager->getActiveProvider();
                if ($provider) {
                    $providerUsed = $provider->getIdentifier();
                    $modelUsed = $provider->getModel();

                    $systemPrompt = <<<SYS
You are "Skynet AI Assistant", an executive AI business analyst for Skynet Digital POS.
Analyze the user's business question using ONLY the provided verified database metrics.
Rules:
1. NEVER fabricate, estimate, or hallucinate numbers not in the verified payload.
2. Present key figures clearly, using currency formatting (₦ / Naira) where appropriate.
3. Be professional, concise, and highlight actionable insights for the store owner/manager.
4. If the data shows issues (e.g. low stock, stock conflicts, unpaid debt), provide a helpful next step.
SYS;

                    $payloadJson = json_encode($payload, JSON_PRETTY_PRINT);
                    $prompt = <<<USER_PROMPT
User Question: "{$question}"

Verified Database Records:
{$payloadJson}

Please provide a concise, structured executive summary answering the question directly based on these exact figures:
USER_PROMPT;

                    $aiResponse = $provider->chat([
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ], [
                        'temperature' => 0.4,
                        'max_tokens'  => 800,
                    ]);
                }
            } catch (\Throwable $e) {
                // Fallback to local rule-based text summary
                $aiResponse = self::generateRuleBasedSummary($intent, $payload, $question);
            }
        }

        if (empty($aiResponse)) {
            $aiResponse = self::generateRuleBasedSummary($intent, $payload, $question);
        }

        $execTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        // 3. Log to conversation history
        AiConversationLog::create([
            'branch_id'          => $branch?->id,
            'user_id'            => $user->id,
            'session_id'         => $sessionId,
            'role'               => 'assistant',
            'message'            => $aiResponse,
            'detected_intent'    => $intent,
            'structured_payload' => $payload,
            'provider_used'      => $providerUsed,
            'model_used'         => $modelUsed,
            'execution_time_ms'  => $execTimeMs,
        ]);

        return [
            'session_id'         => $sessionId,
            'intent'             => $intent,
            'grounding'          => $grounding,
            'structured_data'    => $payload,
            'ai_response'        => $aiResponse,
            'provider_used'      => $providerUsed,
            'execution_time_ms'  => $execTimeMs,
        ];
    }

    /**
     * Map natural language patterns to structured Eloquent queries.
     */
    protected static function resolveIntentAndData(string $q, ?Branch $branch, User $user): array
    {
        // ── A. Sales Comparison / Performance ────────────────────────────────
        if (str_contains($q, 'compare') || str_contains($q, 'last week') || str_contains($q, 'this week') || str_contains($q, 'yesterday') || str_contains($q, 'today') || str_contains($q, 'revenue') || str_contains($q, 'sales')) {
            $todaySales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereDate('created_at', today())
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $yesterdaySales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereDate('created_at', today()->subDay())
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $thisWeekSales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->startOfWeek())
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $lastWeekSales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $thisMonthSales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            return [
                'intent'    => 'sales_performance',
                'grounding' => 'Queried live sales table records grouped by calendar date ranges.',
                'data'      => [
                    'scope'           => $branch ? $branch->name : 'All System Branches',
                    'today'           => ['orders' => (int)$todaySales->count, 'revenue' => (float)$todaySales->revenue, 'profit' => (float)$todaySales->profit],
                    'yesterday'       => ['orders' => (int)$yesterdaySales->count, 'revenue' => (float)$yesterdaySales->revenue, 'profit' => (float)$yesterdaySales->profit],
                    'this_week'       => ['orders' => (int)$thisWeekSales->count, 'revenue' => (float)$thisWeekSales->revenue, 'profit' => (float)$thisWeekSales->profit],
                    'last_week'       => ['orders' => (int)$lastWeekSales->count, 'revenue' => (float)$lastWeekSales->revenue, 'profit' => (float)$lastWeekSales->profit],
                    'this_month'      => ['orders' => (int)$thisMonthSales->count, 'revenue' => (float)$thisMonthSales->revenue, 'profit' => (float)$thisMonthSales->profit],
                    'week_on_week_pct'=> (float)$lastWeekSales->revenue > 0
                        ? round((((float)$thisWeekSales->revenue - (float)$lastWeekSales->revenue) / (float)$lastWeekSales->revenue) * 100, 1)
                        : 0,
                ],
            ];
        }

        // ── B. Top Selling Items ─────────────────────────────────────────────
        if (str_contains($q, 'top') || str_contains($q, 'best seller') || str_contains($q, 'most sold') || str_contains($q, 'popular')) {
            $topItems = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
                ->when($branch, fn($query) => $query->where('sales.branch_id', $branch->id))
                ->where('sales.created_at', '>=', now()->subDays(30))
                ->select(
                    'sale_orders.item_id',
                    'sale_orders.item_name',
                    DB::raw('SUM(sale_orders.qty) as total_qty_sold'),
                    DB::raw('SUM(sale_orders.total_selling_price) as total_revenue')
                )
                ->groupBy('sale_orders.item_id', 'sale_orders.item_name')
                ->orderByDesc('total_qty_sold')
                ->limit(5)
                ->get();

            return [
                'intent'    => 'top_sellers',
                'grounding' => 'Aggregated past 30 days completed sale_orders records.',
                'data'      => [
                    'scope'     => $branch ? $branch->name : 'All System Branches',
                    'period'    => 'Past 30 Days',
                    'top_items' => $topItems->map(fn($i) => [
                        'item_name' => $i->item_name,
                        'units_sold'=> (int)$i->total_qty_sold,
                        'revenue'   => (float)$i->total_revenue,
                    ])->values()->all(),
                ],
            ];
        }

        // ── C. Low Stock & Inventory ─────────────────────────────────────────
        if (str_contains($q, 'stock') || str_contains($q, 'low') || str_contains($q, 'run out') || str_contains($q, 'inventory') || str_contains($q, 'reorder')) {
            $lowStockItems = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('front_store_qty', '<=', 10)
                ->with('category:id,name')
                ->orderBy('front_store_qty')
                ->limit(8)
                ->get();

            $totalStockWorth = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))->sum('stock_worth');

            return [
                'intent'    => 'inventory_status',
                'grounding' => 'Queried live items table inventory quantities and reorder thresholds.',
                'data'      => [
                    'scope'           => $branch ? $branch->name : 'All System Branches',
                    'total_stock_worth' => (float)$totalStockWorth,
                    'low_stock_count' => $lowStockItems->count(),
                    'items_at_risk'   => $lowStockItems->map(fn($i) => [
                        'item_name' => $i->item_name,
                        'category'  => $i->category?->name ?? 'N/A',
                        'stock'     => (int)$i->front_store_qty,
                    ])->values()->all(),
                ],
            ];
        }

        // ── D. Tax Collected ─────────────────────────────────────────────────
        if (str_contains($q, 'tax') || str_contains($q, 'vat')) {
            $thisMonthTax = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('tax_amount');

            $allTimeTax = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->sum('tax_amount');

            return [
                'intent'    => 'tax_summary',
                'grounding' => 'Aggregated tax_amount column from completed sales records.',
                'data'      => [
                    'scope'          => $branch ? $branch->name : 'All System Branches',
                    'this_month_tax' => (float)$thisMonthTax,
                    'all_time_tax'   => (float)$allTimeTax,
                ],
            ];
        }

        // ── E. Staff / Cashier Performance ───────────────────────────────────
        if (str_contains($q, 'cashier') || str_contains($q, 'staff') || str_contains($q, 'void') || str_contains($q, 'user')) {
            $staffSales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->with('user:id,name,username')
                ->select(
                    'user_id',
                    DB::raw('COUNT(*) as sales_count'),
                    DB::raw('SUM(final_total) as total_volume')
                )
                ->groupBy('user_id')
                ->orderByDesc('total_volume')
                ->get();

            return [
                'intent'    => 'staff_performance',
                'grounding' => 'Queried completed sales grouped by authenticated user_id.',
                'data'      => [
                    'scope' => $branch ? $branch->name : 'All System Branches',
                    'staff' => $staffSales->map(fn($s) => [
                        'name'        => $s->user?->name ?? 'Unknown',
                        'sales_count' => (int)$s->sales_count,
                        'revenue'     => (float)$s->total_volume,
                    ])->values()->all(),
                ],
            ];
        }

        // ── F. IMEI / Device Serial Lookup ───────────────────────────────────
        if (str_contains($q, 'imei') || str_contains($q, 'serial') || str_contains($q, 'device')) {
            // Extract possible alphanumeric IMEI or serial token from string
            $stopWords = ['where', 'which', 'device', 'serial', 'number', 'located', 'search', 'lookup', 'status', 'please', 'customer', 'about', 'gadget', 'product'];
            $tokens = preg_split('/[\s,;:\?\!]+/', $q);
            $imeiParam = null;

            foreach ($tokens as $token) {
                $cleaned = preg_replace('/[^a-z0-9\-]/i', '', $token);
                if (strlen($cleaned) >= 5 && !in_array(strtolower($cleaned), $stopWords) && preg_match('/[0-9]/', $cleaned)) {
                    $imeiParam = $cleaned;
                    break;
                }
            }

            if (!$imeiParam) {
                foreach ($tokens as $token) {
                    $cleaned = preg_replace('/[^a-z0-9\-]/i', '', $token);
                    if (strlen($cleaned) >= 6 && !in_array(strtolower($cleaned), $stopWords)) {
                        $imeiParam = $cleaned;
                        break;
                    }
                }
            }

            $imeiRecord = null;
            if ($imeiParam) {
                $imeiRecord = ItemDeviceUnit::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                    ->where('imei_or_device_id', 'like', "%{$imeiParam}%")
                    ->with('item:id,item_name')
                    ->first();
            }

            return [
                'intent'    => 'imei_lookup',
                'grounding' => 'Queried item_device_units table for matching hardware serials.',
                'data'      => [
                    'search_term' => $imeiParam,
                    'found'       => !empty($imeiRecord),
                    'device'      => $imeiRecord ? [
                        'imei'       => $imeiRecord->imei_or_device_id,
                        'item_name'  => $imeiRecord->item?->item_name ?? 'Unknown',
                        'status'     => $imeiRecord->status,
                        'location'   => $imeiRecord->location,
                    ] : null,
                ],
            ];
        }

        // ── G. Stock Conflicts & Notifications Status ────────────────────────
        if (str_contains($q, 'conflict') || str_contains($q, 'notification') || str_contains($q, 'alert')) {
            $unreadNotifications = AppNotification::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('is_read', false)
                ->count();

            $conflictNotifications = AppNotification::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('type', 'stock_conflict')
                ->where('is_read', false)
                ->count();

            return [
                'intent'    => 'conflict_summary',
                'grounding' => 'Queried active app_notifications and stock conflicts.',
                'data'      => [
                    'scope'                 => $branch ? $branch->name : 'All System Branches',
                    'unread_notifications'  => $unreadNotifications,
                    'active_stock_conflicts'=> $conflictNotifications,
                ],
            ];
        }

        // ── H. General Business Overview (Fallback) ──────────────────────────
        $generalSales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->whereMonth('created_at', now()->month)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue')
            ->first();

        $totalItems = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))->count();

        return [
            'intent'    => 'general_overview',
            'grounding' => 'Queried overall monthly sales summary and catalog totals.',
            'data'      => [
                'scope'          => $branch ? $branch->name : 'All System Branches',
                'month_revenue'  => (float)$generalSales->revenue,
                'month_orders'   => (int)$generalSales->count,
                'total_products' => (int)$totalItems,
            ],
        ];
    }

    /**
     * Generate fallback summary if AI provider is offline/unconfigured.
     */
    protected static function generateRuleBasedSummary(string $intent, array $payload, string $question): string
    {
        return match ($intent) {
            'sales_performance' => "Sales Summary for {$payload['scope']}: Today revenue is ₦" . number_format($payload['today']['revenue'], 2) . " across {$payload['today']['orders']} orders. This week total is ₦" . number_format($payload['this_week']['revenue'], 2) . " (compared to ₦" . number_format($payload['last_week']['revenue'], 2) . " last week). Month-to-date revenue stands at ₦" . number_format($payload['this_month']['revenue'], 2) . ".",
            'top_sellers'       => "Top Selling Items for {$payload['scope']} over the {$payload['period']}: " . implode(', ', array_map(fn($i) => "{$i['item_name']} ({$i['units_sold']} units, ₦" . number_format($i['revenue'], 2) . ")", $payload['top_items'] ?? [])) . ".",
            'inventory_status'  => "Inventory Status for {$payload['scope']}: Total stock valuation is ₦" . number_format($payload['total_stock_worth'], 2) . ". There are {$payload['low_stock_count']} items currently running low on stock.",
            'tax_summary'       => "Tax Collection for {$payload['scope']}: Total tax collected this month is ₦" . number_format($payload['this_month_tax'], 2) . " (All-time tax: ₦" . number_format($payload['all_time_tax'], 2) . ").",
            'staff_performance' => "Staff Performance for {$payload['scope']}: Leading staff members by revenue: " . implode(', ', array_map(fn($s) => "{$s['name']} (₦" . number_format($s['revenue'], 2) . " across {$s['sales_count']} sales)", $payload['staff'] ?? [])) . ".",
            'imei_lookup'       => $payload['found']
                ? "IMEI Search Found: {$payload['device']['item_name']} (IMEI: {$payload['device']['imei']}) is currently {$payload['device']['status']} at {$payload['device']['location']}."
                : "No matching device found for the requested IMEI/serial number.",
            'conflict_summary'  => "Notification & Conflict Status: You have {$payload['unread_notifications']} unread notifications, including {$payload['active_stock_conflicts']} unresolved stock conflict alerts.",
            default             => "Overview for {$payload['scope']}: Month-to-date revenue is ₦" . number_format($payload['month_revenue'], 2) . " across {$payload['month_orders']} transactions, managing {$payload['total_products']} catalog items.",
        };
    }
}
