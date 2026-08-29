<?php

namespace App\Modules\AI\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\DebtPayment;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosCustomer;
use App\Models\Sale;
use App\Models\SaleOrder;
use App\Models\SaleReturnItem;
use App\Models\StockConflict;
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

        // 1. Detect Intent and execute structured, branch-isolated DB queries
        $intentData = self::resolveIntentAndData($q, $branch, $user);
        $intent     = $intentData['intent'];
        $payload    = $intentData['data'];
        $grounding  = $intentData['grounding'];

        // 2. Synthesize with AI Provider (or fallback rule-based template if unconfigured)
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

                    $scopeLabel = $branch ? "Branch: {$branch->name}" : "All System Branches (Super Admin Cross-Branch)";
                    $systemPrompt = <<<SYS
You are "Skynet AI Executive Business Analyst", the embedded intelligent advisor for Skynet Digital POS.
You are analyzing business performance for: {$scopeLabel}.
Analyze the user's business question using ONLY the provided verified database metrics.

Formatting & Analytical Guidelines:
1. ALWAYS format numbers and monetary values cleanly using the Nigerian Naira currency symbol (₦) with commas (e.g. ₦1,250,000.00).
2. When presenting multiple comparative metrics (e.g., top items, employee rankings, branch comparisons, growth periods), ALWAYS format them in clean Markdown Tables using the '|' pipe syntax.
3. Highlight critical insights, growth rates, top performers, or risk flags using **bold text**.
4. Group actionable recommendations into bullet points under a '#### Strategic Recommendations' or '#### Key Takeaways' heading.
5. If answering about suspicious activity, evaluate discount volume, refunds, stock write-offs, and conflicts with an objective, risk-focused tone.
6. If answering about growth or trajectory, evaluate velocity percentages and profit margins with clear momentum indicators (e.g., Accelerating Growth, Stable, Contracting).
7. NEVER fabricate, speculate, or hallucinate metrics not present in the verified grounding payload.
SYS;

                    $payloadJson = json_encode($payload, JSON_PRETTY_PRINT);
                    $prompt = <<<USER_PROMPT
User Question: "{$question}"

Verified Database Records:
{$payloadJson}

