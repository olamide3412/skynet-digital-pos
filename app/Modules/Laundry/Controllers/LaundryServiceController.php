<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Laundry\Models\LaundryService;
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
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($q, $cat) {
                $q->where('category', $cat);
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

        $categories = LaundryService::forBranch($branch->id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return Inertia::render('Laundry/Services/Index', [
            'services'   => $services,
            'categories' => $categories,
            'filters'    => $request->only(['search', 'category', 'status']),
            'can_manage' => auth()->user()->isBranchAdmin() || auth()->user()->hasPermissionTo('laundry.manage_services'),
        ]);
    }

    /**
     * Store a newly created laundry service.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.manage_services')) {
            abort(403, 'Unauthorized to manage laundry services.');
        }

        $branch = current_branch();

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'category'     => ['nullable', 'string', 'max:100'],
            'pricing_type' => ['required', 'string', 'in:per_piece,per_kg'],
            'price'        => ['required', 'numeric', 'min:0'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
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
    public function update(Request $request, LaundryService $service)
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.manage_services')) {
            abort(403, 'Unauthorized to manage laundry services.');
        }

        $branch = current_branch();
        if ($service->branch_id !== $branch->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'category'     => ['nullable', 'string', 'max:100'],
            'pricing_type' => ['required', 'string', 'in:per_piece,per_kg'],
            'price'        => ['required', 'numeric', 'min:0'],
            'is_active'    => ['boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ]);

        $service->update($validated);

        return back()->with('success', 'Laundry service updated successfully.');
    }

    /**
     * Toggle the active status of the specified service.
     */
    public function toggleActive(LaundryService $service)
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.manage_services')) {
            abort(403, 'Unauthorized to manage laundry services.');
        }

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
    public function destroy(LaundryService $service)
    {
        $user = auth()->user();
        if (!$user->isBranchAdmin() && !$user->isSuperAdmin() && !$user->hasPermissionTo('laundry.manage_services')) {
            abort(403, 'Unauthorized to manage laundry services.');
        }

        $branch = current_branch();
        if ($service->branch_id !== $branch->id) {
            abort(404);
        }

        if ($service->orderItems()->exists()) {
            // Soft deactivate instead of delete to preserve historical integrity
            $service->update(['is_active' => false]);
            return back()->with('info', 'Service has order history and was deactivated instead of deleted.');
        }

        $service->delete();

        return back()->with('success', 'Laundry service deleted successfully.');
    }
}
