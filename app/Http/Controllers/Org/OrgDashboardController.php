<?php

namespace App\Http\Controllers\Org;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\Sale;
use App\Models\StockConflict;
use App\Models\User;
use App\Modules\Laundry\Models\LaundryOrder;
use App\Modules\Laundry\Models\LaundryPayment;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrgDashboardController extends Controller
{
    /**
     * Resolve the active organization for the current user session.
     */
    protected function resolveOrganization(Request $request): ?Organization
    {
        $user = Auth::guard('web')->user();

        if ($user->isSuperAdmin()) {
            if ($request->has('org_id')) {
                return Organization::find($request->get('org_id')) ?: Organization::first();
            }
            return $user->organization_id ? Organization::find($user->organization_id) : Organization::first();
        }

        return $user->organization_id ? Organization::find($user->organization_id) : null;
    }

    /**
     * Display the Organization Executive Overview Dashboard.
     */
    public function index(Request $request): Response
    {
        $user = Auth::guard('web')->user();
        $org = $this->resolveOrganization($request);

        if (!$org) {
            abort(404, 'No organization found.');
        }

        $branches = Branch::where('organization_id', $org->id)
            ->with(['settings'])
            ->withCount(['items', 'users'])
            ->get();

        $branchIds = $branches->pluck('id')->all();

        // Detect module capabilities across all org branches
        $hasPosBranches     = $branches->contains(fn(Branch $b) => $b->posEnabled());
        $hasLaundryBranches = $branches->contains(fn(Branch $b) => $b->laundryEnabled());

        // ── 1. Financial & Operational KPIs (Unified Scope: Retail POS + Laundry) ──
        $todayPosRevenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today())
            ->sum('final_total');

        $todayPosCount = Sale::whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today())
            ->count();

        $weekPosRevenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('final_total');

        $weekPosCount = Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $monthPosRevenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('final_total');

        // Laundry Organization Financials & Operations
        $todayLaundryRevenue  = 0.0;
        $todayLaundryOrders   = 0;
        $weekLaundryRevenue   = 0.0;
        $weekLaundryOrders    = 0;
        $monthLaundryRevenue  = 0.0;
        $activeLaundryOrders  = 0;
        $readyLaundryOrders   = 0;
        $overdueLaundryOrders = 0;
        $unpaidLaundryDebt    = 0.0;

        if ($hasLaundryBranches) {
            $todayGrossLdy = (float) LaundryPayment::whereIn('branch_id', $branchIds)
                ->whereDate('paid_at', today())
                ->where('type', 'payment')
                ->sum('amount');
            $todayRefLdy = (float) LaundryPayment::whereIn('branch_id', $branchIds)
                ->whereDate('paid_at', today())
                ->where('type', 'refund')
                ->sum('amount');
            $todayLaundryRevenue = max(0.0, round($todayGrossLdy - $todayRefLdy, 2));

            $todayLaundryOrders = LaundryOrder::whereIn('branch_id', $branchIds)
                ->whereDate('received_at', today())
                ->count();

            $weekGrossLdy = (float) LaundryPayment::whereIn('branch_id', $branchIds)
                ->where('paid_at', '>=', now()->startOfWeek())
                ->where('type', 'payment')
                ->sum('amount');
            $weekRefLdy = (float) LaundryPayment::whereIn('branch_id', $branchIds)
                ->where('paid_at', '>=', now()->startOfWeek())
                ->where('type', 'refund')
                ->sum('amount');
            $weekLaundryRevenue = max(0.0, round($weekGrossLdy - $weekRefLdy, 2));

            $weekLaundryOrders = LaundryOrder::whereIn('branch_id', $branchIds)
                ->where('received_at', '>=', now()->startOfWeek())
                ->count();

            $monthGrossLdy = (float) LaundryPayment::whereIn('branch_id', $branchIds)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->where('type', 'payment')
                ->sum('amount');
            $monthRefLdy = (float) LaundryPayment::whereIn('branch_id', $branchIds)
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->where('type', 'refund')
                ->sum('amount');
            $monthLaundryRevenue = max(0.0, round($monthGrossLdy - $monthRefLdy, 2));

            $activeLaundryOrders = LaundryOrder::whereIn('branch_id', $branchIds)
                ->whereIn('status', ['received', 'in_progress'])
                ->count();

            $readyLaundryOrders = LaundryOrder::whereIn('branch_id', $branchIds)
                ->where('status', 'ready')
                ->count();

            $overdueLaundryOrders = LaundryOrder::whereIn('branch_id', $branchIds)
                ->whereIn('status', ['received', 'in_progress'])
                ->where('due_at', '<', now())
                ->count();

            $unpaidLaundryDebt = (float) LaundryOrder::whereIn('branch_id', $branchIds)
                ->where('status', '!=', 'cancelled')
                ->where('balance', '>', 0)
                ->sum('balance');
        }

        // Consolidated Financials
        $todayRevenue = round($todayPosRevenue + $todayLaundryRevenue, 2);
        $todayOrders  = $todayPosCount + $todayLaundryOrders;
        $weekRevenue  = round($weekPosRevenue + $weekLaundryRevenue, 2);
        $weekOrders   = $weekPosCount + $weekLaundryOrders;
        $monthRevenue = round($monthPosRevenue + $monthLaundryRevenue, 2);

        $totalItemsCount = Item::whereIn('branch_id', $branchIds)->count();

        $lowStockCount = Item::whereIn('branch_id', $branchIds)
            ->whereRaw('(front_store_qty + back_store_qty) <= reorder_point')
            ->count();

        $expiringCount = Item::whereIn('branch_id', $branchIds)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(30))
            ->count();

        $pendingConflictsCount = StockConflict::whereIn('branch_id', $branchIds)
            ->where('status', 'pending')
            ->count();

        // ── 2. Per-Branch Performance Cards ──────────────────────────────────
        $branchCards = $branches->map(function (Branch $b) {
            $isPos = $b->posEnabled();
            $isLaundry = $b->laundryEnabled();
            $operatingMode = ($isPos && $isLaundry) ? 'both' : ($isLaundry ? 'laundry_only' : 'pos_only');

            // POS metrics
            $todayPos = (float) Sale::where('branch_id', $b->id)->whereDate('created_at', today())->sum('final_total');
            $todayPosCount = Sale::where('branch_id', $b->id)->whereDate('created_at', today())->count();
            $weekPos = (float) Sale::where('branch_id', $b->id)->where('created_at', '>=', now()->startOfWeek())->sum('final_total');
            $monthPos = (float) Sale::where('branch_id', $b->id)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('final_total');

            // Laundry metrics
            $todayLdy = 0.0;
            $todayLdyCount = 0;
            $weekLdy = 0.0;
            $monthLdy = 0.0;
            $activeLdy = 0;

            if ($isLaundry) {
                $todayLdyGross = (float) LaundryPayment::where('branch_id', $b->id)->whereDate('paid_at', today())->where('type', 'payment')->sum('amount');
                $todayLdyRef   = (float) LaundryPayment::where('branch_id', $b->id)->whereDate('paid_at', today())->where('type', 'refund')->sum('amount');
                $todayLdy      = max(0.0, round($todayLdyGross - $todayLdyRef, 2));

                $todayLdyCount = LaundryOrder::where('branch_id', $b->id)->whereDate('received_at', today())->count();

                $weekLdyGross  = (float) LaundryPayment::where('branch_id', $b->id)->where('paid_at', '>=', now()->startOfWeek())->where('type', 'payment')->sum('amount');
                $weekLdyRef    = (float) LaundryPayment::where('branch_id', $b->id)->where('paid_at', '>=', now()->startOfWeek())->where('type', 'refund')->sum('amount');
                $weekLdy       = max(0.0, round($weekLdyGross - $weekLdyRef, 2));

                $monthLdyGross = (float) LaundryPayment::where('branch_id', $b->id)->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->where('type', 'payment')->sum('amount');
                $monthLdyRef   = (float) LaundryPayment::where('branch_id', $b->id)->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->where('type', 'refund')->sum('amount');
                $monthLdy      = max(0.0, round($monthLdyGross - $monthLdyRef, 2));

                $activeLdy     = LaundryOrder::where('branch_id', $b->id)->whereIn('status', ['received', 'in_progress', 'ready'])->count();
            }

            $todayTotalRevenue = round($todayPos + $todayLdy, 2);
            $todayTotalOrders  = $todayPosCount + $todayLdyCount;
            $weekTotalRevenue  = round($weekPos + $weekLdy, 2);
            $monthTotalRevenue = round($monthPos + $monthLdy, 2);

            $entryUrl = ($operatingMode === 'laundry_only')
                ? url("/{$b->slug}/laundry")
                : url("/{$b->slug}");

            return [
                'id'                   => $b->id,
                'name'                 => $b->name,
                'slug'                 => $b->slug,
                'address'              => $b->address,
                'phone'                => $b->phone,
                'email'                => $b->email,
                'is_active'            => (bool) $b->is_active,
                'is_ai_enabled'        => (bool) $b->is_ai_enabled,
                'logo_url'             => $b->logo_path ? asset('storage/' . $b->logo_path) : null,
                'items_count'          => $b->items_count,
                'users_count'          => $b->users_count,
                'operating_mode'       => $operatingMode,
                'pos_enabled'          => $isPos,
                'laundry_enabled'      => $isLaundry,
                'today_revenue'        => $todayTotalRevenue,
                'today_orders'         => $todayTotalOrders,
                'today_pos_revenue'    => $todayPos,
                'today_laundry_revenue'=> $todayLdy,
                'week_revenue'         => $weekTotalRevenue,
                'month_revenue'        => $monthTotalRevenue,
                'active_laundry_count' => $activeLdy,
                'pos_url'              => $entryUrl,
            ];
        });

        // ── 3. Recent Transactions Across Organization (Sales & Laundry) ────
        $recentSales = Sale::whereIn('branch_id', $branchIds)
            ->with(['branch:id,name,slug', 'user:id,name', 'customer:id,name'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn(Sale $s) => [
                'id'             => 'sale_' . $s->id,
                'type'           => 'pos',
                'receipt_number' => $s->receipt_id ?? "REC-{$s->id}",
                'branch_name'    => $s->branch?->name ?? 'Unknown',
                'cashier_name'   => $s->user?->name ?? 'Staff',
                'customer_name'  => $s->customer?->name ?? 'Walk-in Customer',
                'final_total'    => (float) $s->final_total,
                'payment_method' => $s->payment_method ?: 'Cash',
                'status'         => $s->is_debt ? 'Credit Sale' : 'Completed',
                'created_at'     => $s->created_at?->format('d M Y, h:i A'),
                'timestamp'      => $s->created_at?->timestamp ?? 0,
            ]);

        $recentLaundry = collect();
        if ($hasLaundryBranches) {
            $recentLaundry = LaundryOrder::whereIn('branch_id', $branchIds)
                ->with(['branch:id,name,slug'])
                ->latest('received_at')
                ->take(10)
                ->get()
                ->map(fn(LaundryOrder $o) => [
                    'id'             => 'laundry_' . $o->id,
                    'type'           => 'laundry',
                    'receipt_number' => $o->order_number,
                    'branch_name'    => $o->branch?->name ?? 'Unknown',
                    'cashier_name'   => 'Laundry Staff',
                    'customer_name'  => $o->customer_name ?: 'Customer',
                    'final_total'    => (float) $o->total,
                    'payment_method' => ((float) $o->balance <= 0) ? 'Fully Paid' : 'Partial / Balance Due',
                    'status'         => ucfirst(str_replace('_', ' ', $o->status)),
                    'created_at'     => $o->received_at?->format('d M Y, h:i A'),
                    'timestamp'      => $o->received_at?->timestamp ?? 0,
                ]);
        }

        $recentTransactions = $recentSales->concat($recentLaundry)
            ->sortByDesc('timestamp')
            ->take(10)
            ->values()
            ->all();

        // For Super Admin view: list of all active organizations for quick switching
        $allOrganizations = $user->isSuperAdmin()
            ? Organization::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : [];

        $inTransitTransfersCount = \App\Models\InterBranchTransfer::where('organization_id', $org->id)
            ->where('status', 'pending')
            ->count();

        $productGroupsCount = \App\Models\ProductGroup::where('organization_id', $org->id)->count();

        return Inertia::render('Org/Dashboard', [
            'organization' => [
                'id'            => $org->id,
                'name'          => $org->name,
                'contact_name'  => $org->contact_name,
                'contact_email' => $org->contact_email,
                'contact_phone' => $org->contact_phone,
                'status'        => $org->status,
                'address'       => $org->address,
            ],
            'kpis' => [
                'total_branches'         => $branches->count(),
                'active_branches'        => $branches->where('is_active', true)->count(),
                'has_pos_branches'       => $hasPosBranches,
                'has_laundry_branches'   => $hasLaundryBranches,
                'today_revenue'          => $todayRevenue,
                'today_orders'           => $todayOrders,
                'today_pos_revenue'      => $todayPosRevenue,
                'today_laundry_revenue'  => $todayLaundryRevenue,
                'week_revenue'           => $weekRevenue,
                'week_orders'            => $weekOrders,
                'month_revenue'          => $monthRevenue,
                // Retail POS specific
                'total_items'            => $totalItemsCount,
                'low_stock_count'        => $lowStockCount,
                'expiring_count'         => $expiringCount,
                'conflicts_count'        => $pendingConflictsCount,
                'in_transit_transfers'   => $inTransitTransfersCount,
                'product_groups_count'   => $productGroupsCount,
                // Laundry specific
                'active_laundry_orders'  => $activeLaundryOrders,
                'ready_laundry_orders'   => $readyLaundryOrders,
                'overdue_laundry_orders' => $overdueLaundryOrders,
                'unpaid_laundry_debt'    => $unpaidLaundryDebt,
            ],
            'branches'            => $branchCards,
            'recent_sales'        => $recentTransactions,
            'all_organizations'   => $allOrganizations,
            'is_super_admin'      => $user->isSuperAdmin(),
        ]);
    }

    /**
     * Display all branches of the Organization.
     */
    public function branches(Request $request): Response
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        $branches = Branch::where('organization_id', $org->id)
            ->with(['settings'])
            ->withCount(['items', 'users'])
            ->get()
            ->map(function (Branch $b) {
                $isPos = $b->posEnabled();
                $isLaundry = $b->laundryEnabled();
                $operatingMode = ($isPos && $isLaundry) ? 'both' : ($isLaundry ? 'laundry_only' : 'pos_only');

                $monthPos = (float) Sale::where('branch_id', $b->id)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('final_total');

                $todayPos = (float) Sale::where('branch_id', $b->id)
                    ->whereDate('created_at', today())
                    ->sum('final_total');

                $monthLdy = 0.0;
                $todayLdy = 0.0;
                $activeLdy = 0;
                if ($isLaundry) {
                    $mGross = (float) LaundryPayment::where('branch_id', $b->id)->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->where('type', 'payment')->sum('amount');
                    $mRef   = (float) LaundryPayment::where('branch_id', $b->id)->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->where('type', 'refund')->sum('amount');
                    $monthLdy = max(0.0, round($mGross - $mRef, 2));

                    $tGross = (float) LaundryPayment::where('branch_id', $b->id)->whereDate('paid_at', today())->where('type', 'payment')->sum('amount');
                    $tRef   = (float) LaundryPayment::where('branch_id', $b->id)->whereDate('paid_at', today())->where('type', 'refund')->sum('amount');
                    $todayLdy = max(0.0, round($tGross - $tRef, 2));

                    $activeLdy = LaundryOrder::where('branch_id', $b->id)->whereIn('status', ['received', 'in_progress', 'ready'])->count();
                }

                $entryUrl = ($operatingMode === 'laundry_only')
                    ? url("/{$b->slug}/laundry")
                    : url("/{$b->slug}");

                return [
                    'id'                   => $b->id,
                    'name'                 => $b->name,
                    'slug'                 => $b->slug,
                    'address'              => $b->address,
                    'phone'                => $b->phone,
                    'email'                => $b->email,
                    'is_active'            => (bool) $b->is_active,
                    'logo_url'             => $b->logo_path ? asset('storage/' . $b->logo_path) : null,
                    'items_count'          => $b->items_count,
                    'users_count'          => $b->users_count,
                    'operating_mode'       => $operatingMode,
                    'pos_enabled'          => $isPos,
                    'laundry_enabled'      => $isLaundry,
                    'today_revenue'        => round($todayPos + $todayLdy, 2),
                    'month_revenue'        => round($monthPos + $monthLdy, 2),
                    'active_laundry_count' => $activeLdy,
                    'pos_url'              => $entryUrl,
                ];
            });

        return Inertia::render('Org/Branches/Index', [
            'organization' => [
                'id'                  => $org->id,
                'name'                => $org->name,
                'can_create_branches' => $org->isBranchCreationAllowed(),
            ],
            'branches' => $branches,
            'plan_info' => [
                'can_create_branch'       => $org->canCreateBranch(),
                'branch_creation_allowed' => $org->isBranchCreationAllowed(),
                'branch_limit'            => $org->getBranchLimit(),
                'current_branches_count'  => $branches->count(),
                'remaining_slots'         => $org->getRemainingBranchSlots(),
                'is_limit_reached'        => $org->isBranchLimitReached(),
                'plan_name'               => $org->subscription?->pricingPlan?->name ?? 'Unrestricted',
            ],
        ]);
    }

    /**
     * Store a newly created branch for the active organization.
     */
    public function storeBranch(Request $request)
    {
        $org = $this->resolveOrganization($request);
        if (!$org) {
            abort(404, 'No organization found.');
        }

        if (!$org->isActive()) {
            return back()->withErrors([
                'name' => 'This organization account is currently suspended. Please contact platform support.',
            ]);
        }

        if (!$org->isBranchCreationAllowed()) {
            return back()->withErrors([
                'name' => 'Branch creation has been disabled for your organization by platform administration.',
            ]);
        }

        if ($org->isBranchLimitReached()) {
            $limit = $org->getBranchLimit();
            return back()->withErrors([
                'name' => "Your organization's subscription plan allows up to {$limit} branch(es). Upgrade your plan to create more branches.",
            ]);
        }

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'slug'           => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:branches,slug',
                Rule::notIn(config('organizations.reserved_slugs', [])),
            ],
            'address'        => 'nullable|string|max:500',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255',
            // Initial Branch Administrator
            'admin_name'     => 'required|string|max:255',
            'admin_username' => 'required|string|max:50|unique:users,username',
            'admin_email'    => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ], [
            'slug.regex'  => 'Branch slug must contain only lowercase letters, numbers, and hyphens (e.g. lekki-store).',
            'slug.not_in' => "The slug ':input' is reserved by the system and cannot be used for a branch URL.",
        ]);

        $branch = DB::transaction(function () use ($org, $data) {
            $branch = Branch::create([
                'organization_id' => $org->id,
                'name'            => $data['name'],
                'slug'            => $data['slug'],
                'address'         => $data['address'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'email'           => $data['email'] ?? null,
                'is_active'       => true,
                'is_ai_enabled'   => $org->hasFeature('ai_chat_assistant'),
            ]);

            $admin = User::create([
                'name'            => $data['admin_name'],
                'full_name'       => $data['admin_name'],
                'username'        => $data['admin_username'],
                'email'           => $data['admin_email'],
                'password'        => Hash::make($data['admin_password']),
                'is_active'       => true,
                'is_super_admin'  => false,
                'branch_id'       => $branch->id,
                'organization_id' => $org->id,
            ]);

            setPermissionsTeamId($branch->id);
            $branch->ensureDefaultRoles();
            $admin->assignRole('branch-admin');

            $branch->getSettings();

            ActivityLogger::log(
                'branch',
                "Organization Admin created new branch '{$branch->name}' with admin account @{$admin->username}",
                $branch->id
            );

            OrganizationActivityLog::create([
                'organization_id' => $org->id,
                'actor_user_id'   => Auth::id(),
                'action'          => 'branch_created',
                'details'         => [
                    'branch_id'   => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_slug' => $branch->slug,
                    'admin_user'  => $admin->username,
                    'created_via' => 'org_portal',
                ],
                'created_at'      => now(),
            ]);

            if ($org->hasFeature('accounting')) {
                \App\Modules\Accounting\Services\AccountSetupService::activateBranch($branch);
            }

            return $branch;
        });

        return redirect()->route('org.branches')->with('success', "Branch '{$branch->name}' created successfully.");
    }
}
