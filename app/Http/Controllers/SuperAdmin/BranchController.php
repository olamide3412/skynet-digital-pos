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
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with(['settings'])
            ->withCount(['users', 'items', 'sales'])
            ->latest()
            ->get()
            ->map(fn (Branch $b) => [
                'id'                 => $b->id,
                'name'               => $b->name,
                'slug'               => $b->slug,
                'address'            => $b->address,
                'phone'              => $b->phone,
                'email'              => $b->email,
                'is_active'          => (bool) $b->is_active,
                'logo_url'           => $b->logo_path ? asset('storage/' . $b->logo_path) : null,
                'users_count'        => $b->users_count,
                'items_count'        => $b->items_count,
                'sales_count'        => $b->sales_count,
                'settings'           => $b->settings,
                'created_at'         => $b->created_at?->format('d M Y'),
            ]);

        return Inertia::render('SuperAdmin/Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Branches/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'slug'               => 'required|string|max:100|unique:branches|regex:/^[a-z0-9\-]+$/',
            'address'            => 'nullable|string|max:500',
            'phone'              => 'nullable|string|max:50',
            'email'              => 'nullable|email|max:255',
            'is_offline_enabled'   => 'nullable|boolean',
            'is_turnstile_enabled' => 'nullable|boolean',
            // Branch Admin account details
            'admin_name'         => 'required|string|max:255',
            'admin_username'     => 'required|string|max:50|unique:users,username',
            'admin_email'        => 'required|email|max:255|unique:users,email',
            'admin_password'     => 'required|string|min:8',
        ]);

        $createdBranch = DB::transaction(function () use ($data) {
            // Create branch
            $branch = Branch::create([
                'name'      => $data['name'],
                'slug'      => $data['slug'],
                'address'   => $data['address'],
                'phone'     => $data['phone'],
                'email'     => $data['email'],
                'is_active' => true,
            ]);

            // Auto-create default Branch Admin user
            $admin = User::create([
                'name'           => $data['admin_name'],
                'full_name'      => $data['admin_name'],
                'username'       => $data['admin_username'],
                'email'          => $data['admin_email'],
                'password'       => Hash::make($data['admin_password']),
                'is_active'      => true,
                'is_super_admin' => false,
                'branch_id'      => $branch->id,
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
            ->when($search, fn ($q) => $q
                ->where('item_name', 'like', "%{$search}%")
                ->orWhere('barcode_number', 'like', "%{$search}%")
            )
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
        $salesSearch = $request->query('sales_search', '');
        $recentSales = Sale::where('branch_id', $branch->id)
            ->with(['user:id,name,username', 'customer:id,name'])
            ->when($salesSearch, function ($q) use ($salesSearch) {
                $q->where('receipt_id', 'like', "%{$salesSearch}%")
                  ->orWhere('payment_method', 'like', "%{$salesSearch}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$salesSearch}%"))
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$salesSearch}%"));
            })
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
        $logSearch = $request->query('log_search', '');
        $activityLogs = PosLog::where('branch_id', $branch->id)
            ->with('user:id,name,email')
            ->when($logSearch, function ($q) use ($logSearch) {
                $q->where('log', 'like', "%{$logSearch}%")
                  ->orWhere('action_type', 'like', "%{$logSearch}%")
                  ->orWhere('ip_address', 'like', "%{$logSearch}%")
                  ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$logSearch}%"));
            })
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

        return Inertia::render('SuperAdmin/Branches/Show', [
            'branch' => [
                'id'         => $branch->id,
                'name'       => $branch->name,
                'slug'       => $branch->slug,
                'address'    => $branch->address,
                'phone'      => $branch->phone,
                'email'      => $branch->email,
                'is_active'  => (bool) $branch->is_active,
                'logo_url'   => $branch->logo_path ? asset('storage/' . $branch->logo_path) : null,
                'created_at' => $branch->created_at?->format('d M Y, h:i A'),
                'updated_at' => $branch->updated_at?->format('d M Y, h:i A'),
            ],
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
            'filters'              => [
                'item_search'  => $search,
                'sales_search' => $salesSearch,
                'log_search'   => $logSearch,
                'active_tab'   => $request->query('tab', 'overview'),
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
            'slug'      => 'required|string|max:100|unique:branches,slug,' . $branch->id . '|regex:/^[a-z0-9\-]+$/',
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:255',
            'is_active' => 'nullable|boolean',
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
            'offline_receipt_prefix'   => 'nullable|string|max:10',
            'is_price_editable'        => 'required|boolean',
            'is_qty_deduction'         => 'required|boolean',
            'out_of_stock'             => 'required|integer|min:0',
            'is_check_expiration'      => 'required|boolean',
            'is_imei_enabled'          => 'required|boolean',
            'is_show_buy_price'        => 'required|boolean',
            'is_use_profit_percentage' => 'required|boolean',
            'wholesale_profit_percent' => 'nullable|numeric|min:0',
            'consumer_profit_percent'  => 'nullable|numeric|min:0',
            'sell_interface'           => 'required|string|in:classic,gallery',
            'business_sector'          => 'required|string|in:commerce,health',
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
     * Export branch catalog items in CSV or Excel format with customizable quantity levels.
     */
    public function exportItems(Request $request, Branch $branch, ItemImportService $importService)
    {
        $format = $request->query('format', 'xlsx');
        $quantityOption = $request->query('quantity_option', 'total');

        ActivityLogger::log(
            'branch',
            "Exported catalog items for branch '{$branch->name}' ({$format}, quantity: {$quantityOption})",
            $branch->id
        );

        return $importService->exportBranchItems($branch, $format, $quantityOption);
    }
}

