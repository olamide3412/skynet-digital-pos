<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Item;
use App\Models\Organization;
use App\Models\ProductGroup;
use App\Services\ProductLinkingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductLinkController extends Controller
{
    /**
     * Display the organization-wide Product Links grid.
     */
    public function index(Request $request, Organization $organization): Response
    {
        $filters = [
            'search'   => $request->query('search'),
            'status'   => $request->query('status', 'all'), // all, incomplete, complete, conflicts
            'per_page' => (int) $request->query('per_page', 25),
        ];

        $gridData = ProductLinkingService::getGridData($organization, $filters);

        return Inertia::render('SuperAdmin/Organizations/ProductLinks', [
            'organization'    => $organization->only('id', 'name', 'status'),
            'branches'        => $gridData['branches'],
            'groups'          => $gridData['groups'],
            'totalGroups'     => $gridData['total_groups'],
            'conflictCount'   => $gridData['conflict_count'],
            'unlinkedStats'   => $gridData['unlinked_stats'],
            'allGroupOptions' => $gridData['all_group_options'] ?? [],
            'filters'         => $filters,
        ]);
    }

    /**
     * Link an existing item in a branch to a product group.
     */
    public function link(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'product_group_id' => 'required|integer|exists:product_groups,id',
            'branch_id'        => 'required|integer|exists:branches,id',
            'item_id'          => 'required|integer|exists:items,id',
            'link_source'      => 'nullable|string|in:manual,auto_barcode,auto_name_confirmed,copy,receiving_queue',
        ]);

        try {
            ProductLinkingService::linkItem(
                (int) $data['product_group_id'],
                (int) $data['branch_id'],
                (int) $data['item_id'],
                $request->user()?->id,
                $data['link_source'] ?? 'manual'
            );

            return back()->with('success', 'Item successfully linked to product group.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Unlink an item from its product group.
     */
    public function unlink(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => 'required|integer|exists:items,id',
        ]);

        try {
            ProductLinkingService::unlinkItem((int) $data['item_id'], $request->user()?->id);
            return back()->with('success', 'Item unlinked successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Create a new product group (with optional starting item).
     */
    public function createGroup(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'initial_item_id' => 'nullable|integer|exists:items,id',
        ]);

        try {
            ProductLinkingService::createGroup(
                $organization->id,
                $data['name'],
                $request->user()?->id,
                $data['initial_item_id'] ?? null
            );

            return back()->with('success', "Product group '{$data['name']}' created successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Update an existing product group's name.
     */
    public function updateGroup(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'product_group_id' => 'required|integer|exists:product_groups,id',
            'name'             => 'required|string|max:255',
        ]);

        try {
            $group = ProductGroup::where('organization_id', $organization->id)
                ->findOrFail((int) $data['product_group_id']);

            ProductLinkingService::updateGroup(
                $group->id,
                $data['name'],
                $request->user()?->id
            );

            return back()->with('success', "Product group renamed to '{$data['name']}' successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Merge two product groups.
     */
    public function merge(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'keep_group_id'      => 'required|integer|exists:product_groups,id',
            'merge_group_id'     => 'required|integer|exists:product_groups,id|different:keep_group_id',
            'branch_resolutions' => 'nullable|array',
        ]);

        try {
            ProductLinkingService::mergeGroups(
                (int) $data['keep_group_id'],
                (int) $data['merge_group_id'],
                $request->user()?->id,
                $data['branch_resolutions'] ?? []
            );

            return back()->with('success', 'Product groups merged successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Auto-suggest links across branches based on a template branch.
     */
    public function autoSuggest(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'template_branch_id' => 'required|integer|exists:branches,id',
        ]);

        $suggestions = ProductLinkingService::autoSuggest($organization->id, (int) $data['template_branch_id']);

        return response()->json($suggestions);
    }

    /**
     * Bulk-confirm auto-suggested matches.
     */
    public function bulkConfirm(Request $request, Organization $organization): RedirectResponse
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $data = $request->validate([
            'matches' => 'required|array|min:1',
            'matches.*.template_item_id' => 'required|integer',
            'matches.*.target_item_id'   => 'required|integer',
            'matches.*.target_branch_id' => 'required|integer',
            'matches.*.match_type'       => 'nullable|string',
        ]);

        try {
            $result = ProductLinkingService::bulkConfirm(
                $organization->id,
                $data['matches'],
                $request->user()?->id
            );

            return back()->with('success', "Successfully linked {$result['linked_count']} items across branches (Batch: {$result['batch_id']}).");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Undo a bulk link batch.
     */
    public function undoBatch(Request $request, Organization $organization): RedirectResponse
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $data = $request->validate([
            'batch_id' => 'required|string',
        ]);

        try {
            $unlinkedCount = ProductLinkingService::undoBatch(
                $organization->id,
                $data['batch_id'],
                $request->user()?->id
            );

            return back()->with('success', "Undid batch {$data['batch_id']}: removed {$unlinkedCount} links.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Copy an item into other branches with prices (one-time copy) and link it.
     */
    public function copyToBranches(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'source_item_id'    => 'required|integer|exists:items,id',
            'target_branch_ids' => 'required|array|min:1',
            'target_branch_ids.*' => 'integer|exists:branches,id',
            'include_prices'    => 'nullable|boolean',
        ]);

        try {
            $result = ProductLinkingService::copyToBranches(
                (int) $data['source_item_id'],
                $data['target_branch_ids'],
                $request->boolean('include_prices', true),
                $request->user()?->id
            );

            $count = count($result['created_items'] ?? []);
            if ($count === 0) {
                return back()->with('warning', 'No new items were copied (selected branch(es) may already have an item linked to this group).');
            }

            return back()->with('success', "Created and linked {$count} item(s) in destination branches.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Get unlinked items in a branch (for the link modal picker).
     */
    public function unlinkedItems(Request $request, Organization $organization, $branch): JsonResponse
    {
        $branchModel = $branch instanceof Branch
            ? $branch
            : (is_numeric($branch) ? Branch::findOrFail((int) $branch) : Branch::where('slug', $branch)->firstOrFail());

        if ($branchModel->organization_id !== $organization->id) {
            return response()->json([], 403);
        }

        $limit = min((int) $request->query('limit', 25), 50);
        $items = ProductLinkingService::getUnlinkedItems($branchModel->id, $request->query('search'), $limit);

        return response()->json($items);
    }
}