Please provide a structured, executive-ready summary answering the question directly based on these exact verified figures:
USER_PROMPT;

                    $aiResponse = $provider->chat([
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ], [
                        'temperature' => 0.3,
                        'max_tokens'  => 1200,
                    ]);
                }
            } catch (\Throwable $e) {
                // Fallback to local rule-based text summary
                $providerUsed = 'template_fallback';
                $modelUsed = 'local_rules';
                $aiResponse = self::generateRuleBasedSummary($intent, $payload, $question);
            }
        }

        if (empty($aiResponse)) {
            $providerUsed = 'template_fallback';
            $modelUsed = 'local_rules';
            $aiResponse = self::generateRuleBasedSummary($intent, $payload, $question);
        }

        $execTimeMs = (int) round((microtime(true) - $startTime) * 1000);
        $isAiGenerated = !in_array($providerUsed, ['template_fallback', 'local_database_summary', 'local_rules']);

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
            'model_used'         => $modelUsed,
            'is_ai_generated'    => $isAiGenerated,
            'execution_time_ms'  => $execTimeMs,
        ];
    }

    /**
     * Map natural language patterns to structured, branch-isolated Eloquent queries.
     */
    protected static function resolveIntentAndData(string $q, ?Branch $branch, User $user): array
    {
        $scopeName = $branch ? $branch->name : 'All System Branches (Cross-Branch)';

        // ── 1. Suspicious Activity / Fraud & Risk Detection ──────────────────
        if (str_contains($q, 'suspicious') || str_contains($q, 'fraud') || str_contains($q, 'theft') ||
            str_contains($q, 'anomaly') || str_contains($q, 'risk') || str_contains($q, 'audit') ||
            str_contains($q, 'discrepancy') || str_contains($q, 'unusual') || str_contains($q, 'loss')) {

            // A. High discount cashiers
            $discountAudit = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->where('discount_amount', '>', 0)
                ->with('user:id,name,username')
                ->select(
                    'user_id',
                    DB::raw('COUNT(*) as discounted_sales_count'),
                    DB::raw('SUM(discount_amount) as total_discount_given'),
                    DB::raw('MAX(discount_amount) as max_single_discount'),
                    DB::raw('SUM(final_total) as total_revenue_generated')
                )
                ->groupBy('user_id')
                ->orderByDesc('total_discount_given')
                ->limit(5)
                ->get();

            // B. Refund / Return items audit
            $returnAudit = SaleReturnItem::when($branch, fn($query) => $query->whereHas('sale', fn($s) => $s->where('branch_id', $branch->id)))
                ->where('created_at', '>=', now()->subDays(30))
                ->with('user:id,name,username')
                ->select(
                    'user_id',
                    DB::raw('COUNT(*) as total_returns_count'),
                    DB::raw('SUM(qty) as total_items_returned'),
                    DB::raw('SUM(refund_amount) as total_refunded_amount')
                )
                ->groupBy('user_id')
                ->orderByDesc('total_refunded_amount')
                ->limit(5)
                ->get();

            // C. Manual Inventory subtractions / write-offs
            $inventoryLossAudit = InventoryTransaction::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->where(function($query) {
                    $query->where('transaction_type', 'subtract')
                          ->orWhere('transaction_type', 'adjustment')
                          ->orWhere('transaction_type', 'like', '%loss%')
                          ->orWhere('transaction_type', 'like', '%damage%');
                })
                ->with(['item:id,item_name', 'user:id,name'])
                ->select('item_id', 'user_id', 'transaction_type', 'qty', 'notes', 'created_at')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            // D. Unresolved stock and IMEI conflicts
            $activeConflictsCount = StockConflict::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('status', 'pending_review')
                ->count();

            // E. Overdue Debts
            $totalOutstandingDebt = PosCustomer::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('debt_bal', '>', 0)
                ->sum('debt_bal');

            $riskFlags = [];
            $totalDiscountIssued = $discountAudit->sum('total_discount_given');
            $totalRefundIssued = $returnAudit->sum('total_refunded_amount');

            if ($activeConflictsCount > 0) {
                $riskFlags[] = "{$activeConflictsCount} unresolved offline sync conflicts pending review.";
            }
            if ($totalRefundIssued > 50000) {
                $riskFlags[] = "High volume of return refunds processed (₦" . number_format($totalRefundIssued, 2) . ") in past 30 days.";
            }
            if ($totalDiscountIssued > 100000) {
                $riskFlags[] = "Significant discounts issued across sales (₦" . number_format($totalDiscountIssued, 2) . ").";
            }
            if ($inventoryLossAudit->count() > 0) {
                $riskFlags[] = "{$inventoryLossAudit->count()} manual inventory deduction / adjustment logs recorded.";
            }

            $riskLevel = count($riskFlags) >= 3 ? 'High' : (count($riskFlags) >= 1 ? 'Moderate' : 'Low / Clean');

            return [
                'intent'    => 'suspicious_activity_audit',
                'grounding' => 'Queried discount distributions, return refund volumes, manual inventory write-offs, and offline conflicts.',
                'data'      => [
                    'scope'                 => $scopeName,
                    'overall_risk_level'    => $riskLevel,
                    'active_conflicts'      => $activeConflictsCount,
                    'total_discounts_30d'   => (float)$totalDiscountIssued,
                    'total_refunds_30d'     => (float)$totalRefundIssued,
                    'outstanding_debt'      => (float)$totalOutstandingDebt,
                    'risk_flags'            => $riskFlags,
                    'discount_by_cashier'   => $discountAudit->map(fn($d) => [
                        'cashier'           => $d->user?->name ?? 'Unknown',
                        'discounted_sales'  => (int)$d->discounted_sales_count,
                        'total_discount'    => (float)$d->total_discount_given,
                        'max_single'        => (float)$d->max_single_discount,
                    ])->values()->all(),
                    'returns_by_cashier'    => $returnAudit->map(fn($r) => [
                        'cashier'           => $r->user?->name ?? 'Unknown',
                        'returns_count'     => (int)$r->total_returns_count,
                        'items_returned'    => (int)$r->total_items_returned,
                        'refunded_amount'   => (float)$r->total_refunded_amount,
                    ])->values()->all(),
                    'recent_write_offs'     => $inventoryLossAudit->map(fn($l) => [
                        'item'              => $l->item?->item_name ?? 'Unknown',
                        'qty_deducted'      => (int)$l->qty,
                        'reason'            => $l->notes ?: 'Manual Adjustment',
                        'logged_by'         => $l->user?->name ?? 'System',
                        'date'              => $l->created_at?->format('Y-m-d H:i') ?? 'Recent',
                    ])->values()->all(),
                ],
            ];
        }

        // ── 2. Staff Performance & Top Performers Ranking ───────────────────
        if (str_contains($q, 'perform') || str_contains($q, 'cashier') || str_contains($q, 'staff') ||
            str_contains($q, 'employee') || str_contains($q, 'who sold') || str_contains($q, 'worker') ||
            str_contains($q, 'agent') || str_contains($q, 'sales rep')) {

            $staffStats = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->with('user:id,name,username')
                ->select(
                    'user_id',
                    DB::raw('COUNT(*) as total_sales_count'),
                    DB::raw('COALESCE(SUM(final_total), 0) as total_volume'),
                    DB::raw('COALESCE(SUM(profit_made), 0) as total_profit'),
                    DB::raw('COALESCE(SUM(discount_amount), 0) as total_discounts'),
                    DB::raw('COALESCE(AVG(final_total), 0) as avg_ticket_size')
                )
                ->groupBy('user_id')
                ->orderByDesc('total_volume')
                ->get();

            // Also calculate returns handled by each staff member
            $staffReturns = SaleReturnItem::when($branch, fn($query) => $query->whereHas('sale', fn($s) => $s->where('branch_id', $branch->id)))
                ->where('created_at', '>=', now()->subDays(30))
                ->select('user_id', DB::raw('COUNT(*) as returns_count'), DB::raw('SUM(refund_amount) as total_refunded'))
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');

            $rankedStaff = $staffStats->map(function ($s, $idx) use ($staffReturns) {
                $returns = $staffReturns->get($s->user_id);
                return [
                    'rank'              => $idx + 1,
                    'name'              => $s->user?->name ?? 'Unknown',
                    'username'          => $s->user?->username ?? '',
                    'sales_count'       => (int)$s->total_sales_count,
                    'revenue'           => (float)$s->total_volume,
                    'profit_generated'  => (float)$s->total_profit,
                    'average_sale'      => (float)$s->avg_ticket_size,
                    'discounts_given'   => (float)$s->total_discounts,
                    'returns_count'     => (int)($returns?->returns_count ?? 0),
                    'refunds_processed' => (float)($returns?->total_refunded ?? 0),
                ];
            })->values()->all();

            $topPerformer = $rankedStaff[0] ?? null;

            return [
                'intent'    => 'staff_performance_ranking',
                'grounding' => 'Queried 30-day completed sales, profit generation, average basket value, discounts, and returns per cashier.',
                'data'      => [
                    'scope'         => $scopeName,
                    'period'        => 'Past 30 Days',
                    'total_staff'   => count($rankedStaff),
                    'top_performer' => $topPerformer ? $topPerformer['name'] : 'N/A',
                    'leaderboard'   => $rankedStaff,
                ],
            ];
        }

        // ── 3. Business Growth & Trajectory Prediction ───────────────────────
        if (str_contains($q, 'grow') || str_contains($q, 'growth') || str_contains($q, 'trajectory') ||
            str_contains($q, 'trend') || str_contains($q, 'predict') || str_contains($q, 'forecast') ||
            str_contains($q, 'expansion') || str_contains($q, 'rate of growth') || str_contains($q, 'future')) {

            // This month vs last month
            $thisMonth = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $lastMonth = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereMonth('created_at', now()->subMonth()->month)
                ->whereYear('created_at', now()->subMonth()->year)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            // This week vs last week
            $thisWeek = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->startOfWeek())
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $lastWeek = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as revenue, COALESCE(SUM(profit_made), 0) as profit')
                ->first();

            $monthGrowthRate = (float)$lastMonth->revenue > 0
                ? round((((float)$thisMonth->revenue - (float)$lastMonth->revenue) / (float)$lastMonth->revenue) * 100, 2)
                : ((float)$thisMonth->revenue > 0 ? 100.0 : 0.0);

            $weekGrowthRate = (float)$lastWeek->revenue > 0
                ? round((((float)$thisWeek->revenue - (float)$lastWeek->revenue) / (float)$lastWeek->revenue) * 100, 2)
                : ((float)$thisWeek->revenue > 0 ? 100.0 : 0.0);

            $profitGrowthRate = (float)$lastMonth->profit > 0
                ? round((((float)$thisMonth->profit - (float)$lastMonth->profit) / (float)$lastMonth->profit) * 100, 2)
                : 0.0;

            $grossMarginPct = (float)$thisMonth->revenue > 0
                ? round(((float)$thisMonth->profit / (float)$thisMonth->revenue) * 100, 1)
                : 0.0;

            $trajectoryVerdict = $monthGrowthRate > 10
                ? "Accelerating Growth (+{$monthGrowthRate}%)"
                : ($monthGrowthRate >= 0 ? "Stable Steady Growth (+{$monthGrowthRate}%)" : "Contracting Volume ({$monthGrowthRate}%)");

            return [
                'intent'    => 'growth_trajectory_prediction',
                'grounding' => 'Calculated multi-period revenue, volume, and profit velocity metrics across calendar intervals.',
                'data'      => [
                    'scope'                 => $scopeName,
                    'trajectory_status'     => $trajectoryVerdict,
                    'month_on_month_growth' => $monthGrowthRate,
                    'week_on_week_growth'   => $weekGrowthRate,
                    'profit_growth_rate'    => $profitGrowthRate,
                    'gross_profit_margin'   => $grossMarginPct,
                    'this_month'            => ['orders' => (int)$thisMonth->count, 'revenue' => (float)$thisMonth->revenue, 'profit' => (float)$thisMonth->profit],
                    'last_month'            => ['orders' => (int)$lastMonth->count, 'revenue' => (float)$lastMonth->revenue, 'profit' => (float)$lastMonth->profit],
                    'this_week'             => ['orders' => (int)$thisWeek->count, 'revenue' => (float)$thisWeek->revenue, 'profit' => (float)$thisWeek->profit],
                    'last_week'             => ['orders' => (int)$lastWeek->count, 'revenue' => (float)$lastWeek->revenue, 'profit' => (float)$lastWeek->profit],
                ],
            ];
        }

        // ── 4. Sales Rate, Hourly Velocity & Basket Analysis ─────────────────
        if (str_contains($q, 'sale rate') || str_contains($q, 'rate of sale') || str_contains($q, 'velocity') ||
            str_contains($q, 'rush') || str_contains($q, 'peak hour') || str_contains($q, 'busiest') ||
            str_contains($q, 'basket') || str_contains($q, 'payment method')) {

            // Payment method breakdown past 30 days
            $paymentBreakdown = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->select(
                    DB::raw('COALESCE(SUM(cash), 0) as total_cash'),
                    DB::raw('COALESCE(SUM(bank_transfer), 0) as total_transfer'),
                    DB::raw('COALESCE(SUM(CASE WHEN is_debt = 1 THEN final_total ELSE 0 END), 0) as total_debt'),
                    DB::raw('COALESCE(SUM(final_total), 0) as total_gross'),
                    DB::raw('COUNT(*) as total_orders'),
                    DB::raw('COALESCE(AVG(final_total), 0) as avg_basket_size')
                )
                ->first();

            // Peak hourly sales distribution
            $hourlySales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('created_at', '>=', now()->subDays(30))
                ->select(
                    DB::raw('HOUR(created_at) as hour_of_day'),
                    DB::raw('COUNT(*) as order_count'),
                    DB::raw('SUM(final_total) as hourly_revenue')
                )
                ->groupBy('hour_of_day')
                ->orderByDesc('order_count')
                ->limit(3)
                ->get();

            $totalGross = (float)$paymentBreakdown->total_gross;
            $cashPct = $totalGross > 0 ? round(((float)$paymentBreakdown->total_cash / $totalGross) * 100, 1) : 0;
            $transferPct = $totalGross > 0 ? round(((float)$paymentBreakdown->total_transfer / $totalGross) * 100, 1) : 0;
            $debtPct = $totalGross > 0 ? round(((float)$paymentBreakdown->total_debt / $totalGross) * 100, 1) : 0;

            $peakHoursFormatted = $hourlySales->map(fn($h) => [
                'time_slot' => sprintf('%02d:00 - %02d:00', $h->hour_of_day, ($h->hour_of_day + 1) % 24),
                'orders'    => (int)$h->order_count,
                'revenue'   => (float)$h->hourly_revenue,
            ])->values()->all();

            return [
                'intent'    => 'sales_velocity_and_basket_rate',
                'grounding' => 'Analyzed 30-day transaction timestamps, basket size averages, and payment method settlement splits.',
                'data'      => [
                    'scope'                 => $scopeName,
                    'total_orders_30d'      => (int)$paymentBreakdown->total_orders,
                    'average_basket_value'  => (float)$paymentBreakdown->avg_basket_size,
                    'daily_order_velocity'  => round((int)$paymentBreakdown->total_orders / 30, 1),
                    'payment_mix'           => [
                        'cash_amount'       => (float)$paymentBreakdown->total_cash,
                        'cash_pct'          => $cashPct,
                        'transfer_amount'   => (float)$paymentBreakdown->total_transfer,
                        'transfer_pct'      => $transferPct,
                        'debt_amount'       => (float)$paymentBreakdown->total_debt,
                        'debt_pct'          => $debtPct,
                    ],
                    'peak_rush_hours'       => $peakHoursFormatted,
                ],
            ];
        }

        // ── 5. Cross-Branch Comparison (Super Admin Scope) ───────────────────
        if (!$branch && (str_contains($q, 'branch') || str_contains($q, 'store') || str_contains($q, 'compare stores') || str_contains($q, 'all locations'))) {
            $branchComparison = Branch::where('is_active', true)
                ->withCount(['sales as total_sales_30d' => fn($q) => $q->where('created_at', '>=', now()->subDays(30))])
                ->withSum(['sales as total_revenue_30d' => fn($q) => $q->where('created_at', '>=', now()->subDays(30))], 'final_total')
                ->withSum(['sales as total_profit_30d' => fn($q) => $q->where('created_at', '>=', now()->subDays(30))], 'profit_made')
                ->get();

            $totalSystemRevenue = (float)$branchComparison->sum('total_revenue_30d');

            $rankedBranches = $branchComparison->map(function ($b, $idx) use ($totalSystemRevenue) {
                $rev = (float)($b->total_revenue_30d ?? 0);
                $orders = (int)($b->total_sales_30d ?? 0);
                $profit = (float)($b->total_profit_30d ?? 0);
                return [
                    'rank'              => $idx + 1,
                    'branch_name'       => $b->name,
                    'orders_30d'        => $orders,
                    'revenue_30d'       => $rev,
                    'profit_30d'        => $profit,
                    'avg_order_value'   => $orders > 0 ? round($rev / $orders, 2) : 0,
                    'market_share_pct'  => $totalSystemRevenue > 0 ? round(($rev / $totalSystemRevenue) * 100, 1) : 0,
                ];
            })->sortByDesc('revenue_30d')->values()->map(fn($b, $idx) => array_merge($b, ['rank' => $idx + 1]))->all();

            return [
                'intent'    => 'cross_branch_benchmarking',
                'grounding' => 'Queried and benchmarked 30-day performance metrics across all registered active store branches.',
                'data'      => [
                    'scope'                 => 'All Active Branches',
                    'total_system_revenue'  => $totalSystemRevenue,
                    'active_branch_count'   => count($rankedBranches),
                    'branch_leaderboard'    => $rankedBranches,
                ],
            ];
        }

        // ── 6. Top Selling Items ─────────────────────────────────────────────
        if (str_contains($q, 'top') || str_contains($q, 'best seller') || str_contains($q, 'most sold') || str_contains($q, 'popular')) {
            $topItems = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
                ->leftJoin('items', 'items.id', '=', 'sale_orders.item_id')
                ->when($branch, fn($query) => $query->where('sales.branch_id', $branch->id))
                ->where('sales.created_at', '>=', now()->subDays(30))
                ->select(
                    'sale_orders.item_id',
                    'sale_orders.item_name',
                    DB::raw('SUM(sale_orders.qty) as total_qty_sold'),
                    DB::raw('SUM(sale_orders.total_selling_price) as total_revenue'),
                    DB::raw('SUM(sale_orders.total_selling_price - (COALESCE(items.buy_price, 0) * sale_orders.qty)) as estimated_profit')
                )
                ->groupBy('sale_orders.item_id', 'sale_orders.item_name')
                ->orderByDesc('total_qty_sold')
                ->limit(8)
                ->get();

            return [
                'intent'    => 'top_sellers',
                'grounding' => 'Aggregated past 30 days completed sale_orders records.',
                'data'      => [
                    'scope'     => $scopeName,
                    'period'    => 'Past 30 Days',
                    'top_items' => $topItems->map(fn($i, $idx) => [
                        'rank'      => $idx + 1,
                        'item_name' => $i->item_name,
                        'units_sold'=> (int)$i->total_qty_sold,
                        'revenue'   => (float)$i->total_revenue,
                        'profit'    => (float)$i->estimated_profit,
                    ])->values()->all(),
                ],
            ];
        }

        // ── 7. Low Stock & Inventory ─────────────────────────────────────────
        if (str_contains($q, 'stock') || str_contains($q, 'low') || str_contains($q, 'run out') ||
            str_contains($q, 'inventory') || str_contains($q, 'reorder') || str_contains($q, 'warehouse')) {

            $lowStockItems = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('front_store_qty', '<=', 10)
                ->with('category:id,name')
                ->orderBy('front_store_qty')
                ->limit(10)
                ->get();

            $totalStockWorth = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))->sum('stock_worth');
            $totalProducts = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))->count();

            return [
                'intent'    => 'inventory_status',
                'grounding' => 'Queried live items table inventory quantities, front/back store stock, and valuations.',
                'data'      => [
                    'scope'             => $scopeName,
                    'total_stock_worth' => (float)$totalStockWorth,
                    'total_catalog'     => (int)$totalProducts,
                    'low_stock_count'   => $lowStockItems->count(),
                    'items_at_risk'     => $lowStockItems->map(fn($i) => [
                        'item_name'     => $i->item_name,
                        'category'      => $i->category?->name ?? 'General',
                        'front_stock'   => (int)$i->front_store_qty,
                        'back_stock'    => (int)$i->back_store_qty,
                        'reorder_point' => (int)($i->reorder_point ?? 5),
                    ])->values()->all(),
                ],
            ];
        }

        // ── 8. Debt & Customer Credit Health ─────────────────────────────────
        if (str_contains($q, 'debt') || str_contains($q, 'credit') || str_contains($q, 'debtor') ||
            str_contains($q, 'unpaid') || str_contains($q, 'owing') || str_contains($q, 'recover')) {

            $debtors = PosCustomer::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('debt_bal', '>', 0)
                ->orderByDesc('debt_bal')
                ->limit(6)
                ->get();

            $totalOutstanding = PosCustomer::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('debt_bal', '>', 0)
                ->sum('debt_bal');

            $debtRecoveredThisMonth = DebtPayment::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('type', 'credit')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            return [
                'intent'    => 'debt_and_credit_health',
                'grounding' => 'Aggregated customer debt ledgers, outstanding balances, and monthly recovery payments.',
                'data'      => [
                    'scope'                 => $scopeName,
                    'total_outstanding'     => (float)$totalOutstanding,
                    'debt_recovered_month'  => (float)$debtRecoveredThisMonth,
                    'debtors_count'         => $debtors->count(),
                    'top_debtors'           => $debtors->map(fn($d) => [
                        'customer_name'     => $d->name,
                        'phone'             => $d->phone ?? 'N/A',
                        'balance_owing'     => (float)$d->debt_bal,
                    ])->values()->all(),
                ],
            ];
        }

        // ── 9. Tax & VAT Collected ───────────────────────────────────────────
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
                    'scope'          => $scopeName,
                    'this_month_tax' => (float)$thisMonthTax,
                    'all_time_tax'   => (float)$allTimeTax,
                ],
            ];
        }

        // ── 10. IMEI / Device Serial Lookup ──────────────────────────────────
        if (str_contains($q, 'imei') || str_contains($q, 'serial') || str_contains($q, 'device')) {
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

        // ── 11. General Business Overview & Executive Briefing ───────────────
        $todaySales = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->whereDate('created_at', today())
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

        $totalItems = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))->count();
        $lowStockCount = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))->where('front_store_qty', '<=', 10)->count();

        $wowGrowth = (float)$lastWeekSales->revenue > 0
            ? round((((float)$thisWeekSales->revenue - (float)$lastWeekSales->revenue) / (float)$lastWeekSales->revenue) * 100, 1)
            : 0;

        return [
            'intent'    => 'sales_performance',
            'grounding' => 'Queried multi-tier real-time metrics across sales orders, profits, catalog counts, and inventory health.',
            'data'      => [
                'scope'           => $scopeName,
                'today'           => ['orders' => (int)$todaySales->count, 'revenue' => (float)$todaySales->revenue, 'profit' => (float)$todaySales->profit],
                'this_week'       => ['orders' => (int)$thisWeekSales->count, 'revenue' => (float)$thisWeekSales->revenue, 'profit' => (float)$thisWeekSales->profit],
                'last_week'       => ['orders' => (int)$lastWeekSales->count, 'revenue' => (float)$lastWeekSales->revenue, 'profit' => (float)$lastWeekSales->profit],
                'this_month'      => ['orders' => (int)$thisMonthSales->count, 'revenue' => (float)$thisMonthSales->revenue, 'profit' => (float)$thisMonthSales->profit],
                'week_on_week_pct'=> $wowGrowth,
                'total_products'  => (int)$totalItems,
                'low_stock_count' => (int)$lowStockCount,
            ],
        ];
    }

    /**
     * Generate fallback rich markdown summary when external AI provider is offline or not configured.
     */
    protected static function generateRuleBasedSummary(string $intent, array $payload, string $question): string
    {
        return match ($intent) {
            'suspicious_activity_audit' => self::formatSuspiciousActivitySummary($payload),
            'staff_performance_ranking' => self::formatStaffRankingSummary($payload),
            'growth_trajectory_prediction' => self::formatGrowthTrajectorySummary($payload),
            'sales_velocity_and_basket_rate' => self::formatSalesVelocitySummary($payload),
            'cross_branch_benchmarking' => self::formatCrossBranchSummary($payload),
            'top_sellers'               => self::formatTopSellersSummary($payload),
            'inventory_status'          => self::formatInventoryStatusSummary($payload),
            'debt_and_credit_health'    => self::formatDebtHealthSummary($payload),
            'tax_summary'               => "### Tax & VAT Collection Brief\n\n- **Scope**: {$payload['scope']}\n- **Month-to-Date Tax**: **₦" . number_format($payload['this_month_tax'], 2) . "**\n- **All-Time Tax Collected**: **₦" . number_format($payload['all_time_tax'], 2) . "**\n\nAll tax figures are calculated strictly from completed sales invoices.",
            'imei_lookup'               => $payload['found']
                ? "### Device IMEI Verification Record\n\n- **Device Item**: **{$payload['device']['item_name']}**\n- **IMEI / Serial**: `{$payload['device']['imei']}`\n- **Current Status**: **" . strtoupper($payload['device']['status']) . "**\n- **Stock Location**: {$payload['device']['location']}"
                : "### Device Search Result\n\nNo device matching `" . htmlspecialchars($payload['search_term'] ?? 'query') . "` was found in the inventory ledger.",
            default                     => self::formatGeneralOverviewSummary($payload),
        };
    }

    private static function formatSuspiciousActivitySummary(array $p): string
    {
        $out = "### Security & Risk Audit Report\n\n";
        $out .= "**Scope**: {$p['scope']} | **Risk Assessment**: **{$p['overall_risk_level']}**\n\n";

        if (!empty($p['risk_flags'])) {
            $out .= "#### Key Risk Indicators\n";
            foreach ($p['risk_flags'] as $flag) {
                $out .= "- ⚠️ {$flag}\n";
            }
            $out .= "\n";
        }

        if (!empty($p['discount_by_cashier'])) {
            $out .= "#### Cashier Discount Activity (Past 30 Days)\n\n";
            $out .= "| Cashier | Discounted Sales | Total Discounts (₦) | Max Single Discount (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- |\n";
            foreach ($p['discount_by_cashier'] as $d) {
                $out .= "| {$d['cashier']} | {$d['discounted_sales']} | ₦" . number_format($d['total_discount'], 2) . " | ₦" . number_format($d['max_single'], 2) . " |\n";
            }
            $out .= "\n";
        }

        if (!empty($p['returns_by_cashier'])) {
            $out .= "#### Refund & Return Volumes by Staff\n\n";
            $out .= "| Cashier | Return Invoices | Items Returned | Total Refunded (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- |\n";
            foreach ($p['returns_by_cashier'] as $r) {
                $out .= "| {$r['cashier']} | {$r['returns_count']} | {$r['items_returned']} pcs | ₦" . number_format($r['refunded_amount'], 2) . " |\n";
            }
            $out .= "\n";
        }

        $out .= "#### Actionable Advice\n- Review staff members showing unusually high discount frequency.\n- Resolve pending stock conflicts to prevent inventory leakage.";
        return $out;
    }

    private static function formatStaffRankingSummary(array $p): string
    {
        $out = "### Staff Performance & Sales Ranking\n\n";
        $out .= "**Scope**: {$p['scope']} | **Period**: {$p['period']} | **Top Performer**: **{$p['top_performer']}**\n\n";

        if (!empty($p['leaderboard'])) {
            $out .= "| Rank | Cashier Name | Orders | Total Revenue (₦) | Profit (₦) | Avg Sale (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['leaderboard'] as $s) {
                $out .= "| #{$s['rank']} | {$s['name']} | {$s['sales_count']} | ₦" . number_format($s['revenue'], 2) . " | ₦" . number_format($s['profit_generated'], 2) . " | ₦" . number_format($s['average_sale'], 2) . " |\n";
            }
            $out .= "\n";
        }

        $out .= "#### Key Performance Highlights\n";
        $out .= "- **Top Volume Driver**: **{$p['top_performer']}** led store sales volume over the evaluated period.\n";
        $out .= "- Review average ticket sizes to incentivize cross-selling and upselling among lower-ranked staff.";
        return $out;
    }

    private static function formatGrowthTrajectorySummary(array $p): string
    {
        $out = "### Business Growth & Trajectory Forecast\n\n";
        $out .= "**Scope**: {$p['scope']} | **Trajectory Status**: **{$p['trajectory_status']}**\n\n";

        $out .= "| Interval | Orders | Revenue (₦) | Gross Profit (₦) | Growth Rate |\n";
        $out .= "| :--- | :--- | :--- | :--- | :--- |\n";
        $out .= "| This Month | {$p['this_month']['orders']} | ₦" . number_format($p['this_month']['revenue'], 2) . " | ₦" . number_format($p['this_month']['profit'], 2) . " | **" . ($p['month_on_month_growth'] >= 0 ? '+' : '') . "{$p['month_on_month_growth']}% MoM** |\n";
        $out .= "| Last Month | {$p['last_month']['orders']} | ₦" . number_format($p['last_month']['revenue'], 2) . " | ₦" . number_format($p['last_month']['profit'], 2) . " | Baseline |\n";
        $out .= "| This Week | {$p['this_week']['orders']} | ₦" . number_format($p['this_week']['revenue'], 2) . " | ₦" . number_format($p['this_week']['profit'], 2) . " | **" . ($p['week_on_week_growth'] >= 0 ? '+' : '') . "{$p['week_on_week_growth']}% WoW** |\n";
        $out .= "\n";

        $out .= "#### Strategic Growth Takeaways\n";
        $out .= "- **Gross Profit Margin**: Currently standing at **{$p['gross_profit_margin']}%**.\n";
        $out .= "- **Profit Velocity**: Profit growth stands at **{$p['profit_growth_rate']}%**.\n";
        $out .= "- Continue prioritizing high-margin product bundles to sustain positive revenue momentum.";
        return $out;
    }

    private static function formatSalesVelocitySummary(array $p): string
    {
        $out = "### Sales Rate & Velocity Analysis\n\n";
        $out .= "**Scope**: {$p['scope']} | **30-Day Orders**: {$p['total_orders_30d']} | **Daily Velocity**: **{$p['daily_order_velocity']} orders/day**\n\n";

        $out .= "#### Payment Settlement Distribution\n\n";
        $out .= "| Payment Method | Amount (₦) | Percentage Share |\n";
        $out .= "| :--- | :--- | :--- |\n";
        $out .= "| Cash Collected | ₦" . number_format($p['payment_mix']['cash_amount'], 2) . " | {$p['payment_mix']['cash_pct']}% |\n";
        $out .= "| Bank Transfer | ₦" . number_format($p['payment_mix']['transfer_amount'], 2) . " | {$p['payment_mix']['transfer_pct']}% |\n";
        $out .= "| Customer Debt / Credit | ₦" . number_format($p['payment_mix']['debt_amount'], 2) . " | {$p['payment_mix']['debt_pct']}% |\n\n";

        if (!empty($p['peak_rush_hours'])) {
            $out .= "#### Peak Rush Hours (Top 3 Time Slots)\n\n";
            $out .= "| Hour Slot | Transactions | Revenue (₦) |\n";
            $out .= "| :--- | :--- | :--- |\n";
            foreach ($p['peak_rush_hours'] as $h) {
                $out .= "| {$h['time_slot']} | {$h['orders']} orders | ₦" . number_format($h['revenue'], 2) . " |\n";
            }
            $out .= "\n";
        }

        $out .= "- **Average Basket Value**: **₦" . number_format($p['average_basket_value'], 2) . "** per completed transaction.";
        return $out;
    }

    private static function formatCrossBranchSummary(array $p): string
    {
        $out = "### Cross-Branch Performance Leaderboard\n\n";
        $out .= "**Scope**: System-Wide Multi-Branch | **Total Revenue (30D)**: **₦" . number_format($p['total_system_revenue'], 2) . "**\n\n";

        if (!empty($p['branch_leaderboard'])) {
            $out .= "| Rank | Store / Branch Name | Orders (30D) | Revenue (₦) | Profit (₦) | Market Share |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['branch_leaderboard'] as $b) {
                $out .= "| #{$b['rank']} | {$b['branch_name']} | {$b['orders_30d']} | ₦" . number_format($b['revenue_30d'], 2) . " | ₦" . number_format($b['profit_30d'], 2) . " | **{$b['market_share_pct']}%** |\n";
            }
            $out .= "\n";
        }

        $out .= "#### Strategic Recommendation\n- Focus inventory allocation on leading branches while replicating top cashier playbooks across underperforming locations.";
        return $out;
    }

    private static function formatTopSellersSummary(array $p): string
    {
        $out = "### Top Selling Products ({$p['period']})\n\n";
        $out .= "**Scope**: {$p['scope']}\n\n";

        if (!empty($p['top_items'])) {
            $out .= "| Rank | Product Name | Units Sold | Revenue (₦) | Est. Profit (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['top_items'] as $i) {
                $out .= "| #{$i['rank']} | {$i['item_name']} | {$i['units_sold']} pcs | ₦" . number_format($i['revenue'], 2) . " | ₦" . number_format($i['profit'], 2) . " |\n";
            }
            $out .= "\n";
        }

        $out .= "#### Key Inventory Action\n- Ensure top sellers remain well-stocked to avoid stockouts during peak operational periods.";
        return $out;
    }

    private static function formatInventoryStatusSummary(array $p): string
    {
        $out = "### Inventory Valuation & Restock Status\n\n";
        $out .= "**Scope**: {$p['scope']} | **Total Valuation**: **₦" . number_format($p['total_stock_worth'], 2) . "** across {$p['total_catalog']} products.\n\n";

        if (!empty($p['items_at_risk'])) {
            $out .= "#### Products At Risk / Low Stock\n\n";
            $out .= "| Product Name | Category | Front Store | Back Store | Reorder Point |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['items_at_risk'] as $item) {
                $out .= "| {$item['item_name']} | {$item['category']} | **{$item['front_stock']} pcs** | {$item['back_stock']} pcs | {$item['reorder_point']} pcs |\n";
            }
            $out .= "\n";
        }

        $out .= "- **Low Stock Alert**: **{$p['low_stock_count']} items** currently at or below minimum threshold.";
        return $out;
    }

    private static function formatDebtHealthSummary(array $p): string
    {
        $out = "### Customer Credit & Debt Recovery Overview\n\n";
        $out .= "**Scope**: {$p['scope']}\n\n";
        $out .= "- **Total Outstanding Debt**: **₦" . number_format($p['total_outstanding'], 2) . "**\n";
        $out .= "- **Debt Recovered This Month**: **₦" . number_format($p['debt_recovered_month'], 2) . "**\n\n";

        if (!empty($p['top_debtors'])) {
            $out .= "#### Top Customer Debt Balances\n\n";
            $out .= "| Customer Name | Phone Number | Balance Owing (₦) |\n";
            $out .= "| :--- | :--- | :--- |\n";
            foreach ($p['top_debtors'] as $d) {
                $out .= "| {$d['customer_name']} | {$d['phone']} | **₦" . number_format($d['balance_owing'], 2) . "** |\n";
            }
            $out .= "\n";
        }

        $out .= "#### Recovery Recommendation\n- Prioritize follow-up calls and payment schedules for top balance accounts.";
        return $out;
    }

    private static function formatGeneralOverviewSummary(array $p): string
    {
        $out = "### Business Performance Executive Summary\n\n";
        $out .= "**Scope**: {$p['scope']}\n\n";

        $out .= "| Interval | Transactions | Revenue (₦) | Gross Profit (₦) |\n";
        $out .= "| :--- | :--- | :--- | :--- |\n";
        $out .= "| Today | {$p['today']['orders']} | ₦" . number_format($p['today']['revenue'], 2) . " | ₦" . number_format($p['today']['profit'], 2) . " |\n";
        $out .= "| This Week | {$p['this_week']['orders']} | ₦" . number_format($p['this_week']['revenue'], 2) . " | ₦" . number_format($p['this_week']['profit'], 2) . " |\n";
        $out .= "| Last Week | {$p['last_week']['orders']} | ₦" . number_format($p['last_week']['revenue'], 2) . " | ₦" . number_format($p['last_week']['profit'], 2) . " |\n";
        $out .= "| This Month | {$p['this_month']['orders']} | ₦" . number_format($p['this_month']['revenue'], 2) . " | ₦" . number_format($p['this_month']['profit'], 2) . " |\n\n";

        $out .= "#### Operational Snapshot\n";
        $out .= "- **Week-on-Week Growth**: **" . ($p['week_on_week_pct'] >= 0 ? '+' : '') . "{$p['week_on_week_pct']}%**\n";
        $out .= "- **Active Catalog**: Managing **{$p['total_products']} products** with **{$p['low_stock_count']} low-stock items** needing attention.";
        return $out;
    }
}
