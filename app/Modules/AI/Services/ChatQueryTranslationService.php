<?php

namespace App\Modules\AI\Services;

use App\Models\AppNotification;
use App\Models\Branch;
use App\Models\DebtPayment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use App\Models\InventoryTransaction;
use App\Models\Item;
use App\Models\ItemDeviceUnit;
use App\Models\PosCustomer;
use App\Models\PosLog;
use App\Models\Sale;
use App\Models\SaleDiscount;
use App\Models\SaleOrder;
use App\Models\SaleReturnItem;
use App\Models\StockConflict;
use App\Models\User;
use App\Modules\AI\Models\AiConversationLog;
use App\Services\RoleService;
use Carbon\Carbon;
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

        // 0. Branch resolution from question (enforcing user role boundaries)
        if ($user->is_super_admin) {
            $allBranches = Branch::where('is_active', true)->get();
            foreach ($allBranches as $b) {
                if (
                    str_contains($q, strtolower($b->name)) ||
                    (!empty($b->slug) && str_contains($q, strtolower($b->slug)))
                ) {
                    $branch = $b;
                    break;
                }
            }
        } elseif ($user->isOrganizationAdmin()) {
            // Organization Admin: strictly scope to branches in their organization
            $orgBranches = Branch::where('organization_id', $user->organization_id)->get();
            $matched = null;
            foreach ($orgBranches as $b) {
                if (
                    str_contains($q, strtolower($b->name)) ||
                    (!empty($b->slug) && str_contains($q, strtolower($b->slug)))
                ) {
                    $matched = $b;
                    break;
                }
            }
            if ($matched) {
                $branch = $matched;
            } elseif ($branch && $branch->organization_id !== $user->organization_id) {
                $branch = null;
            }
        } else {
            // Branch user: strictly isolate to their assigned branch
            $branch = current_branch() ?: ($user->branch_id ? Branch::find($user->branch_id) : null);
        }

        // 1. Detect Intent and execute structured DB queries
        $intentData = self::resolveIntentAndData($q, $branch, $user);
        $intent     = $intentData['intent'];
        $payload    = $intentData['data'];
        $grounding  = $intentData['grounding'];

        // 2. Synthesize with AI Provider (or fallback rule-based template if unconfigured)
        $aiManager = app(AiManager::class);
        $aiResponse = '';
        $providerUsed = 'template_fallback';
        $modelUsed = 'local_rules';

        if ($intent === 'permission_denied') {
            $providerUsed = 'local_rules';
            $modelUsed = 'rbac_guard';
            $aiResponse = self::formatPermissionDeniedSummary($payload);
        } elseif ($aiManager->isBranchEnabled($branch)) {
            try {
                $provider = $aiManager->getActiveProvider();
                if ($provider) {
                    $providerUsed = $provider->getIdentifier();
                    $modelUsed = $provider->getModel();

                    $scopeLabel = $branch ? "Branch: {$branch->name}" : "All System Branches (Super Admin Cross-Branch)";
                    $systemPrompt = <<<SYS
You are "Skynet AI Executive Business Analyst", the embedded intelligent business copilot for Skynet Digital POS.
You are analyzing verified business data for: {$scopeLabel}.
Analyze the user's business question using the provided verified database metrics and records.

Core Analytical & Presentation Guidelines:
1. ALWAYS answer the user's question directly and concisely in the first 2 sentences, then provide full supporting data.
2. If asked about a SPECIFIC PRODUCT (e.g. stock left, price, last sold):
   - State the EXACT available quantity broken down into Front Store (cashier shelf) and Back Store (warehouse reserve).
   - State the unit retail selling price, wholesale price, and packaging tiers/variants if present.
   - Explicitly mention the EXACT LAST TIME it was sold (date, time, cashier name, quantity sold, receipt number, and total price).
   - Provide 30-day sales volume and any restock history.
   - If in Super Admin scope, display stock availability across other active store branches.
3. If looking up a RECEIPT / SALE RECORD:
   - Provide a short plain-language summary of the sale (total amount, store branch, date/time, cashier name, payment method, discounts, and customer).
   - Present a clean Markdown Table of all items purchased (item name, unit/variant, quantity, unit price, total price, and IMEI serials if present).
   - Show subtotal, discounts, tax, and final amount paid.
4. If asked about HELD SALES:
   - State the exact number of open held sales and total monetary value currently held.
   - If requested, highlight held sales older than 24 hours.
   - Present a table detailing hold name, customer, cashier, item count, total value, and when it was placed on hold.
5. If asked about BRANCH EXPENSES:
   - State the total operating expenses for the requested period directly.
   - Provide category breakdown with amounts and percentages in a Markdown Table.
   - If comparative (e.g. this month vs last month), clearly state the expense variance, monetary difference, and percentage change.
6. If asked about INVENTORY VALUATION / TOTAL ITEM WORTH:
   - State total inventory value at cost price (buy price) and retail selling value.
   - If for a specific item, state its total quantity (front + back store), unit cost, unit price, total cost worth, and retail value.
   - If comparing branches, rank branches by inventory value.
7. If asked about CUSTOMER DEBT & DEBT ANALYSIS:
   - State total outstanding debt across customers and recovery amount.
   - If looking up a specific customer, state their current balance, contact, and recent payment history.
   - If analyzing overdue debt (e.g. >30 days without payment), highlight aged balances and list overdue debtors.
8. If asked about STAFF ACTIVITY or AUDIT LOGS:
   - Provide a clear breakdown or Markdown Table of recent staff actions, cashier sales, inventory adjustments, and timestamped activity logs.
9. Format all monetary values cleanly using the Nigerian Naira currency symbol (₦) with commas (e.g. ₦1,250,000.00).
10. When presenting multiple comparative metrics, rankings, inventory lists, or activity logs, ALWAYS format them in clean Markdown Tables using the '|' pipe syntax.
11. Highlight key takeaways, product quantities, timestamps, or risk flags using **bold text**.
12. NEVER speculate or fabricate figures not present in the verified database payload.
SYS;

                    $payloadJson = json_encode($payload, JSON_PRETTY_PRINT);
                    $prompt = <<<USER_PROMPT
User Question: "{$question}"

Verified Database Records:
{$payloadJson}

Please provide a structured, executive-ready answer addressing the question directly based on these exact verified database records:
USER_PROMPT;

                    $aiResponse = $provider->chat([
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ], [
                        'temperature' => 0.25,
                        'max_tokens'  => 1500,
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

        // ── 0. ABSOLUTE PRIORITY: Specific Receipt Lookup ────────────────────
        // Direct pattern check for receipt/invoice numbers (e.g. "RC202609260173", "SKN-483920", "OFFLINE-...", "receipt #173")
        $receiptCandidate = self::detectReceiptNumber($q);
        if ($receiptCandidate) {
            return self::resolveReceiptLookup($receiptCandidate, $branch, $user, $scopeName);
        }

        // ── 1. Held Sales & Carts on Hold ────────────────────────────────────
        if (
            str_contains($q, 'held sale') || str_contains($q, 'held sales') ||
            str_contains($q, 'held cart') || str_contains($q, 'hold cart') ||
            str_contains($q, 'sales on hold') || str_contains($q, 'held orders') ||
            str_contains($q, 'held order') ||
            (str_contains($q, 'hold') && (str_contains($q, 'open') || str_contains($q, 'value') || str_contains($q, 'total') || str_contains($q, 'how many') || str_contains($q, 'show') || str_contains($q, 'older')))
        ) {
            return self::resolveHeldSales($q, $branch, $user, $scopeName);
        }

        // ── 2. Branch Operating Expenses ─────────────────────────────────────
        if (
            str_contains($q, 'expense') || str_contains($q, 'expenses') ||
            str_contains($q, 'spending') || str_contains($q, 'spend on') ||
            str_contains($q, 'spent on') || str_contains($q, 'did i spend') ||
            str_contains($q, 'did we spend') || str_contains($q, 'operating cost') ||
            str_contains($q, 'overhead')
        ) {
            return self::resolveBranchExpenses($q, $branch, $user, $scopeName);
        }

        // ── 3. Inventory Valuation & Total Item Worth ────────────────────────
        $hasInventoryWord = str_contains($q, 'inventory') ||
            str_contains($q, 'stock') ||
            str_contains($q, 'sock') || // common typo for stock
            str_contains($q, 'item') ||
            str_contains($q, 'items') ||
            str_contains($q, 'goods') ||
            str_contains($q, 'catalog');

        $hasValuationWord = str_contains($q, 'worth') ||
            str_contains($q, 'wort') || // common typo for worth
            str_contains($q, 'valuation') ||
            str_contains($q, 'value') ||
            str_contains($q, 'carrying the most') ||
            str_contains($q, 'most value') ||
            str_contains($q, 'total value') ||
            str_contains($q, 'stock value') ||
            str_contains($q, 'cost value') ||
            str_contains($q, 'how much is') ||
            str_contains($q, 'how much are');

        if (
            ($hasInventoryWord && $hasValuationWord) ||
            str_contains($q, 'inventory worth') ||
            str_contains($q, 'stock worth') ||
            str_contains($q, 'inventory value') ||
            str_contains($q, 'stock valuation') ||
            str_contains($q, 'worth of the stock') ||
            str_contains($q, 'worth of stock') ||
            str_contains($q, 'value of stock') ||
            str_contains($q, 'value of the stock')
        ) {
            return self::resolveInventoryValuation($q, $branch, $user, $scopeName);
        }

        // ── 4. Specific Item / Product Search ────────────────────────────────
        // If query asks about a particular item, stock count, price, last sold, or mentions an item name
        $itemSearchResults = self::searchSpecificItemData($q, $branch, $user);
        if (!empty($itemSearchResults['items'])) {
            // If the query is specifically focused on an item lookup (e.g. stock, price, last sold, how many, or item name)
            $isExplicitItemQuery = str_contains($q, 'how many') || str_contains($q, 'left') ||
                str_contains($q, 'stock') || str_contains($q, 'price') || str_contains($q, 'cost') ||
                str_contains($q, 'last sold') || str_contains($q, 'when was') || str_contains($q, 'sold') ||
                str_contains($q, 'do we have') || str_contains($q, 'available') || str_contains($q, 'details') ||
                str_contains($q, 'barcode') || str_contains($q, 'warehouse') || str_contains($q, 'store') ||
                str_contains($q, 'reorder') || str_contains($q, 'carton') || str_contains($q, 'pack') ||
                count($itemSearchResults['items']) === 1;

            if ($isExplicitItemQuery) {
                return [
                    'intent'    => 'item_specific_lookup',
                    'grounding' => 'Queried live item catalog, front/back inventory, packaging unit variants, recent 30-day sales, and exact last sold transaction.',
                    'data'      => [
                        'scope'            => $scopeName,
                        'matched_products' => $itemSearchResults['items'],
                        'search_query'     => $itemSearchResults['search_query'],
                        'total_matches'    => count($itemSearchResults['items']),
                    ],
                ];
            }
        }

        // ── 1. Staff Activity Logs & Weekly Audit Trail ───────────────────────
        if (
            str_contains($q, 'activity') || str_contains($q, 'audit log') || str_contains($q, 'activity log') ||
            str_contains($q, 'what did staff') || str_contains($q, 'staff action') || str_contains($q, 'what happened') ||
            str_contains($q, 'who logged') || str_contains($q, 'log of') || str_contains($q, 'history of') ||
            str_contains($q, 'weekly log') || str_contains($q, 'past week') || str_contains($q, 'last 7 days')
        ) {
            return self::resolveStaffActivityAudit($q, $branch, $user, $scopeName);
        }

        // ── 2. Suspicious Activity / Fraud & Risk Detection ──────────────────
        if (
            str_contains($q, 'suspicious') || str_contains($q, 'fraud') || str_contains($q, 'theft') ||
            str_contains($q, 'anomaly') || str_contains($q, 'risk') || str_contains($q, 'audit') ||
            str_contains($q, 'discrepancy') || str_contains($q, 'unusual') || str_contains($q, 'loss')
        ) {
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

        // ── 3. Staff Performance & Top Performers Ranking ───────────────────
        if (
            str_contains($q, 'perform') || str_contains($q, 'cashier') || str_contains($q, 'staff') ||
            str_contains($q, 'employee') || str_contains($q, 'who sold') || str_contains($q, 'worker') ||
            str_contains($q, 'agent') || str_contains($q, 'sales rep')
        ) {
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

        // ── 4. Business Growth & Trajectory Prediction ───────────────────────
        if (
            str_contains($q, 'grow') || str_contains($q, 'growth') || str_contains($q, 'trajectory') ||
            str_contains($q, 'trend') || str_contains($q, 'predict') || str_contains($q, 'forecast') ||
            str_contains($q, 'expansion') || str_contains($q, 'rate of growth') || str_contains($q, 'future')
        ) {
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

        // ── 5. Sales Rate, Hourly Velocity & Payment Settlement Breakdown ────
        if (
            str_contains($q, 'sale rate') || str_contains($q, 'rate of sale') || str_contains($q, 'velocity') ||
            str_contains($q, 'rush') || str_contains($q, 'peak hour') || str_contains($q, 'busiest') ||
            str_contains($q, 'basket') || str_contains($q, 'payment method')
        ) {
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

        // ── 6. Cross-Branch Comparison (Super Admin Scope) ───────────────────
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

        // ── 7. Top Selling Items ─────────────────────────────────────────────
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

        // ── 8. Low Stock & Inventory Valuation ───────────────────────────────
        if (
            str_contains($q, 'stock') || str_contains($q, 'low') || str_contains($q, 'run out') ||
            str_contains($q, 'inventory') || str_contains($q, 'reorder') || str_contains($q, 'warehouse')
        ) {
            $lowStockItems = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->where('front_store_qty', '<=', 10)
                ->with('category:id,name')
                ->orderBy('front_store_qty')
                ->limit(12)
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

        // ── 9. Customer Debt & Debt Analysis ─────────────────────────────────
        if (
            str_contains($q, 'debt') || str_contains($q, 'credit') || str_contains($q, 'debtor') ||
            str_contains($q, 'unpaid') || str_contains($q, 'owing') || str_contains($q, 'recover') ||
            str_contains($q, 'owe') || str_contains($q, 'haven\'t paid') || str_contains($q, 'havent paid') ||
            (str_contains($q, 'balance') && (str_contains($q, 'customer') || str_contains($q, 'current') || str_contains($q, '\'s balance')))
        ) {
            return self::resolveCustomerDebtAnalysis($q, $branch, $user, $scopeName);
        }

        // ── 10. Tax & VAT Collected ──────────────────────────────────────────
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

        // ── 11. IMEI / Device Serial Lookup ──────────────────────────────────
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

        // ── 12. General Business Overview & Executive Briefing ───────────────
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

        // Recent 5 activity logs for situational awareness
        $recentActivityLogs = PosLog::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($l) => [
                'user'    => $l->user?->name ?? 'System',
                'action'  => $l->action_type,
                'log'     => $l->log,
                'time'    => $l->created_at ? $l->created_at->diffForHumans() : 'Recent',
            ])->all();

        return [
            'intent'    => 'sales_performance',
            'grounding' => 'Queried multi-tier real-time metrics across sales orders, profits, catalog counts, and inventory health.',
            'data'      => [
                'scope'                 => $scopeName,
                'today'                 => ['orders' => (int)$todaySales->count, 'revenue' => (float)$todaySales->revenue, 'profit' => (float)$todaySales->profit],
                'this_week'             => ['orders' => (int)$thisWeekSales->count, 'revenue' => (float)$thisWeekSales->revenue, 'profit' => (float)$thisWeekSales->profit],
                'last_week'             => ['orders' => (int)$lastWeekSales->count, 'revenue' => (float)$lastWeekSales->revenue, 'profit' => (float)$lastWeekSales->profit],
                'this_month'            => ['orders' => (int)$thisMonthSales->count, 'revenue' => (float)$thisMonthSales->revenue, 'profit' => (float)$thisMonthSales->profit],
                'week_on_week_pct'      => $wowGrowth,
                'total_products'        => (int)$totalItems,
                'low_stock_count'       => (int)$lowStockCount,
                'recent_activity_trail' => $recentActivityLogs,
            ],
        ];
    }

    /**
     * Search specific product catalog and enrich with inventory, last sale, 30-day velocity, and cross-branch stock.
     */
    protected static function searchSpecificItemData(string $q, ?Branch $branch, User $user): array
    {
        // 1. Clean query to extract product candidate tokens
        $stopWords = [
            'how', 'many', 'is', 'are', 'left', 'in', 'stock', 'of', 'for', 'the', 'a', 'an',
            'what', 'price', 'cost', 'much', 'when', 'was', 'last', 'sold', 'with', 'time',
            'date', 'do', 'we', 'have', 'can', 'you', 'tell', 'me', 'about', 'details',
            'give', 'check', 'search', 'lookup', 'available', 'remaining', 'count', 'quantity',
            'warehouse', 'store', 'front', 'back', 'item', 'product', 'please', 'there',
            'at', 'our', 'branch', 'shop', 'business', 'currently', 'show', 'find', 'get',
            // Valuation & General Inventory inquiry tokens to prevent accidental product matches:
            'total', 'inventory', 'worth', 'wort', 'value', 'valuation', 'my', 'right', 'now',
            'all', 'overall', 'entire', 'whole', 'current', 'today', 'goods', 'sock', 'level',
            'levels', 'summary', 'report', 'worthwhile', 'whats', 'what\'s', 'calculate', 'bring'
        ];

        $tokens = preg_split('/[\s,;:\?\!]+/', $q);
        $cleanTokens = array_values(array_filter($tokens, fn($t) => strlen($t) > 1 && !in_array(strtolower($t), $stopWords)));
        $cleanSearchString = implode(' ', $cleanTokens);

        if (empty($cleanTokens) && empty($cleanSearchString)) {
            return ['items' => [], 'search_query' => ''];
        }

        // 2. Query Item matching candidates
        $itemQuery = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->with(['category:id,name', 'units' => fn($u) => $u->where('status', 'active')]);

        // Attempt exact or phrase LIKE
        $items = (clone $itemQuery)
            ->where(function($query) use ($cleanSearchString, $cleanTokens) {
                $query->where('item_name', 'like', "%{$cleanSearchString}%")
                      ->orWhere('barcode_number', 'like', "%{$cleanSearchString}%");

                // If multiple tokens, try matching all
                if (count($cleanTokens) > 1) {
                    $query->orWhere(function($sub) use ($cleanTokens) {
                        foreach ($cleanTokens as $token) {
                            $sub->where('item_name', 'like', "%{$token}%");
                        }
                    });
                }
            })
            ->limit(5)
            ->get();

        // If not found, try searching individual prominent keywords (> 2 chars)
        if ($items->isEmpty() && count($cleanTokens) > 0) {
            $prominentTokens = array_filter($cleanTokens, fn($t) => strlen($t) >= 3);
            if (!empty($prominentTokens)) {
                $items = (clone $itemQuery)
                    ->where(function($query) use ($prominentTokens) {
                        foreach ($prominentTokens as $token) {
                            $query->orWhere('item_name', 'like', "%{$token}%");
                        }
                    })
                    ->limit(5)
                    ->get();
            }
        }

        if ($items->isEmpty()) {
            return ['items' => [], 'search_query' => $cleanSearchString];
        }

        // 3. Enrich each matched item with last sold, 30-day velocity, and cross-branch distribution
        $enrichedItems = $items->map(function(Item $item) use ($branch, $user) {
            $totalQty = (int) $item->total_qty;
            $frontStock = (int) $item->front_store_qty;
            $backStock = (int) $item->back_store_qty;

            // A. Last time this item was sold
            $lastSaleOrder = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
                ->where('sale_orders.item_id', $item->id)
                ->when($branch, fn($q) => $q->where('sales.branch_id', $branch->id))
                ->with(['user:id,name,username', 'sale.customer:id,name,phone'])
                ->select(
                    'sale_orders.id as order_id',
                    'sale_orders.qty',
                    'sale_orders.unit_used',
                    'sale_orders.selling_price',
                    'sale_orders.total_selling_price',
                    'sales.id as sale_id',
                    'sales.receipt_id',
                    'sales.created_at as sale_datetime',
                    'sales.user_id as cashier_id',
                    'sales.customer_id'
                )
                ->orderByDesc('sales.created_at')
                ->first();

            $lastSoldData = null;
            if ($lastSaleOrder) {
                $saleTime = Carbon::parse($lastSaleOrder->sale_datetime);
                $lastSoldData = [
                    'exact_datetime'    => $saleTime->format('Y-m-d H:i:s'),
                    'human_time'        => $saleTime->format('l, M j, Y \a\t h:i A') . ' (' . $saleTime->diffForHumans() . ')',
                    'qty_sold'          => (int) $lastSaleOrder->qty,
                    'unit_tier'         => $lastSaleOrder->unit_used ?: ($item->unit_label ?: 'Unit'),
                    'unit_price'        => (float) $lastSaleOrder->selling_price,
                    'total_price'       => (float) $lastSaleOrder->total_selling_price,
                    'cashier_name'      => $lastSaleOrder->user?->name ?? 'Cashier',
                    'receipt_id'        => $lastSaleOrder->receipt_id ?? "Sale #{$lastSaleOrder->sale_id}",
                    'customer_name'     => $lastSaleOrder->sale?->customer?->name ?? 'Walk-in Customer',
                ];
            }

            // B. 30-Day Sales Velocity for this item
            $sales30d = SaleOrder::join('sales', 'sales.id', '=', 'sale_orders.sale_id')
                ->where('sale_orders.item_id', $item->id)
                ->when($branch, fn($q) => $q->where('sales.branch_id', $branch->id))
                ->where('sales.created_at', '>=', now()->subDays(30))
                ->selectRaw('COALESCE(SUM(sale_orders.qty), 0) as total_qty, COALESCE(SUM(sale_orders.total_selling_price), 0) as total_rev, COUNT(DISTINCT sales.id) as orders_count')
                ->first();

            // C. Multi-unit Packaging Tiers (Variants)
            $packagingVariants = $item->units->map(fn($u) => [
                'unit_name'       => $u->unit_name,
                'multiplier'      => (int) $u->base_unit_multiplier,
                'selling_price'   => (float) $u->selling_price,
                'wholesale_price' => (float) $u->wholesale_price,
                'barcode'         => $u->barcode_number ?: 'N/A',
            ])->all();

            // D. Cross-branch stock distribution (if Super Admin or multi-branch query)
            $crossBranchStock = [];
            if ($user->is_super_admin) {
                $crossBranchStock = Item::where('item_name', $item->item_name)
                    ->orWhere(function($q) use ($item) {
                        if (!empty($item->barcode_number)) {
                            $q->where('barcode_number', $item->barcode_number);
                        }
                    })
                    ->with('branch:id,name')
                    ->get()
                    ->map(fn($other) => [
                        'branch_name' => $other->branch?->name ?? 'Main Store',
                        'front_stock' => (int) $other->front_store_qty,
                        'back_stock'  => (int) $other->back_store_qty,
                        'total_stock' => (int) $other->total_qty,
                    ])->all();
            }

            // E. Last restocked / inventory transaction
            $lastRestock = InventoryTransaction::where('item_id', $item->id)
                ->when($branch, fn($q) => $q->where('branch_id', $branch->id))
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->first();

            $restockData = null;
            if ($lastRestock) {
                $restockData = [
                    'transaction_type' => $lastRestock->transaction_type,
                    'qty_change'       => (int) $lastRestock->qty,
                    'logged_by'        => $lastRestock->user?->name ?? 'System',
                    'notes'            => $lastRestock->notes ?: 'Stock Update',
                    'datetime'         => $lastRestock->created_at ? $lastRestock->created_at->format('M j, Y h:i A') : 'Recent',
                ];
            }

            return [
                'item_id'            => $item->id,
                'item_name'          => $item->item_name,
                'barcode_number'     => $item->barcode_number ?: 'N/A',
                'category'           => $item->category?->name ?? 'General',
                'unit_label'         => $item->unit_label ?: 'Unit',
                'front_store_qty'    => $frontStock,
                'back_store_qty'     => $backStock,
                'total_stock_qty'    => $totalQty,
                'stock_status'       => $totalQty <= 0 ? 'Out of Stock' : ($frontStock <= (int)($item->reorder_point ?? 5) ? 'Low Stock Warning' : 'Healthy In-Stock'),
                'reorder_point'      => (int) ($item->reorder_point ?? 5),
                'buy_price'          => (float) $item->buy_price,
                'selling_price'      => (float) $item->price,
                'wholesale_price'    => (float) $item->wholesale_price,
                'profit_margin_unit' => round((float)$item->price - (float)$item->buy_price, 2),
                'packaging_variants' => $packagingVariants,
                'last_sold_record'   => $lastSoldData ?: 'No sales recorded yet for this item.',
                'sales_velocity_30d' => [
                    'units_sold'     => (int) ($sales30d->total_qty ?? 0),
                    'revenue'        => (float) ($sales30d->total_rev ?? 0),
                    'orders_count'   => (int) ($sales30d->orders_count ?? 0),
                ],
                'last_restock_log'   => $restockData ?: 'No recent inventory restock logs found.',
                'cross_branch_stock' => $crossBranchStock,
            ];
        })->all();

        return [
            'items'        => $enrichedItems,
            'search_query' => $cleanSearchString,
        ];
    }

    /**
     * Resolve 7-Day Staff Activity Log, Inventory Adjustments & Cashier Operations.
     */
    protected static function resolveStaffActivityAudit(string $q, ?Branch $branch, User $user, string $scopeName): array
    {
        // 1. Check if asking about a specific staff member
        $targetUser = null;
        $allUsers = User::when($branch, fn($query) => $query->where('branch_id', $branch->id))->get();
        foreach ($allUsers as $u) {
            if (str_contains($q, strtolower($u->name)) || str_contains($q, strtolower($u->username))) {
                $targetUser = $u;
                break;
            }
        }

        // 2. Query PosLogs for past 7 days
        $logs = PosLog::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->when($targetUser, fn($query) => $query->where('user_id', $targetUser->id))
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['user:id,name,username', 'branch:id,name'])
            ->orderByDesc('created_at')
            ->limit(35)
            ->get()
            ->map(fn($l) => [
                'id'          => $l->id,
                'staff_name'  => $l->user?->name ?? 'System / Anonymous',
                'action_type' => $l->action_type,
                'log_summary' => $l->log,
                'details'     => $l->details,
                'exact_time'  => $l->created_at ? $l->created_at->format('Y-m-d H:i:s') : 'N/A',
                'time_human'  => $l->created_at ? $l->created_at->format('M j, h:i A') . ' (' . $l->created_at->diffForHumans() . ')' : 'Recent',
                'branch'      => $l->branch?->name ?? 'Main',
            ])->all();

        // 3. Query Inventory Transactions logged by staff past 7 days
        $inventoryActions = InventoryTransaction::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->when($targetUser, fn($query) => $query->where('user_id', $targetUser->id))
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['user:id,name', 'item:id,item_name'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn($i) => [
                'staff_name'       => $i->user?->name ?? 'System',
                'item_name'        => $i->item?->item_name ?? 'Unknown Item',
                'transaction_type' => $i->transaction_type,
                'qty_change'       => (int) $i->qty,
                'previous_qty'     => (int) $i->previous_qty,
                'new_qty'          => (int) $i->new_qty,
                'notes'            => $i->notes ?: 'Inventory Update',
                'time_human'       => $i->created_at ? $i->created_at->format('M j, h:i A') : 'Recent',
            ])->all();

        // 4. Per-staff 7-day sales breakdown
        $staffSalesBreakdown = Sale::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->when($targetUser, fn($query) => $query->where('user_id', $targetUser->id))
            ->where('created_at', '>=', now()->subDays(7))
            ->with('user:id,name')
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('COALESCE(SUM(final_total), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(discount_amount), 0) as total_discounts'),
                DB::raw('COALESCE(SUM(profit_made), 0) as total_profit')
            )
            ->groupBy('user_id')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(fn($s) => [
                'staff_name'      => $s->user?->name ?? 'Unknown',
                'orders_count'    => (int) $s->total_orders,
                'revenue'         => (float) $s->total_revenue,
                'discounts_given' => (float) $s->total_discounts,
                'profit'          => (float) $s->total_profit,
            ])->all();

        return [
            'intent'    => 'staff_activity_audit',
            'grounding' => 'Queried past 7 days pos_logs, inventory transactions, and cashier sales orders.',
            'data'      => [
                'scope'                 => $scopeName,
                'target_staff'          => $targetUser ? $targetUser->name : 'All Staff Members',
                'period'                => 'Past 7 Days (Weekly Audit Trail)',
                'total_activity_events' => count($logs),
                'activity_logs'         => $logs,
                'inventory_actions'     => $inventoryActions,
                'staff_sales_summary'   => $staffSalesBreakdown,
            ],
        ];
    }

    /**
     * RBAC helper to verify specific permission capability on a user instance.
     */
    protected static function userCan(User $user, string $permission): bool
    {
        if ($user->isSuperAdmin() || $user->isBranchAdmin() || $user->isOrganizationAdmin()) {
            return true;
        }
        if ($user->branch_id) {
            setPermissionsTeamId($user->branch_id);
        }
        try {
            if ($user->permissions->isNotEmpty() && $user->hasDirectPermission($permission)) {
                return true;
            }
            return $user->hasPermissionTo($permission);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Detect if an input query is a receipt/invoice identifier or specific sale inquiry.
     */
    public static function detectReceiptNumber(string $query): ?string
    {
        $q = trim($query);
        $stopWords = ['are', 'is', 'the', 'open', 'closed', 'held', 'sales', 'order', 'orders', 'details', 'left', 'stock', 'today', 'yesterday', 'what', 'how', 'many'];

        // 1. Standalone receipt/offline identifier (e.g. "RC202609260173", "SKN-483920", "OFFLINE-1788594698-A")
        if (preg_match('/^(?:rc|skn|rec|offline)[a-z0-9\-_]+$/i', $q, $m)) {
            return $m[0];
        }

        // 2. Query mentioning receipt or invoice (e.g. "receipt #173", "receipt 173", "show receipt RC202609260173")
        if (preg_match('/\b(?:receipt|invoice)\s*(?:#|no\.?|num\.?|number)?\s*([a-z0-9\-_]+)/i', $q, $m)) {
            $candidate = trim($m[1]);
            if (!in_array(strtolower($candidate), $stopWords) && (preg_match('/[0-9]/', $candidate) || strlen($candidate) >= 4)) {
                return $candidate;
            }
        }

        // 3. Query mentioning specific sale number or ID (e.g. "sale #173", "sale number 174")
        if (preg_match('/\b(?:sale)\s+(?:#|no\.?|number|id)\s*([a-z0-9\-_]+)/i', $q, $m)) {
            $candidate = trim($m[1]);
            if (!in_array(strtolower($candidate), $stopWords) && preg_match('/[0-9]/', $candidate)) {
                return $candidate;
            }
        }

        // 4. Embedded receipt code pattern inside a query (e.g. "Can you check RC202609260173 for me?")
        if (preg_match('/\b(rc\d{4,}|skn-?[a-z0-9]+|offline-[a-z0-9\-]+)\b/i', $q, $m)) {
            return $m[1];
        }

        // 5. Exact match against existing receipt_id or offline_sale_id in database
        $cleaned = preg_replace('/^#/', '', $q);
        if (strlen($cleaned) >= 4 && Sale::where('receipt_id', $cleaned)->orWhere('offline_sale_id', $cleaned)->exists()) {
            return $cleaned;
        }

        return null;
    }

    /**
     * Resolve exact receipt lookup with full line item details and plain-language summary.
     */
    protected static function resolveReceiptLookup(string $receiptCode, ?Branch $branch, User $user, string $scopeName): array
    {
        // 1. Permission check
        $canViewSales = self::userCan($user, 'canViewSales') || RoleService::canViewSales();
        if (!$canViewSales) {
            return [
                'intent'    => 'permission_denied',
                'grounding' => 'Enforced RBAC permission boundary for sale receipt lookup.',
                'data'      => [
                    'feature'             => 'Receipt & Sale Records',
                    'required_permission' => 'canViewSales',
                    'message'             => 'You do not have permission to view sales records or receipt details.',
                ],
            ];
        }

        $cleaned = trim($receiptCode);
        $saleQuery = Sale::with([
            'branch:id,name,slug',
            'user:id,name,username',
            'customer:id,name,phone,address',
            'saleDiscount',
            'saleOrders.item:id,item_name,barcode_number,unit_label,price,wholesale_price',
            'saleOrders.deviceUnit:id,imei_or_device_id',
            'returnItems',
        ]);

        $foundSale = null;
        if (is_numeric($cleaned)) {
            $foundSale = (clone $saleQuery)->where('id', (int)$cleaned)->first()
                      ?: (clone $saleQuery)->where('receipt_id', $cleaned)->first();
        }
        if (!$foundSale) {
            $foundSale = (clone $saleQuery)->where('receipt_id', $cleaned)->first()
                      ?: (clone $saleQuery)->where('offline_sale_id', $cleaned)->first();
        }
        if (!$foundSale && strlen($cleaned) >= 4) {
            $foundSale = (clone $saleQuery)->where('receipt_id', 'like', "%{$cleaned}%")
                      ->orWhere('offline_sale_id', 'like', "%{$cleaned}%")
                      ->latest()
                      ->first();
        }

        // Tenant scoping rule:
        // Platform Super Admin -> can view all receipts
        // Organization Admin -> can only view receipts from branches within their organization
        // Branch staff -> can only view receipts from their assigned branch
        if ($foundSale && !$user->is_super_admin) {
            if ($user->isOrganizationAdmin()) {
                $orgBranchIds = Branch::where('organization_id', $user->organization_id)->pluck('id')->all();
                if (!in_array($foundSale->branch_id, $orgBranchIds)) {
                    return [
                        'intent'    => 'receipt_lookup',
                        'grounding' => 'Enforced organization tenant isolation boundary for sale receipt lookup.',
                        'data'      => [
                            'receipt_no' => $receiptCode,
                            'found'      => false,
                            'error'      => 'cross_org_denied',
                            'message'    => "Receipt **{$receiptCode}** exists in the system but belongs to another organization. You can only look up receipts issued by your organization's branches.",
                        ],
                    ];
                }
            } else {
                $userBranchId = $branch ? $branch->id : $user->branch_id;
                if ($userBranchId && $foundSale->branch_id !== $userBranchId) {
                    return [
                        'intent'    => 'receipt_lookup',
                        'grounding' => 'Enforced branch isolation boundary for sale receipt lookup.',
                        'data'      => [
                            'receipt_no' => $receiptCode,
                            'found'      => false,
                            'error'      => 'cross_branch_denied',
                            'message'    => "Receipt **{$receiptCode}** exists in the system but belongs to another branch (**{$foundSale->branch?->name}**). As a branch user, you can only look up receipts issued at **" . ($branch?->name ?? 'your branch') . "**.",
                        ],
                    ];
                }
            }
        }

        if (!$foundSale) {
            return [
                'intent'    => 'receipt_lookup',
                'grounding' => 'Queried live sales table by receipt_id, offline_sale_id, and primary key ID.',
                'data'      => [
                    'receipt_no' => $receiptCode,
                    'found'      => false,
                    'message'    => "No sale record was found matching receipt number **\"{$receiptCode}\"** in {$scopeName}. Please verify the receipt number and try again.",
                ],
            ];
        }

        // Transform sale items
        $itemsPurchased = $foundSale->saleOrders->map(function ($order) {
            return [
                'item_name'     => $order->item_name ?: ($order->item?->item_name ?? 'Custom Item'),
                'variant_unit'  => $order->unit_used ?: ($order->item?->unit_label ?: 'Unit'),
                'purchase_type' => $order->purchase_type ?: 'Consumer',
                'qty'           => (int) $order->qty,
                'unit_price'    => (float) $order->selling_price,
                'total_price'   => (float) $order->total_selling_price,
                'imei_serial'   => $order->imei_or_device_id ?: ($order->deviceUnit?->imei_or_device_id ?? null),
            ];
        })->values()->all();

        $subtotal = (float) $foundSale->saleOrders->sum('total_selling_price');
        $discountAmount = (float) $foundSale->discount_amount;
        $taxAmount = (float) $foundSale->tax_amount;
        $finalTotal = (float) $foundSale->final_total;

        $saleDetails = [
            'receipt_no'        => $foundSale->receipt_id ?: "Sale #{$foundSale->id}",
            'offline_sale_id'   => $foundSale->offline_sale_id,
            'is_offline_sale'   => (bool) $foundSale->is_offline_sale,
            'sale_id'           => $foundSale->id,
            'branch_name'       => $foundSale->branch?->name ?? 'Main Branch',
            'date_formatted'    => $foundSale->created_at->format('F j, Y \a\t g:i A'),
            'date_human'        => $foundSale->created_at->diffForHumans(),
            'cashier_name'      => $foundSale->user?->name ?? 'Cashier',
            'cashier_username'  => $foundSale->user?->username ?? '',
            'customer_name'     => $foundSale->customer?->name ?? 'Walk-in Customer',
            'customer_phone'    => $foundSale->customer?->phone ?? 'N/A',
            'payment_method'    => ucfirst($foundSale->payment_method ?: 'Cash'),
            'cash_paid'         => (float) $foundSale->cash,
            'bank_transfer'     => (float) $foundSale->bank_transfer,
            'change_given'      => (float) $foundSale->change_bal,
            'is_debt'           => (bool) $foundSale->is_debt,
            'subtotal'          => $subtotal,
            'discount_amount'   => $discountAmount,
            'discount_details'  => $foundSale->saleDiscount?->description ?? ($discountAmount > 0 ? 'Applied Sale Discount' : 'None'),
            'tax_amount'        => $taxAmount,
            'tax_percentage'    => (float) $foundSale->tax_percentage,
            'final_total'       => $finalTotal,
            'items'             => $itemsPurchased,
            'total_items_count' => count($itemsPurchased),
        ];

        // Short AI-friendly summary sentence
        $summaryParts = [];
        $summaryParts[] = "This was a ₦" . number_format($finalTotal, 2) . " sale at " . $saleDetails['branch_name'] . " on " . $foundSale->created_at->format('M j, Y') . ", paid by " . strtolower($saleDetails['payment_method']) . ".";
        if ($discountAmount > 0) {
            $summaryParts[] = "Includes a ₦" . number_format($discountAmount, 2) . " discount.";
        }
        if (count($itemsPurchased) === 1) {
            $summaryParts[] = "Purchased: {$itemsPurchased[0]['qty']}x {$itemsPurchased[0]['item_name']}.";
        } else {
            $summaryParts[] = "Purchased " . count($itemsPurchased) . " item types (Total quantity: " . $foundSale->saleOrders->sum('qty') . ").";
        }
        $aiSummaryText = implode(' ', $summaryParts);

        return [
            'intent'    => 'receipt_lookup',
            'grounding' => 'Queried verified sale transaction, cashier audit, customer ledger, line items, and discounts.',
            'data'      => [
                'scope'      => $scopeName,
                'found'      => true,
                'ai_summary' => $aiSummaryText,
                'sale'       => $saleDetails,
            ],
        ];
    }

    /**
     * Resolve held sales overview, total value sitting in held carts, and aged carts.
     */
    protected static function resolveHeldSales(string $q, ?Branch $branch, User $user, string $scopeName): array
    {
        $isOlderThanDay = str_contains($q, 'older than a day') ||
                          str_contains($q, 'older than 1 day') ||
                          str_contains($q, 'older than 24 hours') ||
                          str_contains($q, 'past day') ||
                          str_contains($q, 'aged');

        $query = HeldSale::where('status', 'Held')
            ->when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->when($isOlderThanDay, fn($query) => $query->where('created_at', '<=', now()->subHours(24)))
            ->with([
                'branch:id,name',
                'user:id,name,username',
                'customer:id,name,phone',
                'items.item:id,item_name,unit_label',
            ])
            ->orderBy('created_at', 'asc');

        $heldSales = $query->get();

        $totalCount = $heldSales->count();
        $totalValue = (float) $heldSales->sum(function ($h) {
            return $h->items->sum(fn($i) => (float)$i->price * (int)$i->qty);
        });

        $totalUnits = (int) $heldSales->sum(function ($h) {
            return $h->items->sum('qty');
        });

        $mappedSales = $heldSales->map(function ($h) {
            $cartVal = (float) $h->items->sum(fn($i) => (float)$i->price * (int)$i->qty);
            $itemsList = $h->items->map(fn($i) => [
                'item_name' => $i->item_name ?: ($i->item?->item_name ?? 'Item'),
                'qty'       => (int) $i->qty,
                'price'     => (float) $i->price,
                'unit'      => $i->unit_used ?: 'unit',
            ])->values()->all();

            return [
                'id'            => $h->id,
                'uuid'          => $h->held_sale_uuid,
                'hold_name'     => $h->hold_name ?: "Cart #{$h->id}",
                'branch_name'   => $h->branch?->name ?? 'Main',
                'customer_name' => $h->customer?->name ?? 'Walk-in Customer',
                'cashier_name'  => $h->user?->name ?? 'Cashier',
                'created_at'    => $h->created_at->format('M j, Y h:i A'),
                'time_human'    => $h->created_at->diffForHumans(),
                'hours_open'    => round(now()->diffInHours($h->created_at, true), 1),
                'items_count'   => count($itemsList),
                'total_units'   => (int) $h->items->sum('qty'),
                'total_value'   => $cartVal,
                'items_preview' => $itemsList,
            ];
        })->values()->all();

        return [
            'intent'    => 'held_sales_overview',
            'grounding' => 'Queried live held_sales and held_sale_items tables for open active held carts.',
            'data'      => [
                'scope'             => $scopeName,
                'filter'            => $isOlderThanDay ? 'Older than 24 hours' : 'All Currently Open Held Sales',
                'total_held_sales'  => $totalCount,
                'total_held_value'  => $totalValue,
                'total_held_units'  => $totalUnits,
                'held_sales_list'   => $mappedSales,
            ],
        ];
    }

    /**
     * Resolve operating expenses with date ranges, categories, and permission checks.
     */
    protected static function resolveBranchExpenses(string $q, ?Branch $branch, User $user, string $scopeName): array
    {
        // 1. Permission Check
        $canView = self::userCan($user, 'canViewExpenseReports') || self::userCan($user, 'canViewReports') || RoleService::canViewExpenseReports();
        if (!$canView) {
            return [
                'intent'    => 'permission_denied',
                'grounding' => 'Enforced RBAC permission boundary for operating expense reports.',
                'data'      => [
                    'feature'             => 'Operating Expenses',
                    'required_permission' => 'canViewExpenseReports',
                    'message'             => 'You do not have permission to view branch expense data or reports.',
                ],
            ];
        }

        // Determine date range
        $isLastMonth = str_contains($q, 'last month');
        $isThisWeek = str_contains($q, 'this week');
        $isLastWeek = str_contains($q, 'last week');
        $isToday = str_contains($q, 'today');
        $isYesterday = str_contains($q, 'yesterday');
        $isComparative = str_contains($q, 'compare') || str_contains($q, 'compared');

        if ($isLastMonth) {
            $startDate = now()->subMonth()->startOfMonth();
            $endDate = now()->subMonth()->endOfMonth();
            $periodLabel = 'Last Month (' . $startDate->format('F Y') . ')';
        } elseif ($isLastWeek) {
            $startDate = now()->subWeek()->startOfWeek();
            $endDate = now()->subWeek()->endOfWeek();
            $periodLabel = 'Last Week (' . $startDate->format('M j') . ' - ' . $endDate->format('M j, Y') . ')';
        } elseif ($isThisWeek) {
            $startDate = now()->startOfWeek();
            $endDate = now()->endOfWeek();
            $periodLabel = 'This Week (' . $startDate->format('M j') . ' - ' . $endDate->format('M j, Y') . ')';
        } elseif ($isToday) {
            $startDate = today()->startOfDay();
            $endDate = today()->endOfDay();
            $periodLabel = 'Today (' . today()->format('M j, Y') . ')';
        } elseif ($isYesterday) {
            $startDate = today()->subDay()->startOfDay();
            $endDate = today()->subDay()->endOfDay();
            $periodLabel = 'Yesterday (' . today()->subDay()->format('M j, Y') . ')';
        } else {
            // Default to This Month
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
            $periodLabel = 'This Month (' . $startDate->format('F Y') . ')';
        }

        // Check for specific category mention
        $matchedCategory = null;
        $allCategories = ExpenseCategory::all();
        foreach ($allCategories as $cat) {
            if (str_contains($q, strtolower($cat->name))) {
                $matchedCategory = $cat;
                break;
            }
        }
        if (!$matchedCategory) {
            if (str_contains($q, 'transport') || str_contains($q, 'logistics')) {
                $matchedCategory = ExpenseCategory::where('name', 'like', '%transport%')->first();
            } elseif (str_contains($q, 'fuel') || str_contains($q, 'diesel') || str_contains($q, 'petrol')) {
                $matchedCategory = ExpenseCategory::where('name', 'like', '%fuel%')->first();
            } elseif (str_contains($q, 'salary') || str_contains($q, 'salaries') || str_contains($q, 'wage')) {
                $matchedCategory = ExpenseCategory::where('name', 'like', '%salary%')->orWhere('name', 'like', '%wage%')->first();
            }
        }

        // Query primary period expenses
        $baseQuery = Expense::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->whereBetween('expense_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($matchedCategory) {
            $baseQuery->where('expense_category_id', $matchedCategory->id);
        }

        $totalAmount = (float) (clone $baseQuery)->sum('amount');
        $totalCount = (int) (clone $baseQuery)->count();
        $daysCount = max(1, (int) $startDate->diffInDays($endDate) + 1);
        $dailyAverage = round($totalAmount / $daysCount, 2);

        // Category breakdown
        $categoryBreakdown = Expense::when($branch, fn($query) => $query->where('expenses.branch_id', $branch->id))
            ->whereBetween('expenses.expense_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($matchedCategory, fn($query) => $query->where('expenses.expense_category_id', $matchedCategory->id))
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.name as category_name',
                DB::raw('COUNT(expenses.id) as count'),
                DB::raw('SUM(expenses.amount) as total_amount')
            )
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn($row) => [
                'category_name' => $row->category_name,
                'count'         => (int) $row->count,
                'total_amount'  => (float) $row->total_amount,
                'percentage'    => $totalAmount > 0 ? round(((float)$row->total_amount / $totalAmount) * 100, 1) : 0,
            ])->values()->all();

        // Comparative period calculations if comparative requested or comparing months
        $comparativeData = null;
        if ($isComparative || (str_contains($q, 'compare') && str_contains($q, 'last month'))) {
            $prevStart = now()->subMonth()->startOfMonth();
            $prevEnd = now()->subMonth()->endOfMonth();

            $prevQuery = Expense::when($branch, fn($query) => $query->where('branch_id', $branch->id))
                ->whereBetween('expense_date', [$prevStart->toDateString(), $prevEnd->toDateString()]);

            if ($matchedCategory) {
                $prevQuery->where('expense_category_id', $matchedCategory->id);
            }

            $prevTotal = (float) $prevQuery->sum('amount');
            $prevCount = (int) $prevQuery->count();
            $diffAmount = $totalAmount - $prevTotal;
            $diffPct = $prevTotal > 0 ? round(($diffAmount / $prevTotal) * 100, 1) : 0;

            $comparativeData = [
                'previous_period'   => 'Last Month (' . $prevStart->format('F Y') . ')',
                'previous_total'    => $prevTotal,
                'previous_count'    => $prevCount,
                'difference_amount' => $diffAmount,
                'difference_pct'    => $diffPct,
                'trend'             => $diffAmount > 0 ? 'Increased' : ($diffAmount < 0 ? 'Decreased' : 'Unchanged'),
            ];
        }

        return [
            'intent'    => 'branch_expenses_overview',
            'grounding' => 'Queried expenses and expense_categories tables respecting RBAC expense reporting boundaries.',
            'data'      => [
                'scope'               => $scopeName,
                'period'              => $periodLabel,
                'category_filter'     => $matchedCategory ? $matchedCategory->name : 'All Categories',
                'total_expense'       => $totalAmount,
                'expense_count'       => $totalCount,
                'daily_average'       => $dailyAverage,
                'category_breakdown'  => $categoryBreakdown,
                'top_category'        => $categoryBreakdown[0] ?? null,
                'comparative'         => $comparativeData,
            ],
        ];
    }

    /**
     * Resolve inventory valuation at cost price (buy price) and retail selling value.
     */
    protected static function resolveInventoryValuation(string $q, ?Branch $branch, User $user, string $scopeName): array
    {
        // 1. Check if asking cross-branch comparison: "Which branch is carrying the most inventory value?"
        $isCrossBranch = (!$branch || str_contains($q, 'which branch') || str_contains($q, 'across branches') || str_contains($q, 'most inventory value') || str_contains($q, 'compare branches'));
        if ($isCrossBranch && $user->is_super_admin) {
            $branches = Branch::where('is_active', true)->get();
            $branchValuations = $branches->map(function ($b) {
                $items = Item::where('branch_id', $b->id)->get();
                $costVal = (float) $items->sum(fn($i) => (float)($i->stock_worth > 0 ? $i->stock_worth : ((float)$i->buy_price * ((int)$i->front_store_qty + (int)$i->back_store_qty))));
                $retailVal = (float) $items->sum(fn($i) => (float)$i->price * ((int)$i->front_store_qty + (int)$i->back_store_qty));
                $totalUnits = (int) $items->sum(fn($i) => (int)$i->front_store_qty + (int)$i->back_store_qty);

                return [
                    'branch_id'        => $b->id,
                    'branch_name'      => $b->name,
                    'total_products'   => $items->count(),
                    'total_units'      => $totalUnits,
                    'cost_valuation'   => round($costVal, 2),
                    'retail_valuation' => round($retailVal, 2),
                    'potential_profit' => round($retailVal - $costVal, 2),
                ];
            })->sortByDesc('cost_valuation')->values()->map(fn($row, $idx) => array_merge($row, ['rank' => $idx + 1]))->all();

            $topBranch = $branchValuations[0] ?? null;

            return [
                'intent'    => 'inventory_valuation',
                'grounding' => 'Aggregated total stock_worth and retail valuation across all registered active branches.',
                'data'      => [
                    'scope'                 => 'All Active Branches (Super Admin)',
                    'type'                  => 'cross_branch_ranking',
                    'top_branch'            => $topBranch ? $topBranch['branch_name'] : 'N/A',
                    'top_branch_value'      => $topBranch ? $topBranch['cost_valuation'] : 0,
                    'total_system_cost_val' => (float) array_sum(array_column($branchValuations, 'cost_valuation')),
                    'branch_rankings'       => $branchValuations,
                ],
            ];
        }

        // 2. Check if asking about a specific item (e.g. "What's the stock value of Peak Milk Sachet?")
        // NOTE: Only consider specific item if query is NOT explicitly asking for total / overall / store inventory worth!
        $isGeneralValuation = str_contains($q, 'total') ||
            str_contains($q, 'overall') ||
            str_contains($q, 'entire') ||
            str_contains($q, 'whole') ||
            str_contains($q, 'all') ||
            str_contains($q, 'in the store') ||
            str_contains($q, 'in store') ||
            str_contains($q, 'in the shop') ||
            str_contains($q, 'in shop') ||
            str_contains($q, 'my inventory') ||
            str_contains($q, 'our inventory') ||
            str_contains($q, 'the inventory') ||
            str_contains($q, 'my stock') ||
            str_contains($q, 'our stock') ||
            str_contains($q, 'the stock') ||
            str_contains($q, 'right now') ||
            str_contains($q, 'currently') ||
            str_contains($q, 'today') ||
            str_contains($q, 'worth of the sock') ||
            str_contains($q, 'wort of the sock') ||
            str_contains($q, 'worth of the stock') ||
            str_contains($q, 'sock in the store') ||
            str_contains($q, 'inventory worth') ||
            str_contains($q, 'stock worth') ||
            str_contains($q, 'inventory value') ||
            str_contains($q, 'stock valuation') ||
            str_contains($q, 'inventory valuation') ||
            !str_contains($q, ' of ');

        if (!$isGeneralValuation) {
            $itemResults = self::searchSpecificItemData($q, $branch, $user);
            if (!empty($itemResults['items']) && count($itemResults['items']) <= 3) {
                $item = $itemResults['items'][0];
                $costValuation = (float)$item['buy_price'] * (int)$item['total_stock_qty'];
                $retailValuation = (float)$item['selling_price'] * (int)$item['total_stock_qty'];
                $estimatedProfit = $retailValuation - $costValuation;
                $marginPct = $retailValuation > 0 ? round(($estimatedProfit / $retailValuation) * 100, 1) : 0;

                return [
                    'intent'    => 'inventory_valuation',
                    'grounding' => 'Calculated cost valuation and retail selling potential for specific matched inventory item.',
                    'data'      => [
                        'scope'                 => $scopeName,
                        'type'                  => 'specific_item',
                        'item_name'             => $item['item_name'],
                        'barcode'               => $item['barcode_number'],
                        'category'              => $item['category'],
                        'total_stock_qty'       => (int)$item['total_stock_qty'],
                        'front_store_qty'       => (int)$item['front_store_qty'],
                        'back_store_qty'        => (int)$item['back_store_qty'],
                        'unit_label'            => $item['unit_label'],
                        'buy_price'             => (float)$item['buy_price'],
                        'selling_price'         => (float)$item['selling_price'],
                        'wholesale_price'       => (float)$item['wholesale_price'],
                        'cost_valuation'        => round($costValuation, 2),
                        'retail_valuation'      => round($retailValuation, 2),
                        'potential_profit'      => round($estimatedProfit, 2),
                        'gross_margin_pct'      => $marginPct,
                        'cross_branch_stock'    => $item['cross_branch_stock'] ?? [],
                    ],
                ];
            }
        }

        // 3. General Branch / Store Inventory Worth
        $itemsQuery = Item::when($branch, fn($query) => $query->where('branch_id', $branch->id));
        $allItems = $itemsQuery->get();

        $totalCostValuation = (float) $allItems->sum(function($i) {
            $qty = (int)$i->front_store_qty + (int)$i->back_store_qty;
            if ($i->stock_worth > 0) {
                return (float) $i->stock_worth;
            }
            return (float)$i->buy_price * $qty;
        });
        $totalRetailValuation = (float) $allItems->sum(fn($i) => (float)$i->price * ((int)$i->front_store_qty + (int)$i->back_store_qty));
        $totalFrontUnits = (int) $allItems->sum('front_store_qty');
        $totalBackUnits = (int) $allItems->sum('back_store_qty');
        $totalUnits = $totalFrontUnits + $totalBackUnits;
        $potentialProfit = $totalRetailValuation - $totalCostValuation;
        $grossMarginPct = $totalRetailValuation > 0 ? round(($potentialProfit / $totalRetailValuation) * 100, 1) : 0;

        // Top 5 highest-worth items
        $topValuedItems = $allItems->sortByDesc(function($i) {
            $qty = (int)$i->front_store_qty + (int)$i->back_store_qty;
            return (float)($i->stock_worth > 0 ? $i->stock_worth : ((float)$i->buy_price * $qty));
        })->take(5)->map(function($i) {
            $qty = (int)$i->front_store_qty + (int)$i->back_store_qty;
            $worth = (float)($i->stock_worth > 0 ? $i->stock_worth : ((float)$i->buy_price * $qty));
            return [
                'item_name'       => $i->item_name,
                'total_qty'       => $qty,
                'cost_price'      => (float) $i->buy_price,
                'retail_price'    => (float) $i->price,
                'stock_worth'     => round($worth, 2),
                'retail_worth'    => round((float)$i->price * $qty, 2),
            ];
        })->values()->all();

        return [
            'intent'    => 'inventory_valuation',
            'grounding' => 'Queried live items catalog computing cost valuation (stock_worth) and retail asset potential.',
            'data'      => [
                'scope'                  => $scopeName,
                'type'                   => 'general_valuation',
                'total_cost_valuation'   => round($totalCostValuation, 2),
                'total_retail_valuation' => round($totalRetailValuation, 2),
                'potential_gross_profit' => round($potentialProfit, 2),
                'gross_margin_pct'       => $grossMarginPct,
                'total_catalog_products' => $allItems->count(),
                'total_units_in_stock'   => $totalUnits,
                'front_store_units'      => $totalFrontUnits,
                'back_store_units'       => $totalBackUnits,
                'top_value_items'        => $topValuedItems,
            ],
        ];
    }

    /**
     * Resolve customer debt queries, aging analysis (>30 days), and individual balance lookups.
     */
    protected static function resolveCustomerDebtAnalysis(string $q, ?Branch $branch, User $user, string $scopeName): array
    {
        // 1. Permission check: canManageDebt
        $canManageDebt = self::userCan($user, 'canManageDebt') || self::userCan($user, 'canManageCustomers') || RoleService::canManageDebt();
        if (!$canManageDebt) {
            return [
                'intent'    => 'permission_denied',
                'grounding' => 'Enforced RBAC permission boundary for customer debt ledger and credit balances.',
                'data'      => [
                    'feature'             => 'Customer Debt Ledger',
                    'required_permission' => 'canManageDebt',
                    'message'             => 'You do not have permission to view customer debt balances or the debt ledger.',
                ],
            ];
        }

        // 2. Check if looking up a specific customer (e.g. "What's Felix's current balance?", "balance of John")
        $matchedCustomer = null;
        $customers = PosCustomer::when($branch, fn($query) => $query->where('branch_id', $branch->id))->get();
        foreach ($customers as $c) {
            if (
                (!empty($c->name) && str_contains($q, strtolower($c->name))) ||
                (!empty($c->phone) && str_contains($q, $c->phone))
            ) {
                $matchedCustomer = $c;
                break;
            }
        }

        if ($matchedCustomer) {
            // Load ledger entries
            $payments = DebtPayment::where('customer_id', $matchedCustomer->id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            $lastCredit = DebtPayment::where('customer_id', $matchedCustomer->id)
                ->where('type', 'credit')
                ->latest('created_at')
                ->first();

            $totalDebited = (float) DebtPayment::where('customer_id', $matchedCustomer->id)->where('type', 'debit')->sum('amount');
            $totalCredited = (float) DebtPayment::where('customer_id', $matchedCustomer->id)->where('type', 'credit')->sum('amount');

            return [
                'intent'    => 'customer_debt_analysis',
                'grounding' => 'Queried specific customer debt ledger and running balance history.',
                'data'      => [
                    'scope'                 => $scopeName,
                    'type'                  => 'specific_customer',
                    'customer_id'           => $matchedCustomer->id,
                    'customer_name'         => $matchedCustomer->name,
                    'phone'                 => $matchedCustomer->phone ?? 'N/A',
                    'current_balance'       => (float) $matchedCustomer->debt_bal,
                    'watch_list'            => (bool) $matchedCustomer->watch_list,
                    'total_debited'         => $totalDebited,
                    'total_credited'        => $totalCredited,
                    'last_payment_date'     => $lastCredit ? $lastCredit->created_at->format('M j, Y h:i A') : 'No payments recorded',
                    'last_payment_amount'   => $lastCredit ? (float)$lastCredit->amount : 0,
                    'recent_ledger_history' => $payments->map(fn($p) => [
                        'type'           => $p->type,
                        'amount'         => (float) $p->amount,
                        'balance_after'  => (float) $p->balance_after,
                        'narration'      => $p->narration,
                        'staff'          => $p->user?->name ?? 'Staff',
                        'date'           => $p->created_at ? $p->created_at->format('Y-m-d H:i') : 'Recent',
                    ])->values()->all(),
                ],
            ];
        }

        // 3. Overdue debt analysis: "Which customers haven't paid in over 30 days?"
        $isOverdueQuery = str_contains($q, '30 days') ||
                          str_contains($q, 'haven\'t paid') ||
                          str_contains($q, 'havent paid') ||
                          str_contains($q, 'not paid') ||
                          str_contains($q, 'overdue');

        $activeDebtorsQuery = PosCustomer::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->where('debt_bal', '>', 0);

        $activeDebtors = $activeDebtorsQuery->get();
        $totalOutstandingDebt = (float) $activeDebtors->sum('debt_bal');

        // Debt recovered this month
        $debtRecoveredMonth = (float) DebtPayment::when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->where('type', 'credit')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        // Analyze debtor aging (last payment date)
        $enrichedDebtors = $activeDebtors->map(function ($d) {
            $lastPayment = DebtPayment::where('customer_id', $d->id)
                ->where('type', 'credit')
                ->latest('created_at')
                ->first();

            $lastDate = $lastPayment ? $lastPayment->created_at : $d->created_at;
            $daysSincePayment = $lastDate ? (int) $lastDate->diffInDays(now()) : 999;

            return [
                'customer_id'         => $d->id,
                'customer_name'       => $d->name,
                'phone'               => $d->phone ?? 'N/A',
                'debt_bal'            => (float) $d->debt_bal,
                'last_payment_date'   => $lastPayment ? $lastPayment->created_at->format('M j, Y') : 'Never Paid',
                'days_since_payment'  => $daysSincePayment,
                'is_overdue_30d'      => $daysSincePayment >= 30,
            ];
        });

        if ($isOverdueQuery) {
            $overdueList = $enrichedDebtors->filter(fn($d) => $d['is_overdue_30d'])->sortByDesc('debt_bal')->values()->all();
            $overdueTotal = (float) array_sum(array_column($overdueList, 'debt_bal'));

            return [
                'intent'    => 'customer_debt_analysis',
                'grounding' => 'Queried customer debt ledgers cross-referenced with 30-day payment activity timestamps.',
                'data'      => [
                    'scope'                 => $scopeName,
                    'type'                  => 'overdue_30_days',
                    'total_overdue_amount'  => $overdueTotal,
                    'overdue_debtors_count' => count($overdueList),
                    'total_debtors'         => $activeDebtors->count(),
                    'overdue_debtors'       => $overdueList,
                ],
            ];
        }

        // Top debtors ranking / General debt overview
        $topDebtors = $enrichedDebtors->sortByDesc('debt_bal')->take(10)->values()->all();

        return [
            'intent'    => 'customer_debt_analysis',
            'grounding' => 'Aggregated outstanding customer balances, debt recovery rates, and debtor ledger rankings.',
            'data'      => [
                'scope'                 => $scopeName,
                'type'                  => 'debt_overview',
                'total_outstanding'     => $totalOutstandingDebt,
                'debtors_count'         => $activeDebtors->count(),
                'debt_recovered_month'  => $debtRecoveredMonth,
                'top_debtors'           => $topDebtors,
            ],
        ];
    }

    /**
     * Generate fallback rich markdown summary when external AI provider is offline or not configured.
     */
    protected static function generateRuleBasedSummary(string $intent, array $payload, string $question): string
    {
        return match ($intent) {
            'permission_denied'            => self::formatPermissionDeniedSummary($payload),
            'receipt_lookup'               => self::formatReceiptLookupSummary($payload),
            'held_sales_overview'          => self::formatHeldSalesSummary($payload),
            'branch_expenses_overview'     => self::formatBranchExpensesSummary($payload),
            'inventory_valuation'          => self::formatInventoryValuationSummary($payload),
            'customer_debt_analysis'       => self::formatCustomerDebtSummary($payload),
            'item_specific_lookup'         => self::formatItemSpecificSummary($payload),
            'staff_activity_audit'         => self::formatStaffActivitySummary($payload),
            'suspicious_activity_audit'    => self::formatSuspiciousActivitySummary($payload),
            'staff_performance_ranking'    => self::formatStaffRankingSummary($payload),
            'growth_trajectory_prediction' => self::formatGrowthTrajectorySummary($payload),
            'sales_velocity_and_basket_rate' => self::formatSalesVelocitySummary($payload),
            'cross_branch_benchmarking'    => self::formatCrossBranchSummary($payload),
            'top_sellers'                  => self::formatTopSellersSummary($payload),
            'inventory_status'             => self::formatInventoryStatusSummary($payload),
            'debt_and_credit_health'       => self::formatDebtHealthSummary($payload),
            'tax_summary'                  => "### Tax & VAT Collection Brief\n\n- **Scope**: {$payload['scope']}\n- **Month-to-Date Tax**: **₦" . number_format($payload['this_month_tax'], 2) . "**\n- **All-Time Tax Collected**: **₦" . number_format($payload['all_time_tax'], 2) . "**\n\nAll tax figures are calculated strictly from completed sales invoices.",
            'imei_lookup'                  => $payload['found']
                ? "### Device IMEI Verification Record\n\n- **Device Item**: **{$payload['device']['item_name']}**\n- **IMEI / Serial**: `{$payload['device']['imei']}`\n- **Current Status**: **" . strtoupper($payload['device']['status']) . "**\n- **Stock Location**: {$payload['device']['location']}"
                : "### Device Search Result\n\nNo device matching `" . htmlspecialchars($payload['search_term'] ?? 'query') . "` was found in the inventory ledger.",
            default                        => self::formatGeneralOverviewSummary($payload),
        };
    }

    private static function formatItemSpecificSummary(array $p): string
    {
        $out = "### 📦 Product Stock & Sales Report\n\n";
        $out .= "**Scope**: {$p['scope']} | **Matched Products**: " . count($p['matched_products']) . "\n\n";

        foreach ($p['matched_products'] as $item) {
            $out .= "## **{$item['item_name']}**\n";
            $out .= "- **Barcode / SKU**: `{$item['barcode_number']}` | **Category**: {$item['category']}\n";
            $out .= "- **Available Stock**: **{$item['total_stock_qty']} {$item['unit_label']}** (Front Shelf: **{$item['front_store_qty']}**, Back Warehouse: **{$item['back_store_qty']}**)\n";
            $out .= "- **Stock Status**: **{$item['stock_status']}** (Reorder Threshold: {$item['reorder_point']} pcs)\n";
            $out .= "- **Pricing**: Retail Price: **₦" . number_format($item['selling_price'], 2) . "** | Wholesale: **₦" . number_format($item['wholesale_price'], 2) . "** | Buy Cost: **₦" . number_format($item['buy_price'], 2) . "** (Unit Margin: ₦" . number_format($item['profit_margin_unit'], 2) . ")\n\n";

            // Last Sold Section
            $out .= "#### 🕒 Last Time Sold\n";
            if (is_array($item['last_sold_record'])) {
                $ls = $item['last_sold_record'];
                $out .= "- **Date & Time**: **{$ls['human_time']}**\n";
                $out .= "- **Quantity Sold**: **{$ls['qty_sold']} {$ls['unit_tier']}** @ ₦" . number_format($ls['unit_price'], 2) . " (Total: **₦" . number_format($ls['total_price'], 2) . "**)\n";
                $out .= "- **Cashier**: **{$ls['cashier_name']}** | **Receipt**: `{$ls['receipt_id']}` | **Customer**: {$ls['customer_name']}\n\n";
            } else {
                $out .= "- *{$item['last_sold_record']}*\n\n";
            }

            // 30-Day Velocity
            $out .= "#### 📈 30-Day Sales Velocity\n";
            $out .= "- **Units Sold**: **{$item['sales_velocity_30d']['units_sold']} pcs** | **Revenue Generated**: **₦" . number_format($item['sales_velocity_30d']['revenue'], 2) . "** across {$item['sales_velocity_30d']['orders_count']} transactions.\n\n";

            // Packaging variants
            if (!empty($item['packaging_variants'])) {
                $out .= "#### 📦 Configured Packaging Tiers\n\n";
                $out .= "| Tier Name | Multiplier | Retail Price (₦) | Wholesale Price (₦) | Barcode |\n";
                $out .= "| :--- | :--- | :--- | :--- | :--- |\n";
                foreach ($item['packaging_variants'] as $v) {
                    $out .= "| {$v['unit_name']} | {$v['multiplier']}x base | ₦" . number_format($v['selling_price'], 2) . " | ₦" . number_format($v['wholesale_price'], 2) . " | `{$v['barcode']}` |\n";
                }
                $out .= "\n";
            }

            // Cross-branch breakdown
            if (!empty($item['cross_branch_stock'])) {
                $out .= "#### 🏬 Stock Across All Branches\n\n";
                $out .= "| Store / Branch | Front Shelf | Back Warehouse | Total On Hand |\n";
                $out .= "| :--- | :--- | :--- | :--- |\n";
                foreach ($item['cross_branch_stock'] as $b) {
                    $out .= "| {$b['branch_name']} | {$b['front_stock']} pcs | {$b['back_stock']} pcs | **{$b['total_stock']} pcs** |\n";
                }
                $out .= "\n";
            }

            $out .= "---\n\n";
        }

        return $out;
    }

    private static function formatStaffActivitySummary(array $p): string
    {
        $out = "### 📋 Staff Activity & Weekly Audit Log\n\n";
        $out .= "**Scope**: {$p['scope']} | **Target**: {$p['target_staff']} | **Period**: {$p['period']}\n\n";

        if (!empty($p['staff_sales_summary'])) {
            $out .= "#### 💼 7-Day Cashier Sales Performance\n\n";
            $out .= "| Staff Member | Orders Completed | Total Revenue (₦) | Discounts Given (₦) | Profit (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['staff_sales_summary'] as $s) {
                $out .= "| {$s['staff_name']} | {$s['orders_count']} | ₦" . number_format($s['revenue'], 2) . " | ₦" . number_format($s['discounts_given'], 2) . " | ₦" . number_format($s['profit'], 2) . " |\n";
            }
            $out .= "\n";
        }

        if (!empty($p['activity_logs'])) {
            $out .= "#### ⏱️ Recent Activity Stream (Past 7 Days)\n\n";
            $out .= "| Staff | Action | Details / Log | Time |\n";
            $out .= "| :--- | :--- | :--- | :--- |\n";
            foreach (array_slice($p['activity_logs'], 0, 15) as $l) {
                $out .= "| {$l['staff_name']} | `{$l['action_type']}` | {$l['log_summary']} | {$l['time_human']} |\n";
            }
            $out .= "\n";
        }

        if (!empty($p['inventory_actions'])) {
            $out .= "#### 📦 Staff Inventory Adjustments (Past 7 Days)\n\n";
            $out .= "| Staff | Item | Type | Qty Change | Notes | Time |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
            foreach (array_slice($p['inventory_actions'], 0, 10) as $i) {
                $out .= "| {$i['staff_name']} | {$i['item_name']} | {$i['transaction_type']} | {$i['qty_change']} pcs | {$i['notes']} | {$i['time_human']} |\n";
            }
            $out .= "\n";
        }

        return $out;
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
            $out .= "| :--- | :--- | :--- | :--- |\n";
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
            $out .= "| :--- | :--- | :--- | :--- |\n";
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

    private static function formatPermissionDeniedSummary(array $p): string
    {
        $out = "### ⛔ Access Restricted\n\n";
        $out .= $p['message'] . "\n\n";
        if (!empty($p['required_permission'])) {
            $out .= "*Under company security policy, this data requires the `{$p['required_permission']}` permission.*";
        }
        return $out;
    }

    private static function formatReceiptLookupSummary(array $p): string
    {
        if (empty($p['found'])) {
            return "### 🔍 Receipt Lookup Result\n\n" . ($p['message'] ?? 'Receipt not found.');
        }

        $sale = $p['sale'];
        $out = "### 🧾 Receipt Details: `{$sale['receipt_no']}`\n\n";
        if (!empty($p['ai_summary'])) {
            $out .= "> **Summary**: " . $p['ai_summary'] . "\n\n";
        }

        $out .= "#### 📋 Transaction Information\n\n";
        $out .= "| Property | Details |\n";
        $out .= "| :--- | :--- |\n";
        $out .= "| **Store Branch** | {$sale['branch_name']} |\n";
        $out .= "| **Date & Time** | {$sale['date_formatted']} ({$sale['date_human']}) |\n";
        $out .= "| **Cashier** | {$sale['cashier_name']} |\n";
        $out .= "| **Customer** | {$sale['customer_name']} (" . ($sale['customer_phone'] ?: 'N/A') . ") |\n";
        $out .= "| **Payment Method** | **{$sale['payment_method']}** " . ($sale['is_debt'] ? '(Debt Sale)' : '') . " |\n";
        if ($sale['offline_sale_id']) {
            $out .= "| **Offline Sale UUID** | `{$sale['offline_sale_id']}` |\n";
        }
        $out .= "\n";

        $out .= "#### 🛒 Purchased Line Items\n\n";
        $out .= "| Item Name | Unit / Tier | Type | Qty | Unit Price (₦) | Total (₦) | IMEI / Serial |\n";
        $out .= "| :--- | :--- | :--- | :--- | :--- | :--- | :--- |\n";
        foreach ($sale['items'] as $item) {
            $imei = $item['imei_serial'] ? "`{$item['imei_serial']}`" : '—';
            $out .= "| **{$item['item_name']}** | {$item['variant_unit']} | {$item['purchase_type']} | {$item['qty']} | ₦" . number_format($item['unit_price'], 2) . " | **₦" . number_format($item['total_price'], 2) . "** | {$imei} |\n";
        }
        $out .= "\n";

        $out .= "#### 💳 Payment & Settlement Breakdown\n\n";
        $out .= "- **Items Subtotal**: ₦" . number_format($sale['subtotal'], 2) . "\n";
        if ($sale['discount_amount'] > 0) {
            $out .= "- **Discount Applied**: **-₦" . number_format($sale['discount_amount'], 2) . "** ({$sale['discount_details']})\n";
        }
        if ($sale['tax_amount'] > 0) {
            $out .= "- **Tax / VAT ({$sale['tax_percentage']}%)**: ₦" . number_format($sale['tax_amount'], 2) . "\n";
        }
        $out .= "- **Final Total Paid**: **₦" . number_format($sale['final_total'], 2) . "**\n";
        if ($sale['cash_paid'] > 0) {
            $out .= "- **Cash Tendered**: ₦" . number_format($sale['cash_paid'], 2) . ($sale['change_given'] > 0 ? " (Change Given: ₦" . number_format($sale['change_given'], 2) . ")" : '') . "\n";
        }
        if ($sale['bank_transfer'] > 0) {
            $out .= "- **Bank Transfer**: ₦" . number_format($sale['bank_transfer'], 2) . "\n";
        }

        return $out;
    }

    private static function formatHeldSalesSummary(array $p): string
    {
        $out = "### ⏸️ Open Held Sales & Cart Holds\n\n";
        $out .= "**Scope**: {$p['scope']} | **Filter**: {$p['filter']}\n\n";
        $out .= "- **Currently Open Held Carts**: **{$p['total_held_sales']} open sales**\n";
        $out .= "- **Total Value in Held Carts**: **₦" . number_format($p['total_held_value'], 2) . "**\n";
        $out .= "- **Total Units on Hold**: **{$p['total_held_units']} pcs**\n\n";

        if (!empty($p['held_sales_list'])) {
            $out .= "#### 📑 Open Held Carts Directory\n\n";
            $out .= "| Hold Reference | Customer | Cashier | Units | Cart Value (₦) | Held Since |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['held_sales_list'] as $h) {
                $out .= "| **{$h['hold_name']}** | {$h['customer_name']} | {$h['cashier_name']} | {$h['total_units']} pcs | **₦" . number_format($h['total_value'], 2) . "** | {$h['time_human']} |\n";
            }
            $out .= "\n";

            $out .= "#### 📦 Cart Item Breakdowns\n\n";
            foreach ($p['held_sales_list'] as $h) {
                $out .= "- **{$h['hold_name']}** (by {$h['cashier_name']}): ";
                $itemStrs = array_map(fn($it) => "{$it['qty']}x {$it['item_name']} (@ ₦" . number_format($it['price'], 2) . ")", $h['items_preview']);
                $out .= implode(', ', $itemStrs) . "\n";
            }
        } else {
            $out .= "No held sales match the specified filter at this time. All sales carts are settled or clear.";
        }

        return $out;
    }

    private static function formatBranchExpensesSummary(array $p): string
    {
        $out = "### 💸 Operating Expenses Report\n\n";
        $out .= "**Scope**: {$p['scope']} | **Period**: {$p['period']}\n\n";
        $out .= "- **Total Operating Expenses**: **₦" . number_format($p['total_expense'], 2) . "** across {$p['expense_count']} logged entries\n";
        $out .= "- **Daily Average Spend**: **₦" . number_format($p['daily_average'], 2) . "/day**\n";
        if ($p['category_filter'] !== 'All Categories') {
            $out .= "- **Category Filter**: `{$p['category_filter']}`\n";
        }
        $out .= "\n";

        if (!empty($p['comparative'])) {
            $comp = $p['comparative'];
            $diffSign = $comp['difference_amount'] >= 0 ? '+' : '';
            $out .= "#### 📊 Period Comparison ({$comp['previous_period']})\n\n";
            $out .= "- **Previous Period Total**: ₦" . number_format($comp['previous_total'], 2) . "\n";
            $out .= "- **Variance / Change**: **{$diffSign}₦" . number_format($comp['difference_amount'], 2) . "** ({$diffSign}{$comp['difference_pct']}% - **{$comp['trend']}**)\n\n";
        }

        if (!empty($p['category_breakdown'])) {
            $out .= "#### 🏷️ Expense Breakdown by Category\n\n";
            $out .= "| Expense Category | Entries | Total Spent (₦) | Share of Budget |\n";
            $out .= "| :--- | :--- | :--- | :--- |\n";
            foreach ($p['category_breakdown'] as $c) {
                $out .= "| **{$c['category_name']}** | {$c['count']} | ₦" . number_format($c['total_amount'], 2) . " | **{$c['percentage']}%** |\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    private static function formatInventoryValuationSummary(array $p): string
    {
        $out = "### 💎 Inventory Valuation & Stock Asset Report\n\n";
        $out .= "**Scope**: {$p['scope']}\n\n";

        if ($p['type'] === 'specific_item') {
            $out .= "## **{$p['item_name']}**\n";
            $out .= "- **Barcode / SKU**: `{$p['barcode']}` | **Category**: {$p['category']}\n";
            $out .= "- **Total Stock on Hand**: **{$p['total_stock_qty']} {$p['unit_label']}** (Shelf: {$p['front_store_qty']}, Warehouse: {$p['back_store_qty']})\n";
            $out .= "- **Cost Price (Buy Cost)**: ₦" . number_format($p['buy_price'], 2) . " per unit\n";
            $out .= "- **Retail Selling Price**: ₦" . number_format($p['selling_price'], 2) . " per unit\n\n";

            $out .= "#### 💰 Valuation Metrics\n\n";
            $out .= "| Asset Metric | Value (₦) |\n";
            $out .= "| :--- | :--- |\n";
            $out .= "| **Total Stock Worth (at Cost)** | **₦" . number_format($p['cost_valuation'], 2) . "** |\n";
            $out .= "| **Total Potential Retail Value** | **₦" . number_format($p['retail_valuation'], 2) . "** |\n";
            $out .= "| **Estimated Potential Gross Profit** | **₦" . number_format($p['potential_profit'], 2) . "** |\n";
            $out .= "| **Projected Gross Margin** | **{$p['gross_margin_pct']}%** |\n\n";

            if (!empty($p['cross_branch_stock'])) {
                $out .= "#### 🏬 Stock Across Other Branches\n\n";
                $out .= "| Branch Name | Shelf Stock | Warehouse Stock | Total Stock |\n";
                $out .= "| :--- | :--- | :--- | :--- |\n";
                foreach ($p['cross_branch_stock'] as $b) {
                    $out .= "| {$b['branch_name']} | {$b['front_stock']} pcs | {$b['back_stock']} pcs | **{$b['total_stock']} pcs** |\n";
                }
            }
            return $out;
        }

        if ($p['type'] === 'cross_branch_ranking') {
            $out .= "#### 🏬 Inventory Valuation by Store Branch\n\n";
            $out .= "- **Leading Branch**: **{$p['top_branch']}** (Carrying **₦" . number_format($p['top_branch_value'], 2) . "** in stock worth)\n";
            $out .= "- **Total System Inventory Worth**: **₦" . number_format($p['total_system_cost_val'], 2) . "**\n\n";

            $out .= "| Rank | Branch / Store | Products | Units in Stock | Cost Valuation (₦) | Retail Value (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
            foreach ($p['branch_rankings'] as $b) {
                $out .= "| #{$b['rank']} | **{$b['branch_name']}** | {$b['total_products']} | {$b['total_units']} pcs | **₦" . number_format($b['cost_valuation'], 2) . "** | ₦" . number_format($b['retail_valuation'], 2) . " |\n";
            }
            return $out;
        }

        // General valuation
        $out .= "Your current inventory in stock is worth **₦" . number_format($p['total_cost_valuation'], 2) . "** at purchase cost price, with an estimated retail selling value of **₦" . number_format($p['total_retail_valuation'], 2) . "** across {$p['total_catalog_products']} products ({$p['total_units_in_stock']} total units on hand).\n\n";

        $out .= "| Valuation Metric | Amount (₦) |\n";
        $out .= "| :--- | :--- |\n";
        $out .= "| **Total Stock Worth (at Cost)** | **₦" . number_format($p['total_cost_valuation'], 2) . "** |\n";
        $out .= "| **Total Potential Retail Value** | **₦" . number_format($p['total_retail_valuation'], 2) . "** |\n";
        $out .= "| **Potential Gross Profit** | **₦" . number_format($p['potential_gross_profit'], 2) . "** |\n";
        $out .= "| **Projected Gross Margin** | **{$p['gross_margin_pct']}%** |\n\n";

        $out .= "#### 📦 Stock Physical Volume\n\n";
        $out .= "- **Total Catalog Products**: **{$p['total_catalog_products']} unique items**\n";
        $out .= "- **Total Units in Stock**: **{$p['total_units_in_stock']} total physical pieces** (Front Shelf: {$p['front_store_units']} pcs, Back Warehouse: {$p['back_store_units']} pcs)\n\n";

        if (!empty($p['top_value_items'])) {
            $out .= "#### 🏆 Top 5 Highest-Valued Inventory Assets\n\n";
            $out .= "| Item Name | Total Units | Cost Price (₦) | Total Cost Worth (₦) | Retail Value (₦) |\n";
            $out .= "| :--- | :--- | :--- | :--- |\n";
            foreach ($p['top_value_items'] as $item) {
                $out .= "| **{$item['item_name']}** | {$item['total_qty']} pcs | ₦" . number_format($item['cost_price'], 2) . " | **₦" . number_format($item['stock_worth'], 2) . "** | ₦" . number_format($item['retail_worth'], 2) . " |\n";
            }
        }

        return $out;
    }

    private static function formatCustomerDebtSummary(array $p): string
    {
        $out = "### 💳 Customer Debt Ledger & Credit Analysis\n\n";
        $out .= "**Scope**: {$p['scope']}\n\n";

        if ($p['type'] === 'specific_customer') {
            $watchBadge = $p['watch_list'] ? ' ⚠️ *(Watchlist)*' : '';
            $out .= "## **{$p['customer_name']}**{$watchBadge}\n";
            $out .= "- **Phone Number**: {$p['phone']}\n";
            $out .= "- **Current Outstanding Balance**: **₦" . number_format($p['current_balance'], 2) . "**\n";
            $out .= "- **Lifetime Debited (Purchases/Charges)**: ₦" . number_format($p['total_debited'], 2) . "\n";
            $out .= "- **Lifetime Credited (Payments)**: ₦" . number_format($p['total_credited'], 2) . "\n";
            $out .= "- **Last Payment Received**: " . ($p['last_payment_amount'] > 0 ? "₦" . number_format($p['last_payment_amount'], 2) . " on " . $p['last_payment_date'] : $p['last_payment_date']) . "\n\n";

            if (!empty($p['recent_ledger_history'])) {
                $out .= "#### 📑 Recent Debt Ledger Entries\n\n";
                $out .= "| Date | Type | Amount (₦) | Balance After (₦) | Staff | Narration |\n";
                $out .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";
                foreach ($p['recent_ledger_history'] as $e) {
                    $typeBadge = $e['type'] === 'credit' ? '✅ Payment (Credit)' : '⚠️ Charge (Debit)';
                    $out .= "| {$e['date']} | {$typeBadge} | ₦" . number_format($e['amount'], 2) . " | ₦" . number_format($e['balance_after'], 2) . " | {$e['staff']} | {$e['narration']} |\n";
                }
            }
            return $out;
        }

        if ($p['type'] === 'overdue_30_days') {
            $out .= "#### ⏰ Aging Debt Analysis (>30 Days Without Payment)\n\n";
            $out .= "- **Total Overdue Debt (>30 Days)**: **₦" . number_format($p['total_overdue_amount'], 2) . "**\n";
            $out .= "- **Overdue Customer Accounts**: **{$p['overdue_debtors_count']} customers** (out of {$p['total_debtors']} total debtors)\n\n";

            if (!empty($p['overdue_debtors'])) {
                $out .= "| Customer Name | Phone | Balance Owing (₦) | Last Payment | Days Overdue |\n";
                $out .= "| :--- | :--- | :--- | :--- | :--- |\n";
                foreach ($p['overdue_debtors'] as $d) {
                    $out .= "| **{$d['customer_name']}** | {$d['phone']} | **₦" . number_format($d['debt_bal'], 2) . "** | {$d['last_payment_date']} | {$d['days_since_payment']} days ago |\n";
                }
            } else {
                $out .= "No customer debts are overdue past 30 days! All debtor accounts have active recent payment activity.\n";
            }
            return $out;
        }

        // General debt overview
        $out .= "- **Total Outstanding Debt**: **₦" . number_format($p['total_outstanding'], 2) . "**\n";
        $out .= "- **Active Debtors**: **{$p['debtors_count']} customers**\n";
        $out .= "- **Debt Recovered This Month**: **₦" . number_format($p['debt_recovered_month'], 2) . "**\n\n";

        if (!empty($p['top_debtors'])) {
            $out .= "#### 👥 Highest Outstanding Debt Balances\n\n";
            $out .= "| Customer Name | Phone Number | Balance Owing (₦) | Last Payment Date |\n";
            $out .= "| :--- | :--- | :--- | :--- |\n";
            foreach ($p['top_debtors'] as $d) {
                $out .= "| **{$d['customer_name']}** | {$d['phone']} | **₦" . number_format($d['balance_owing'], 2) . "** | {$d['last_payment_date']} |\n";
            }
        }

        return $out;
    }
}

