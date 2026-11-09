<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Laundry\Models\LaundryCategory;
use App\Modules\Laundry\Models\LaundryService;
use App\Modules\Laundry\Models\LaundrySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaundryServiceController extends Controller
{
    /**
     * Display a listing of laundry services.
     */
    public function index(Request $request): Response
    {
        $branch = current_branch();

        $query = LaundryService::query()
            ->forBranch($branch->id)
            ->with(['category:id,name,slug', 'activeDiscount'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhereHas('category', fn($c) => $c->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->category, function ($q, $cat) {
                if (is_numeric($cat)) {
                    $q->where('category_id', $cat);
                } else {
                    $q->whereHas('category', fn($c) => $c->where('name', $cat)->orWhere('slug', $cat));
                }
            })
            ->when($request->status, function ($q, $status) {
                if ($status === 'active') {
                    $q->where('is_active', true);
                } elseif ($status === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        $services = $query->paginate(25)->withQueryString();

        $categories = LaundryCategory::forBranch($branch->id)
            ->withCount('services')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $lSetting = LaundrySetting::forBranch($branch->id);

        return Inertia::render('Laundry/Services/Index', [
            'services'   => $services,
            'categories' => $categories,
            'filters'    => $request->only(['search', 'category', 'status']),
            'can_manage' => $this->canManageUser(),
            'settings'   => [
                'enable_express_service'     => (bool) ($lSetting->enable_express_service ?? true),
                'express_turnaround_hours'   => (int) ($lSetting->express_turnaround_hours ?? 24),
                'default_express_multiplier' => (float) ($lSetting->default_express_multiplier ?? 1.50),
            ],
        ]);
    }

    /**
     * Store a newly created laundry service.
     */
    public function store(Request $request)
    {
        $this->authorizeManage();

        $branch = current_branch();

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'category_id'   => ['nullable', 'integer', 'exists:laundry_categories,id'],
            'pricing_type'  => ['required', 'string', 'in:per_piece,per_kg'],
            'price'         => ['required', 'numeric', 'min:0'],
            'express_price' => ['nullable', 'numeric', 'min:0'],
            'is_active'     => ['boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['branch_id'] = $branch->id;
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        LaundryService::create($validated);

        return back()->with('success', 'Laundry service created successfully.');
    }

    /**
     * Update the specified laundry service.
     */
    public function update(Request $request, $branchParam, LaundryService $service)
    {
        $this->authorizeManage();

        $branch = current_branch();
        if ($service->branch_id !== $branch->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'category_id'   => ['nullable', 'integer', 'exists:laundry_categories,id'],
            'pricing_type'  => ['required', 'string', 'in:per_piece,per_kg'],
            'price'         => ['required', 'numeric', 'min:0'],
            'express_price' => ['nullable', 'numeric', 'min:0'],
            'is_active'     => ['boolean'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
        ]);

        $service->update($validated);

        return back()->with('success', 'Laundry service updated successfully.');
    }

    /**
     * Toggle the active status of the specified service.
     */
    public function toggleActive($branchParam, LaundryService $service)
    {
        $this->authorizeManage();

        $branch = current_branch();
        if ($service->branch_id !== $branch->id) {
            abort(404);
        }

        $service->update([
            'is_active' => !$service->is_active,
        ]);

        $status = $service->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Service {$status} successfully.");
    }

    /**
     * Delete the specified laundry service if not used in any orders.
     */
    public function destroy($branchParam, LaundryService $service)
    {
        $this->authorizeManage();

        $branch = current_branch();
        if ($service->branch_id !== $branch->id) {
            abort(404);
        }

        if ($service->orderItems()->exists()) {
            $service->update(['is_active' => false]);
            return back()->with('info', 'Service has order history and was deactivated instead of deleted.');
        }

        $service->delete();

        return back()->with('success', 'Laundry service deleted successfully.');
    }

    /**
     * Store a new laundry category.
     */
    public function storeCategory(Request $request)
    {
        $this->authorizeManage();

        $branch = current_branch();

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $exists = LaundryCategory::forBranch($branch->id)->where('name', $validated['name'])->exists();
        if ($exists) {
            return back()->withErrors(['name' => 'A category with this name already exists in this branch.']);
        }

        $validated['branch_id'] = $branch->id;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = true;

        LaundryCategory::create($validated);

        return back()->with('success', 'Laundry category created successfully.');
    }

    /**
     * Update an existing laundry category.
     */
    public function updateCategory(Request $request, $branch, $id)
    {
        $this->authorizeManage();

        $branchModel = current_branch();
        $category = LaundryCategory::where('branch_id', $branchModel->id)->findOrFail($id);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $exists = LaundryCategory::forBranch($branchModel->id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $category->id)
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'Another category with this name already exists.']);
        }

        $category->update($validated);

        return back()->with('success', 'Laundry category updated successfully.');
    }

    /**
     * Delete an existing laundry category if no services are assigned to it.
     */
    public function destroyCategory(Request $request, $branch, $id)
    {
        $this->authorizeManage();

        $branchModel = current_branch();
        $category = LaundryCategory::where('branch_id', $branchModel->id)->findOrFail($id);

        if ($category->services()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete category with active services. Please reassign or delete its services first.']);
        }

        $category->delete();

        return back()->with('success', 'Laundry category deleted successfully.');
    }

    protected function authorizeManage(): void
    {
        if (!$this->canManageUser()) {
            abort(403, 'Unauthorized to manage laundry services.');
        }
    }

    protected function canManageUser(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isOrganizationAdmin()
            || $user->isBranchAdmin()
            || $user->hasRole(['branch-admin', 'manager', 'admin'])
            || \App\Services\RoleService::can('laundry.manage_services')
            || $user->hasPermissionTo('laundry.manage_services');
    }
}
