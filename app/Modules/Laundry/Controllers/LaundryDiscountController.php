<?php

namespace App\Modules\Laundry\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Laundry\Models\LaundryDiscount;
use App\Modules\Laundry\Models\LaundryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class LaundryDiscountController extends Controller
{
    private function authorizeManage(): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        if ($user->isBranchAdmin() || $user->isSuperAdmin()) {
            return;
        }

        if ($user->hasPermissionTo('laundry.manage_discounts') || $user->hasPermissionTo('laundry.manage_services')) {
            return;
        }

        abort(403, 'Unauthorized. You do not have permission to manage laundry discounts.');
    }

    /**
     * Display laundry discounts management page (General and Service Level).
     */
    public function index(Request $request)
    {
        $this->authorizeManage();

        $branch = current_branch();

        $generalDiscounts = LaundryDiscount::forBranch($branch->id)
            ->general()
            ->with('creator:id,name,username')
            ->latest('id')
            ->get();

        $serviceDiscounts = LaundryDiscount::forBranch($branch->id)
            ->where('scope', 'service')
            ->with(['service.category:id,name', 'creator:id,name,username'])
            ->latest('id')
            ->get();

        $services = LaundryService::forBranch($branch->id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->select(['id', 'branch_id', 'category_id', 'name', 'pricing_type', 'price'])
            ->orderBy('name')
            ->get();

        return Inertia::render('Laundry/Discounts/Index', [
            'generalDiscounts' => $generalDiscounts,
            'serviceDiscounts' => $serviceDiscounts,
            'services'         => $services,
            'can_manage'       => Auth::user()->isBranchAdmin() || Auth::user()->isSuperAdmin() || Auth::user()->hasPermissionTo('laundry.manage_discounts') || Auth::user()->hasPermissionTo('laundry.manage_services'),
        ]);
    }

    /**
     * Store a newly created general or service-level discount.
     */
    public function store(Request $request)
    {
        $this->authorizeManage();
        $branch = current_branch();

        $data = $request->validate([
            'scope'          => 'required|in:general,service',
            'service_id'     => 'required_if:scope,service|nullable|exists:laundry_services,id',
            'name'           => 'required|string|max:150',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after_or_equal:starts_at',
            'is_active'      => 'boolean',
            'notes'          => 'nullable|string|max:500',
        ]);

        $data['branch_id']  = $branch->id;
        $data['created_by'] = Auth::id();
        $data['is_active']  = $request->boolean('is_active', true);

        if ($data['scope'] === 'service' && $data['is_active'] && !empty($data['service_id'])) {
            // Deactivate any currently active discount for the same service to prevent ambiguity
            LaundryDiscount::forBranch($branch->id)
                ->where('scope', 'service')
                ->where('service_id', $data['service_id'])
                ->where('is_active', true)
                ->update(['is_active' => false]);
        } elseif ($data['scope'] === 'general' && $data['is_active']) {
            // Deactivate other active general discounts so one promo is active at a time
            LaundryDiscount::forBranch($branch->id)
                ->general()
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        LaundryDiscount::create($data);

        $label = $data['scope'] === 'service' ? 'Service promo discount' : 'General laundry promo discount';
        return back()->with('success', "{$label} created successfully.");
    }

    /**
     * Update an existing discount.
     */
    public function update(Request $request, $branchParam, LaundryDiscount $discount)
    {
        $this->authorizeManage();

        $branch = current_branch();
        abort_if($discount->branch_id !== $branch->id, 403);

        $data = $request->validate([
            'scope'          => 'required|in:general,service',
            'service_id'     => 'required_if:scope,service|nullable|exists:laundry_services,id',
            'name'           => 'required|string|max:150',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after_or_equal:starts_at',
            'is_active'      => 'boolean',
            'notes'          => 'nullable|string|max:500',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['scope'] === 'service' && $data['is_active'] && !empty($data['service_id'])) {
            LaundryDiscount::forBranch($branch->id)
                ->where('scope', 'service')
                ->where('service_id', $data['service_id'])
                ->where('id', '!=', $discount->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        } elseif ($data['scope'] === 'general' && $data['is_active']) {
            LaundryDiscount::forBranch($branch->id)
                ->general()
                ->where('id', '!=', $discount->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $discount->update($data);

        return back()->with('success', 'Discount updated successfully.');
    }

    /**
     * Toggle active state of a discount.
     */
    public function toggleActive($branchParam, LaundryDiscount $discount)
    {
        $this->authorizeManage();

        $branch = current_branch();
        abort_if($discount->branch_id !== $branch->id, 403);

        $newActive = !$discount->is_active;

        if ($newActive) {
            if ($discount->scope === 'service' && $discount->service_id) {
                LaundryDiscount::forBranch($branch->id)
                    ->where('scope', 'service')
                    ->where('service_id', $discount->service_id)
                    ->where('id', '!=', $discount->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            } elseif ($discount->scope === 'general') {
                LaundryDiscount::forBranch($branch->id)
                    ->general()
                    ->where('id', '!=', $discount->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        }

        $discount->update(['is_active' => $newActive]);

        return back()->with('success', 'Discount status updated successfully.');
    }

    /**
     * Delete a discount.
     */
    public function destroy($branchParam, LaundryDiscount $discount)
    {
        $this->authorizeManage();

        $branch = current_branch();
        abort_if($discount->branch_id !== $branch->id, 403);

        $discount->delete();

        return back()->with('success', 'Discount deleted successfully.');
    }
}
