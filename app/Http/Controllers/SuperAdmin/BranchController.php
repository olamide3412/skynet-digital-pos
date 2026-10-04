<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\GlobalItem;
use App\Models\Item;
use App\Models\PosLog;
use App\Models\Sale;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ItemImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->input('search', ''));
        $organizationId = $request->input('organization_id');
        $status = $request->input('status');

        $query = Branch::with(['settings', 'organization:id,name'])
            ->withCount(['users', 'items', 'sales'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('address', 'like', "%{$search}%")
                      ->orWhereHas('organization', function ($orgQuery) use ($search) {
                          $orgQuery->where('name', 'like', "%{$search}%");
                      });
                });
            })
            ->when($organizationId, function ($query, $orgId) {
                if ($orgId === 'unassigned') {
                    $query->whereNull('organization_id');
                } else {
                    $query->where('organization_id', $orgId);
                }
            })
            ->when($status !== null && $status !== '', function ($query) use ($status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'disabled') {
                    $query->where('is_active', false);
                }
            });

        $branches = $query->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Branch $b) => [
                'id'                 => $b->id,
                'organization_id'    => $b->organization_id,
                'organization'       => $b->organization ? ['id' => $b->organization->id, 'name' => $b->organization->name] : null,
                'name'               => $b->name,
                'slug'               => $b->slug,
                'address'            => $b->address,
                'phone'              => $b->phone,
                'email'              => $b->email,
                'is_active'          => (bool) $b->is_active,
                'is_ai_enabled'      => (bool) ($b->is_ai_enabled ?? true),
                'logo_url'           => $b->logo_path ? asset('storage/' . $b->logo_path) : null,
                'users_count'        => $b->users_count,
                'items_count'        => $b->items_count,
                'sales_count'        => $b->sales_count,
                'settings'           => $b->settings,
                'created_at'         => $b->created_at?->format('d M Y'),
            ]);

        $organizations = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Branches/Index', [
            'branches'      => $branches,
            'organizations' => $organizations,
            'filters'       => [
                'search'          => $search,
                'organization_id' => $organizationId,
                'status'          => $status,
            ],
        ]);
    }

    public function create()
    {
        $organizations = Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Branches/Create', [
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'organization_id'      => 'required|integer|exists:organizations,id',
            'name'                 => 'required|string|max:255',
            'slug'                 => [
                'required',
                'string',
                'max:100',
                'unique:branches,slug',
                'regex:/^[a-z0-9\-]+$/',
                Rule::notIn(config('organizations.reserved_slugs', [])),
            ],
            'address'              => 'nullable|string|max:500',
            'phone'                => 'nullable|string|max:50',
            'email'                => 'nullable|email|max:255',
            'is_offline_enabled'   => 'nullable|boolean',
            'is_offline_qty_deduction' => 'nullable|boolean',
            'held_sales_visibility'=> 'nullable|string|in:device_only,shared_across_branch',
            'is_turnstile_enabled' => 'nullable|boolean',
            // Branch Admin account details
            'admin_name'           => 'required|string|max:255',
            'admin_username'       => 'required|string|max:50|unique:users,username',
            'admin_email'          => 'required|email|max:255|unique:users,email',
            'admin_password'       => 'required|string|min:8',
        ], [
            'organization_id.required' => 'Please choose an Organization for this branch. Every branch must belong to an organization.',
            'slug.not_in'              => "The slug ':input' is reserved by the system and cannot be used for a branch URL.",
        ]);

        $org = Organization::findOrFail($data['organization_id']);
        if ($org->isBranchLimitReached()) {
            return back()->withErrors([
                'organization_id' => "This organization's plan allows up to {$org->getBranchLimit()} branch(es). Upgrade the plan to add more branches.",
            ]);
        }

        $createdBranch = DB::transaction(function () use ($data) {
            // Create branch bound to selected organization
            $branch = Branch::create([
                'organization_id' => $data['organization_id'],
                'name'            => $data['name'],
                'slug'            => $data['slug'],
                'address'         => $data['address'] ?? null,
                'phone'           => $data['phone'] ?? null,
                'email'           => $data['email'] ?? null,
                'is_active'       => true,
            ]);

            // Auto-create default Branch Admin user
            $admin = User::create([
                'name'            => $data['admin_name'],
                'full_name'       => $data['admin_name'],
                'username'        => $data['admin_username'],
                'email'           => $data['admin_email'],
                'password'        => Hash::make($data['admin_password']),
                'is_active'       => true,
                'is_super_admin'  => false,
                'branch_id'       => $branch->id,
                'organization_id' => $data['organization_id'],
            ]);

            // Set Spatie team context, ensure default roles, then assign branch-admin role
            setPermissionsTeamId($branch->id);
            $branch->ensureDefaultRoles();
            $admin->assignRole('branch-admin');

            // Create branch POS settings with defaults
            $settings = $branch->getSettings();
            $settingsUpdates = [];
            if (!empty($data['is_offline_enabled'])) {
                $settingsUpdates['is_offline_enabled'] = true;
            }
            if (!empty($data['held_sales_visibility'])) {
                $settingsUpdates['held_sales_visibility'] = $data['held_sales_visibility'];
            }
            if (isset($data['is_offline_qty_deduction'])) {
                $settingsUpdates['is_offline_qty_deduction'] = (bool)$data['is_offline_qty_deduction'];
            }
            if (isset($data['is_turnstile_enabled'])) {
                $settingsUpdates['is_turnstile_enabled'] = (bool)$data['is_turnstile_enabled'];
            }
            if (!empty($settingsUpdates)) {
                $settings->update($settingsUpdates);
            }

            ActivityLogger::log(
                'branch',
                "Created new branch '{$branch->name}' with admin account @{$admin->username}",
                $branch->id
            );

            OrganizationActivityLog::create([
                'organization_id' => $branch->organization_id,
                'actor_user_id'   => Auth::id(),
                'action'          => 'branch_created',
                'details'         => [
                    'branch_id'   => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_slug' => $branch->slug,
                    'admin_user'  => $admin->username,
                ],
                'created_at'      => now(),
            ]);

            return $branch;
        });

        return redirect()->route('superadmin.branches.show', ['branch' => $createdBranch->slug])
            ->with('success', "Branch '{$createdBranch->name}' created successfully with initial admin.");
    }

    /**
     * Dedicated Branch Management Hub (Super Admin Per-Branch Mini-Dashboard)
     */
    public function show(Request $request, Branch $branch)
    {
        // Set team context for Spatie permissions query
        setPermissionsTeamId($branch->id);
        $branch->ensureDefaultRoles();

        $settings = $branch->getSettings();

        // ── 1. Statistics & Financial Metrics ─────────────────────────────────
        $totalSalesCount = $branch->sales()->count();
        $totalRevenue    = (float) $branch->sales()->sum('final_total');
        $monthRevenue    = (float) $branch->sales()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('final_total');
        $todayRevenue    = (float) $branch->sales()
            ->whereDate('created_at', today())
            ->sum('final_total');
        $totalItemsCount = $branch->items()->count();
        $totalUsersCount = $branch->users()->count();

        // ── 2. Staff List & Role Templates ────────────────────────────────────
        $users = $branch->users()
            ->with(['roles', 'permissions'])
            ->latest()
            ->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'username'    => $u->username,
                'email'       => $u->email,
                'is_active'   => (bool) $u->is_active,
                'roles'       => $u->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->values(),
                'permissions' => $u->getAllPermissions()->pluck('name')->values(),
                'created_at'  => $u->created_at?->format('d M Y'),
            ]);

        $roles = Role::where('team_id', $branch->id)->get(['id', 'name']);

        $allPermissions = collect(BranchUserController::ALL_PERMISSIONS)
            ->map(fn ($label, $name) => ['name' => $name, 'label' => $label])
            ->values();

        // ── 3. Branch Inventory & Global Master Import ─────────────────────────
        $search = $request->query('item_search', '');
        $branchItems = Item::where('branch_id', $branch->id)
            ->with('category:id,name')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('item_name', 'like', "%{$search}%")
                       ->orWhere('barcode_number', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15, ['*'], 'items_page')
            ->withQueryString();

        $importedBarcodes = Item::where('branch_id', $branch->id)
            ->whereNotNull('barcode_number')
            ->pluck('barcode_number')
            ->toArray();

        $globalItems = GlobalItem::all()->map(fn (GlobalItem $gi) => [
            'id'             => $gi->id,
            'item_name'      => $gi->item_name,
            'barcode_number' => $gi->barcode_number,
            'buy_price'      => (float) $gi->buy_price,
            'price'          => (float) $gi->price,
            'category_hint'  => $gi->category_hint,
            'is_imported'    => in_array($gi->barcode_number, $importedBarcodes),
        ]);

        $categories = Category::where('branch_id', $branch->id)->orWhereNull('branch_id')->orderBy('name')->get(['id', 'name']);

        // ── 4. Transactions Snapshot (Paginated) ──────────────────────────────
        $salesSearch    = $request->query('sales_search', '');
        $salesStartDate = $request->query('sales_start_date', '');
        $salesEndDate   = $request->query('sales_end_date', '');

        $salesQuery = Sale::where('branch_id', $branch->id)
            ->with(['user:id,name,username', 'customer:id,name'])
            ->when($salesSearch, function ($q) use ($salesSearch) {
                $q->where(function ($sq) use ($salesSearch) {
                    $sq->where('receipt_id', 'like', "%{$salesSearch}%")
                       ->orWhere('payment_method', 'like', "%{$salesSearch}%")
                       ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$salesSearch}%"))
                       ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$salesSearch}%"));
                });
            })
            ->when($salesStartDate, fn ($q) => $q->whereDate('created_at', '>=', $salesStartDate))
            ->when($salesEndDate, fn ($q) => $q->whereDate('created_at', '<=', $salesEndDate));

        $recentSales = (clone $salesQuery)
            ->latest('id')
            ->paginate(15, ['*'], 'sales_page')
            ->withQueryString()
            ->through(fn (Sale $s) => [
                'id'             => $s->id,
                'receipt_number' => $s->receipt_id ?? "REC-{$s->id}",
                'total_amount'   => (float) $s->final_total,
                'payment_method' => $s->payment_method,
                'cashier_name'   => $s->user?->name ?? 'Unknown',
                'customer_name'  => $s->customer?->name ?? 'Walk-in Customer',
                'items_count'    => $s->items_order_count ?? 1,
                'created_at'     => $s->created_at?->format('d M Y, h:i A'),
            ]);

        // ── 5. Branch Activity & Audit Logs (Paginated) ───────────────────────
        $logSearch    = $request->query('log_search', '');
        $logStartDate = $request->query('log_start_date', '');
        $logEndDate   = $request->query('log_end_date', '');

        $logQuery = PosLog::where('branch_id', $branch->id)
            ->with('user:id,name,email')
            ->when($logSearch, function ($q) use ($logSearch) {
                $q->where(function ($sq) use ($logSearch) {
                    $sq->where('log', 'like', "%{$logSearch}%")
                       ->orWhere('action_type', 'like', "%{$logSearch}%")
                       ->orWhere('ip_address', 'like', "%{$logSearch}%")
                       ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$logSearch}%"));
                });
            })
            ->when($logStartDate, fn ($q) => $q->whereDate('created_at', '>=', $logStartDate))
            ->when($logEndDate, fn ($q) => $q->whereDate('created_at', '<=', $logEndDate));

        $activityLogs = (clone $logQuery)
            ->latest('id')
            ->paginate(20, ['*'], 'logs_page')
            ->withQueryString()
            ->through(fn (PosLog $log) => [
                'id'          => $log->id,
                'action_type' => $log->action_type,
                'log'         => $log->log,
                'user_name'   => $log->user?->name ?? 'System / SuperAdmin',
                'ip_address'  => $log->ip_address,
                'details'     => $log->details,
                'created_at'  => $log->created_at?->format('d M Y, h:i A'),
            ]);

        // ── 6. Commerce Integration (E-commerce Link) ─────────────────────────
        $commerceIntegration = \App\Modules\CommerceIntegration\Models\CommerceIntegration::where('branch_id', $branch->id)
            ->with(['tokens' => fn ($q) => $q->latest()->limit(1)])
            ->first();

        $ecommerceCapabilityEnabled = $branch->organization ? (bool) $branch->organization->hasFeature('feature_ecommerce_integration') : false;

        $commerceStats = null;
        if ($commerceIntegration) {
            $commerceStats = [
                'total_reservations' => $commerceIntegration->reservations()->count(),
                'active_reservations' => $commerceIntegration->reservations()->active()->count(),
                'confirmed_reservations' => $commerceIntegration->reservations()->where('status', 'confirmed')->count(),
                'mappings_count' => $commerceIntegration->mappings()->count(),
                'movements_count' => $commerceIntegration->movements()->count(),
            ];
        }

        return Inertia::render('SuperAdmin/Branches/Show', [
            'branch' => [
                'id'         => $branch->id,
                'name'       => $branch->name,
                'slug'       => $branch->slug,
                'address'    => $branch->address,
                'phone'      => $branch->phone,
                'email'         => $branch->email,
                'is_active'     => (bool) $branch->is_active,
                'is_ai_enabled' => (bool) ($branch->is_ai_enabled ?? true),
                'logo_url'      => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
                'created_at'    => $branch->created_at?->format('d M Y, h:i A'),
                'updated_at'    => $branch->updated_at?->format('d M Y, h:i A'),
                'organization'  => $branch->organization ? ['id' => $branch->organization->id, 'name' => $branch->organization->name] : null,
            ],
            'all_organizations' => Organization::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'settings' => $settings,
            'stats' => [
                'users_count'   => $totalUsersCount,
                'items_count'   => $totalItemsCount,
                'sales_count'   => $totalSalesCount,
                'total_revenue' => $totalRevenue,
                'month_revenue' => $monthRevenue,
                'today_revenue' => $todayRevenue,
            ],
            'users'           => $users,
            'roles'           => $roles,
            'all_permissions' => $allPermissions,
            'branch_items'         => $branchItems,
            'global_items'         => $globalItems,
            'import_library_files' => \App\Models\ImportLibraryFile::where('status', 'active')->orderBy('name')->get(['id', 'name', 'item_type_tag', 'file_format', 'row_count']),
            'import_item_types'    => \App\Models\ImportItemType::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories'           => $categories,
            'recent_sales'         => $recentSales,
            'activity_logs'        => $activityLogs,
            'commerce_integration' => $commerceIntegration ? [
                'id' => $commerceIntegration->id,
                'client_id' => $commerceIntegration->client_id,
                'api_base_url' => url('/api/commerce/v1'),
                'name' => $commerceIntegration->name,
                'platform' => $commerceIntegration->platform,
                'status' => $commerceIntegration->status,
                'webhook_url' => $commerceIntegration->webhook_url,
                'settings' => $commerceIntegration->settings,
                'last_synced_at' => $commerceIntegration->last_synced_at?->format('d M Y, h:i A'),
                'created_at' => $commerceIntegration->created_at?->format('d M Y, h:i A'),
                'has_token' => $commerceIntegration->tokens()->exists(),
                'last_token_used_at' => $commerceIntegration->tokens()->latest()->first()?->last_used_at?->format('d M Y, h:i A'),
                'stats' => $commerceStats,
            ] : null,
            'commerce_api_base_url' => url('/api/commerce/v1'),
            'ecommerce_capability_enabled' => $ecommerceCapabilityEnabled,
            'new_commerce_token' => session('new_commerce_token'),
            'filters'              => [
                'item_search'      => $search,
                'sales_search'     => $salesSearch,
                'sales_start_date' => $salesStartDate,
                'sales_end_date'   => $salesEndDate,
                'log_search'       => $logSearch,
                'log_start_date'   => $logStartDate,
                'log_end_date'     => $logEndDate,
                'active_tab'       => $request->query('tab', 'overview'),
            ],
        ]);
    }

    public function edit(Branch $branch)
    {
        return redirect()->route('superadmin.branches.show', ['branch' => $branch->slug]);
    }

    /**
     * Update branch profile identity (Name, Slug, Contact Info, Active Status).
     */
    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'slug'      => [
                'required',
                'string',
                'max:100',
                'unique:branches,slug,' . $branch->id,
                'regex:/^[a-z0-9\-]+$/',
                Rule::notIn(config('organizations.reserved_slugs', [])),
            ],
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
        ], [
            'slug.not_in' => "The slug ':input' is reserved by the system and cannot be used for a branch URL.",
        ]);

        $oldSlug = $branch->slug;
        $slugChanged = ($data['slug'] !== $oldSlug);

        $branch->update($data);

        ActivityLogger::log(
            'branch',
            "Super Admin updated branch profile for '{$branch->name}'" . ($slugChanged ? " (Slug changed from '{$oldSlug}' to '{$branch->slug}')" : ""),
            $branch->id,
            ['changed_fields' => array_keys($data)]
        );

        if ($slugChanged) {
            return redirect()->route('superadmin.branches.show', ['branch' => $branch->slug])
                ->with('success', "Branch profile updated and login URL changed to '/{$branch->slug}'.");
        }

        return back()->with('success', 'Branch profile details updated successfully.');
    }

    /**
     * Move a branch to another organization.
     */
    public function moveOrganization(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
        ], [
            'organization_id.required' => 'Please select the destination organization.',
            'organization_id.exists'   => 'Selected organization does not exist.',
        ]);

        $oldOrgId = $branch->organization_id;
        $newOrgId = (int) $data['organization_id'];

        if ($oldOrgId === $newOrgId) {
            return back()->with('info', 'Branch is already assigned to this organization.');
        }

        $oldOrg = $oldOrgId ? Organization::find($oldOrgId) : null;
        $newOrg = Organization::findOrFail($newOrgId);

        $branch->organization_id = $newOrgId;
        $branch->save();

        OrganizationActivityLog::create([
            'organization_id' => $newOrgId,
            'actor_user_id'   => Auth::id(),
            'action'          => 'branch_moved',
            'details'         => [
                'branch_id'              => $branch->id,
                'branch_name'            => $branch->name,
                'branch_slug'            => $branch->slug,
                'from_organization_id'   => $oldOrgId,
                'from_organization_name' => $oldOrg?->name ?? 'None',
                'to_organization_id'     => $newOrgId,
                'to_organization_name'   => $newOrg->name,
            ],
            'created_at'      => now(),
        ]);

        ActivityLogger::log(
            'branch',
            "Super Admin moved branch '{$branch->name}' from '" . ($oldOrg?->name ?? 'None') . "' to '{$newOrg->name}'",
            $branch->id
        );

        return back()->with('success', "Branch '{$branch->name}' moved to organization '{$newOrg->name}' successfully.");
    }

    /**
     * Update branch logo image.
     */
    public function updateLogo(Request $request, Branch $branch)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            // Delete existing logo if present
            if ($branch->logo_path && Storage::disk('public')->exists($branch->logo_path)) {
                Storage::disk('public')->delete($branch->logo_path);
            }

            $path = $request->file('logo')->store('branches/logos', 'public');
            $branch->update(['logo_path' => $path]);

            ActivityLogger::log(
                'setting',
                "Updated branch logo image for '{$branch->name}'",
                $branch->id
            );

            return back()->with('success', 'Branch logo updated successfully.');
        }

        return back()->withErrors(['logo' => 'No image file was uploaded.']);
    }

    /**
     * Update branch configuration (Tax, Printer, Offline, Terminal Behavior).
     */
    public function updateSettings(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'is_tax_enabled'           => 'required|boolean',
            'tax_percentage'           => 'required|numeric|min:0|max:100',
            'receipt_paper_size'       => 'required|string|in:80mm,58mm',
            'receipt_printer_name'     => 'nullable|string|max:255',
            'printer_type'             => 'nullable|string|max:50',
            'printer_connection'       => 'nullable|string|max:50',
            'printer_ip_address'       => 'nullable|string|max:50',
            'receipt_copies'           => 'required|integer|min:1|max:5',
            'auto_print_receipt'       => 'required|boolean',
            'show_receipt_preview'     => 'required|boolean',
            'is_offline_enabled'       => 'required|boolean',
            'is_turnstile_enabled'     => 'required|boolean',
            'is_auto_fullscreen_enabled' => 'required|boolean',
            'held_sales_visibility'    => 'nullable|string|in:device_only,shared_across_branch',
            'offline_receipt_prefix'   => 'nullable|string|max:10',
            'is_price_editable'        => 'required|boolean',
            'is_qty_deduction'         => 'required|boolean',
            'is_offline_qty_deduction' => 'nullable|boolean',
            'out_of_stock'             => 'required|integer|min:0',
            'is_check_expiration'      => 'required|boolean',
            'batch_tracking_enabled'   => 'nullable|boolean',
            'expiry_alert_days'        => 'nullable|integer|min:1',
            'is_imei_enabled'          => 'required|boolean',
            'is_show_buy_price'        => 'required|boolean',
            'is_use_profit_percentage' => 'required|boolean',
            'wholesale_profit_percent' => 'nullable|numeric|min:0',
            'consumer_profit_percent'  => 'nullable|numeric|min:0',
            'sell_interface'           => 'required|string|in:classic,gallery',
            'business_sector'          => 'required|string|in:commerce,health',
            'require_till_opening'     => 'nullable|boolean',
            'print_till_slip'          => 'nullable|boolean',
        ]);

        $settings = $branch->getSettings();
        $settings->update($validated);

        ActivityLogger::log(
            'setting',
            "Super Admin updated POS, Tax, Printer & Terminal settings for branch '{$branch->name}'",
            $branch->id,
            ['settings' => $validated]
        );

        return back()->with('success', 'Branch settings, Tax & Printer configuration updated successfully.');
    }

    public function destroy(Branch $branch)
    {
        // Safety check: don't delete a branch with live sales
        if ($branch->sales()->exists()) {
            return back()->withErrors(['branch' => 'Cannot delete a branch with existing sales records.']);
        }

        $branchName = $branch->name;
        $branch->delete();

        ActivityLogger::log(
            'branch',
            "Super Admin deleted branch '{$branchName}'",
            null
        );

        return redirect()->route('superadmin.branches.index')
            ->with('success', "Branch '{$branchName}' deleted successfully.");
    }

    /**
     * Toggle branch active/inactive status.
     */
    public function toggle(Branch $branch)
    {
        $branch->update(['is_active' => !$branch->is_active]);
        $status = $branch->is_active ? 'enabled' : 'disabled';

        ActivityLogger::log(
            'branch',
            "Super Admin {$status} branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "Branch {$status} successfully.");
    }

    /**
     * Toggle branch offline sales enablement status.
     */
    public function toggleOffline(Branch $branch)
    {
        $settings = $branch->getSettings();
        $newState = !$settings->is_offline_enabled;
        $settings->update(['is_offline_enabled' => $newState]);
        $status = $newState ? 'enabled' : 'disabled';

        ActivityLogger::log(
            'setting',
            "Super Admin {$status} offline sales capability for branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "Offline sales mode {$status} for {$branch->name}.");
    }

    /**
     * Toggle branch Cloudflare Turnstile CAPTCHA requirement.
     */
    public function toggleTurnstile(Branch $branch)
    {
        $settings = $branch->getSettings();
        $newState = !($settings->is_turnstile_enabled ?? true);
        $settings->update(['is_turnstile_enabled' => $newState]);
        $status = $newState ? 'enabled' : 'disabled (low-network mode)';

        ActivityLogger::log(
            'setting',
            "Super Admin {$status} Cloudflare Turnstile CAPTCHA for branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "Cloudflare Turnstile CAPTCHA {$status} for {$branch->name}.");
    }

    /**
     * Toggle branch local offline quantity deduction requirement.
     */
    public function toggleOfflineQtyDeduction(Branch $branch)
    {
        $settings = $branch->getSettings();
        $newState = !($settings->is_offline_qty_deduction ?? false);
        $settings->update(['is_offline_qty_deduction' => $newState]);
        $status = $newState ? 'enabled (strict offline stock validation)' : 'skipped (offline sales proceed; verified on sync)';

        ActivityLogger::log(
            'setting',
            "Super Admin set offline quantity deduction to {$status} for branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "Offline quantity deduction {$status} for {$branch->name}.");
    }

    /**
     * Toggle branch AI capabilities.
     */
    public function toggleAi(Branch $branch)
    {
        $newState = !($branch->is_ai_enabled ?? true);
        $branch->is_ai_enabled = $newState;
        $branch->save();
        $status = $newState ? 'enabled' : 'disabled';

        ActivityLogger::log(
            'setting',
            "Super Admin {$status} AI intelligence features for branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "AI intelligence features {$status} for {$branch->name}.");
    }

    /**
     * Toggle branch auto-fullscreen on login requirement.
     */
    public function toggleAutoFullscreen(Branch $branch)
    {
        $settings = $branch->getSettings();
        $newState = !($settings->is_auto_fullscreen_enabled ?? false);
        $settings->update(['is_auto_fullscreen_enabled' => $newState]);
        $status = $newState ? 'enabled' : 'disabled';

        ActivityLogger::log(
            'setting',
            "Super Admin {$status} auto-fullscreen on POS login for branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "Auto-fullscreen on POS login {$status} for {$branch->name}.");
    }

    /**
     * Toggle branch till opening requirement before selling.
     */
    public function toggleTillOpening(Branch $branch)
    {
        $settings = $branch->getSettings();
        $newState = !($settings->require_till_opening ?? true);
        $settings->update(['require_till_opening' => $newState]);
        $status = $newState ? 'enabled (cashiers must declare float before selling)' : 'disabled (direct checkout allowed)';

        ActivityLogger::log(
            'setting',
            "Super Admin set till opening requirement to {$status} for branch '{$branch->name}'",
            $branch->id
        );

        return back()->with('success', "Till opening requirement {$status} for {$branch->name}.");
    }

    private function streamExcelOrCsv(Spreadsheet $spreadsheet, string $filenamePrefix, string $format = 'xlsx')
    {
        $format = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $filename = $filenamePrefix . '_' . date('Y-m-d_His') . '.' . $format;

        if ($format === 'csv') {
            $writer = new CsvWriter($spreadsheet);
            $writer->setUseBOM(true);
            $writer->setPreCalculateFormulas(false);
            $contentType = 'text/csv; charset=UTF-8';
        } else {
            $writer = new XlsxWriter($spreadsheet);
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * Export branch catalog items in CSV or Excel format with customizable quantity levels.
     */
    public function exportItems(Request $request, Branch $branch)
    {
        $format = $request->query('format', 'xlsx');
        $quantityOption = $request->query('quantity_option', 'total');
        $search = $request->query('item_search', $request->query('search', ''));

        $query = Item::where('branch_id', $branch->id)
            ->with(['category:id,name', 'groupAddress:id,name']);

        if (!empty($search)) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('item_name', 'like', $term)
                  ->orWhere('barcode_number', 'like', $term);
            });
        }

        $items = $query->orderBy('item_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Branch Catalog');

        $headers = [
            '#',
            'Barcode / SKU',
            'Item Name',
            'Category',
            'Group Address / Shelf',
        ];

        if ($quantityOption === 'total') {
            $headers[] = 'Front Store Qty';
            $headers[] = 'Back Store Qty';
            $headers[] = 'Total Stock Qty';
        } elseif ($quantityOption === 'front_store') {
            $headers[] = 'Front Store Qty';
        } elseif ($quantityOption === 'back_store') {
            $headers[] = 'Back Store Qty';
        }

        $headers[] = 'Unit Label';
        $headers[] = 'Cost Price (₦)';
        $headers[] = 'Selling Price (₦)';
        $headers[] = 'Wholesale Price (₦)';
        $headers[] = 'Expiry Date';
        $headers[] = 'IMEI Tracked';
        $headers[] = 'Stock Status';

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($items as $idx => $item) {
            $frontQty = (int) $item->front_store_qty;
            $backQty  = (int) $item->back_store_qty;
            $totalQty = $frontQty + $backQty;

            $status = 'Adequate';
            if ($totalQty <= 0) {
                $status = 'Out of Stock';
            } elseif ($item->needs_reorder) {
                $status = 'Low Stock (Reorder)';
            }

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            if (!empty($item->barcode_number)) {
                $barcodeVal = (string) $item->barcode_number;
                if ($format === 'csv') {
                    $sheet->setCellValueExplicit([$col++, $rowNum], '="' . $barcodeVal . '"', DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValueExplicit([$col++, $rowNum], $barcodeVal, DataType::TYPE_STRING);
                    $sheet->getStyle([$col - 1, $rowNum])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            } else {
                $sheet->setCellValue([$col++, $rowNum], '-');
            }
            $sheet->setCellValue([$col++, $rowNum], $item->item_name);
            $sheet->setCellValue([$col++, $rowNum], $item->category?->name ?: 'Uncategorized');
            $sheet->setCellValue([$col++, $rowNum], $item->groupAddress?->name ?: '-');

            if ($quantityOption === 'total') {
                $sheet->setCellValue([$col++, $rowNum], $frontQty);
                $sheet->setCellValue([$col++, $rowNum], $backQty);
                $sheet->setCellValue([$col++, $rowNum], $totalQty);
            } elseif ($quantityOption === 'front_store') {
                $sheet->setCellValue([$col++, $rowNum], $frontQty);
            } elseif ($quantityOption === 'back_store') {
                $sheet->setCellValue([$col++, $rowNum], $backQty);
            }

            $sheet->setCellValue([$col++, $rowNum], $item->unit_label ?: ($item->unit ?: 'unit'));
            $sheet->setCellValue([$col++, $rowNum], (float) $item->buy_price);
            $sheet->setCellValue([$col++, $rowNum], (float) ($item->price ?? 0));
            $sheet->setCellValue([$col++, $rowNum], (float) ($item->wholesale_price ?? 0));
            $sheet->setCellValue([$col++, $rowNum], $item->expiry_date ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $item->is_imei ? 'YES' : 'No');
            $sheet->setCellValue([$col++, $rowNum], $status);

            $rowNum++;
        }

        if ($format !== 'csv' && $rowNum > 2) {
            $sheet->getStyle('B2:B' . ($rowNum - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        ActivityLogger::log(
            'branch',
            "Super Admin exported catalog items for branch '{$branch->name}' ({$format}, quantity: {$quantityOption})",
            $branch->id
        );

        return $this->streamExcelOrCsv($spreadsheet, $branch->slug . '_inventory_catalog', $format);
    }

    /**
     * Export branch sales transactions and financial logs in CSV or Excel format.
     */
    public function exportSales(Request $request, Branch $branch)
    {
        $format    = $request->query('format', 'xlsx');
        $search    = $request->query('sales_search', $request->query('search', ''));
        $startDate = $request->query('sales_start_date', $request->query('start_date', ''));
        $endDate   = $request->query('sales_end_date', $request->query('end_date', ''));

        $query = Sale::where('branch_id', $branch->id)
            ->with(['user:id,name,username', 'customer:id,name,phone', 'saleOrders']);

        if (!empty($search)) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('receipt_id', 'like', $term)
                  ->orWhere('payment_method', 'like', $term)
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', $term)->orWhere('phone', 'like', $term))
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $term)->orWhere('username', 'like', $term));
            });
        }

        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $sales = $query->latest('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sales Financials');

        $headers = [
            '#',
            'Receipt ID',
            'Date & Time',
            'Cashier / Staff',
            'Customer Name',
            'Customer Phone',
            'Payment Method',
            'Items Count',
            'Subtotal (₦)',
            'Discount (₦)',
            'Total Amount (₦)',
            'Gross Profit (₦)',
            'Status / Type',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($sales as $idx => $s) {
            $staffName = $s->user?->name ?: ($s->user?->username ?: '-');
            $custName  = $s->customer?->name ?: 'Walk-in Customer';
            $custPhone = $s->customer?->phone ?: '-';
            $itemCount = $s->saleOrders ? $s->saleOrders->sum('qty') : ($s->items_order_count ?? 1);

            $payMethod = 'Cash';
            if ($s->is_debt) {
                $payMethod = 'Debt / Credit';
            } elseif ((float) $s->cash > 0 && (float) $s->bank_transfer > 0) {
                $payMethod = 'Split (Cash + Bank)';
            } elseif ((float) $s->bank_transfer > 0) {
                $payMethod = 'Bank Transfer';
            } elseif ($s->payment_method) {
                $payMethod = ucfirst($s->payment_method);
            }

            $status = $s->is_debt ? 'Unpaid Debt' : 'Completed';

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $receiptVal = (string) ($s->receipt_id ?: "REC-{$s->id}");
            $sheet->setCellValueExplicit([$col++, $rowNum], $receiptVal, DataType::TYPE_STRING);
            $sheet->setCellValue([$col++, $rowNum], $s->created_at ? $s->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue([$col++, $rowNum], $staffName);
            $sheet->setCellValue([$col++, $rowNum], $custName);
            if ($custPhone && $custPhone !== '-') {
                if ($format === 'csv') {
                    $sheet->setCellValueExplicit([$col++, $rowNum], '="' . $custPhone . '"', DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValueExplicit([$col++, $rowNum], (string) $custPhone, DataType::TYPE_STRING);
                    $sheet->getStyle([$col - 1, $rowNum])->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
                }
            } else {
                $sheet->setCellValue([$col++, $rowNum], '-');
            }
            $sheet->setCellValue([$col++, $rowNum], $payMethod);
            $sheet->setCellValue([$col++, $rowNum], (int) $itemCount);
            $sheet->setCellValue([$col++, $rowNum], (float) ($s->amount_cost ?: $s->final_total));
            $sheet->setCellValue([$col++, $rowNum], (float) $s->discount_amount);
            $sheet->setCellValue([$col++, $rowNum], (float) $s->final_total);
            $sheet->setCellValue([$col++, $rowNum], (float) $s->profit_made);
            $sheet->setCellValue([$col++, $rowNum], $status);

            $rowNum++;
        }

        if ($format !== 'csv' && $rowNum > 2) {
            $sheet->getStyle('B2:B' . ($rowNum - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->getStyle('F2:F' . ($rowNum - 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        ActivityLogger::log(
            'branch',
            "Super Admin exported sales transactions for branch '{$branch->name}' ({$format})",
            $branch->id
        );

        return $this->streamExcelOrCsv($spreadsheet, $branch->slug . '_sales_financials', $format);
    }

    /**
     * Export branch activity and audit logs in CSV or Excel format.
     */
    public function exportLogs(Request $request, Branch $branch)
    {
        $format    = $request->query('format', 'xlsx');
        $search    = $request->query('log_search', $request->query('search', ''));
        $startDate = $request->query('log_start_date', $request->query('start_date', ''));
        $endDate   = $request->query('log_end_date', $request->query('end_date', ''));

        $query = PosLog::where('branch_id', $branch->id)
            ->with('user:id,name,username,email');

        if (!empty($search)) {
            $term = '%' . trim($search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('log', 'like', $term)
                  ->orWhere('action_type', 'like', $term)
                  ->orWhere('ip_address', 'like', $term)
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', $term)->orWhere('username', 'like', $term)->orWhere('email', 'like', $term));
            });
        }

        if (!empty($startDate)) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $logs = $query->latest('id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Audit Logs');

        $headers = [
            '#',
            'Timestamp',
            'Action Type',
            'Log / Event Description',
            'Staff / User',
            'Email / Username',
            'IP Address',
            'Details / Payload',
        ];

        foreach ($headers as $colIdx => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$colLetter}1", $header);
        }

        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('059669'); // Emerald

        $rowNum = 2;
        foreach ($logs as $idx => $log) {
            $userName  = $log->user?->name ?? 'System / SuperAdmin';
            $userEmail = $log->user?->email ?: ($log->user?->username ?: '-');
            $details   = is_array($log->details) ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : ($log->details ?: '-');

            $col = 1;
            $sheet->setCellValue([$col++, $rowNum], $idx + 1);
            $sheet->setCellValue([$col++, $rowNum], $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : '-');
            $sheet->setCellValue([$col++, $rowNum], strtoupper($log->action_type ?: 'GENERAL'));
            $sheet->setCellValue([$col++, $rowNum], $log->log);
            $sheet->setCellValue([$col++, $rowNum], $userName);
            $sheet->setCellValue([$col++, $rowNum], $userEmail);
            $sheet->setCellValue([$col++, $rowNum], $log->ip_address ?: '-');
            $sheet->setCellValue([$col++, $rowNum], $details);

            $rowNum++;
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        ActivityLogger::log(
            'branch',
            "Super Admin exported audit logs for branch '{$branch->name}' ({$format})",
            $branch->id
        );

        return $this->streamExcelOrCsv($spreadsheet, $branch->slug . '_audit_logs', $format);
    }
}


