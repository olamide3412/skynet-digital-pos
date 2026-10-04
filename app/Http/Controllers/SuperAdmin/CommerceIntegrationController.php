<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\ActivityLogger;
use App\Modules\CommerceIntegration\Models\CommerceIntegration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommerceIntegrationController extends Controller
{
    /**
     * Display or redirect to the branch ecommerce integration management view.
     */
    public function show(Branch $branch)
    {
        return redirect()->route('superadmin.branches.show', ['branch' => $branch->slug, 'tab' => 'ecommerce']);
    }

    /**
     * Create or connect an ecommerce integration for the branch.
     */
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $organization = $branch->organization;
        if (!$organization || !$organization->hasFeature('feature_ecommerce_integration')) {
            return back()->with('error', 'Ecommerce Integration is not enabled on this organization\'s plan. Please upgrade to Bronze plan or higher.');
        }

        $existing = CommerceIntegration::where('branch_id', $branch->id)->first();
        if ($existing) {
            return back()->with('error', 'This branch already has an active ecommerce integration.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'platform' => 'required|string|in:shopify,woocommerce,magento,custom',
            'reservation_ttl_minutes' => 'nullable|integer|min:1|max:120',
            'webhook_url' => 'nullable|url|max:255',
        ]);

        $plainTextToken = null;

        DB::transaction(function () use ($branch, $organization, $validated, &$plainTextToken, $request) {
            $integration = CommerceIntegration::create([
                'organization_id' => $organization->id,
                'branch_id' => $branch->id,
                'name' => $validated['name'],
                'platform' => $validated['platform'],
                'status' => 'active',
                'webhook_url' => $validated['webhook_url'] ?? null,
                'settings' => [
                    'reservation_ttl_minutes' => $validated['reservation_ttl_minutes'] ?? config('commerce.reservation_ttl_minutes', 30),
                ],
                'created_by' => auth()->id(),
            ]);

            // Generate server-to-server Sanctum personal access token
            $token = $integration->createToken('commerce_api_access', ['commerce:full']);
            $plainTextToken = $token->plainTextToken;

            ActivityLogger::log(
                actionType: 'create',
                message: "Created ecommerce integration '{$integration->name}' ({$integration->platform}) for branch {$branch->name}",
                branchId: $branch->id,
                details: ['integration_id' => $integration->id, 'platform' => $integration->platform]
            );
        });

        return back()->with([
            'success' => 'Ecommerce integration created successfully. Please copy the API access token now as it will not be shown again.',
            'new_commerce_token' => $plainTextToken,
        ]);
    }

    /**
     * Regenerate the server-to-server API access token.
     */
    public function regenerateToken(Request $request, Branch $branch): RedirectResponse
    {
        $integration = CommerceIntegration::where('branch_id', $branch->id)->firstOrFail();

        // Revoke old tokens
        $integration->tokens()->delete();

        // Generate new token
        $token = $integration->createToken('commerce_api_access', ['commerce:full']);

        ActivityLogger::log(
            actionType: 'update',
            message: "Regenerated API token for ecommerce integration on branch {$branch->name}",
            branchId: $branch->id,
            details: ['integration_id' => $integration->id]
        );

        return back()->with([
            'success' => 'New API token generated successfully. Copy it now as it will not be shown again.',
            'new_commerce_token' => $token->plainTextToken,
        ]);
    }

    /**
     * Toggle integration status (active / inactive).
     */
    public function toggleStatus(Request $request, Branch $branch): RedirectResponse
    {
        $integration = CommerceIntegration::where('branch_id', $branch->id)->firstOrFail();

        $newStatus = $integration->status === 'active' ? 'inactive' : 'active';
        $integration->update(['status' => $newStatus]);

        ActivityLogger::log(
            actionType: 'update',
            message: "Changed ecommerce integration status to '{$newStatus}' on branch {$branch->name}",
            branchId: $branch->id,
            details: ['integration_id' => $integration->id, 'status' => $newStatus]
        );

        return back()->with('success', "Ecommerce integration status changed to {$newStatus}.");
    }

    /**
     * Update integration configuration settings.
     */
    public function updateSettings(Request $request, Branch $branch): RedirectResponse
    {
        $integration = CommerceIntegration::where('branch_id', $branch->id)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'platform' => 'required|string|in:shopify,woocommerce,magento,custom',
            'reservation_ttl_minutes' => 'nullable|integer|min:1|max:120',
            'webhook_url' => 'nullable|url|max:255',
        ]);

        $settings = $integration->settings ?? [];
        $settings['reservation_ttl_minutes'] = $validated['reservation_ttl_minutes'] ?? 30;

        $integration->update([
            'name' => $validated['name'],
            'platform' => $validated['platform'],
            'webhook_url' => $validated['webhook_url'] ?? null,
            'settings' => $settings,
        ]);

        return back()->with('success', 'Ecommerce integration settings updated successfully.');
    }

    /**
     * Delete integration.
     */
    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $integration = CommerceIntegration::where('branch_id', $branch->id)->firstOrFail();

        // Check if there are active pending reservations
        $activePendingCount = $integration->reservations()->active()->count();
        if ($activePendingCount > 0) {
            return back()->with('error', "Cannot disconnect integration while there are {$activePendingCount} active pending inventory reservations. Please wait for them to expire or confirm them.");
        }

        DB::transaction(function () use ($integration, $branch) {
            $integration->tokens()->delete();
            $integration->delete();

            ActivityLogger::log(
                actionType: 'delete',
                message: "Removed ecommerce integration from branch {$branch->name}",
                branchId: $branch->id,
                details: ['branch_id' => $branch->id]
            );
        });

        return back()->with('success', 'Ecommerce integration disconnected successfully.');
    }
}
