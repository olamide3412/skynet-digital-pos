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
            ->withCount(['items', 'users'])
            ->get();

        $branchIds = $branches->pluck('id')->all();

        // ── 1. Financial & Operational KPIs (Organization Scope) ─────────────
        $todayRevenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today())
            ->sum('final_total');

        $todaySalesCount = Sale::whereIn('branch_id', $branchIds)
            ->whereDate('created_at', today())
            ->count();

        $weekRevenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('final_total');

        $weekSalesCount = Sale::whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $monthRevenue = (float) Sale::whereIn('branch_id', $branchIds)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('final_total');

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
            $todayBranchRevenue = (float) Sale::where('branch_id', $b->id)
                ->whereDate('created_at', today())
                ->sum('final_total');

            $todayBranchOrders = Sale::where('branch_id', $b->id)
                ->whereDate('created_at', today())
                ->count();

            $weekBranchRevenue = (float) Sale::where('branch_id', $b->id)
                ->where('created_at', '>=', now()->startOfWeek())
                ->sum('final_total');

            return [
                'id'            => $b->id,
                'name'          => $b->name,
                'slug'          => $b->slug,
                'address'       => $b->address,
                'phone'         => $b->phone,
                'email'         => $b->email,
                'is_active'     => (bool) $b->is_active,
                'is_ai_enabled' => (bool) $b->is_ai_enabled,
                'logo_url'      => $b->logo_path ? asset('storage/' . $b->logo_path) : null,
                'items_count'   => $b->items_count,
                'users_count'   => $b->users_count,
                'today_revenue' => $todayBranchRevenue,
                'today_orders'  => $todayBranchOrders,
                'week_revenue'  => $weekBranchRevenue,
                'pos_url'       => url("/{$b->slug}"),
            ];
        });

        // ── 3. Recent Transactions Across Organization Branches ──────────────
        $recentSales = Sale::whereIn('branch_id', $branchIds)
            ->with(['branch:id,name,slug', 'user:id,name', 'customer:id,name'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn(Sale $s) => [
                'id'             => $s->id,
                'receipt_number' => $s->receipt_id ?? "REC-{$s->id}",
                'branch_name'    => $s->branch?->name ?? 'Unknown',
                'cashier_name'   => $s->user?->name ?? 'Staff',
                'customer_name'  => $s->customer?->name ?? 'Walk-in Customer',
                'final_total'    => (float) $s->final_total,
                'payment_method' => $s->payment_method,
                'created_at'     => $s->created_at?->format('d M Y, h:i A'),
            ]);

        // For Super Admin view: list of all active organizations for quick switching
        $allOrganizations = $user->isSuperAdmin()
            ? Organization::where('status', 'active')->orderBy('name')->get(['id', 'name'])
            : [];

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
                'total_branches'    => $branches->count(),
                'active_branches'   => $branches->where('is_active', true)->count(),
                'today_revenue'     => $todayRevenue,
                'today_orders'      => $todaySalesCount,
                'week_revenue'      => $weekRevenue,
                'week_orders'       => $weekSalesCount,
                'month_revenue'     => $monthRevenue,
                'total_items'       => $totalItemsCount,
                'low_stock_count'   => $lowStockCount,
                'expiring_count'    => $expiringCount,
                'conflicts_count'   => $pendingConflictsCount,
            ],
            'branches'           => $branchCards,
            'recent_sales'       => $recentSales,
            'all_organizations'  => $allOrganizations,
            'is_super_admin'     => $user->isSuperAdmin(),
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
            ->withCount(['items', 'users'])
            ->get()
            ->map(function (Branch $b) {
                $monthRevenue = (float) Sale::where('branch_id', $b->id)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('final_total');

                $todayRevenue = (float) Sale::where('branch_id', $b->id)
                    ->whereDate('created_at', today())
                    ->sum('final_total');

                return [
                    'id'            => $b->id,
                    'name'          => $b->name,
                    'slug'          => $b->slug,
                    'address'       => $b->address,
                    'phone'         => $b->phone,
                    'email'         => $b->email,
                    'is_active'     => (bool) $b->is_active,
                    'logo_url'      => $b->logo_path ? asset('storage/' . $b->logo_path) : null,
                    'items_count'   => $b->items_count,
                    'users_count'   => $b->users_count,
                    'today_revenue' => $todayRevenue,
                    'month_revenue' => $monthRevenue,
                    'pos_url'       => url("/{$b->slug}"),
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
