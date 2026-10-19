<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\ItemUnit;
use App\Models\Organization;
use App\Models\OrganizationActivityLog;
use App\Models\PosSettings;
use App\Models\ProductGroup;
use App\Models\ProductGroupItem;
use App\Models\ProductLinkLog;
use App\Services\ActivityLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ProductLinkingService
{
    /**
     * Get the full Product Links grid data for an organization.
     */
    public static function getGridData(Organization $organization, array $filters = []): array
    {
        $branches = $organization->branches()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'organization_id']);

        $query = ProductGroup::where('organization_id', $organization->id)
            ->with([
                'groupItems.item' => function ($q) {
                    $q->select('id', 'branch_id', 'item_name', 'barcode_number', 'unit_label', 'buy_price', 'price', 'wholesale_price', 'front_store_qty', 'back_store_qty');
                },
                'groupItems.branch:id,name,slug'
            ]);

        // Search filter
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('groupItems.item', function ($iq) use ($search) {
                        $iq->where('item_name', 'like', "%{$search}%")
                            ->orWhere('barcode_number', 'like', "%{$search}%");
                    });
            });
        }

        $allGroups = $query->latest('id')->get();
        $totalBranchesCount = $branches->count();

        // Process groups and detect conflicts / status
        $processedGroups = $allGroups->map(function (ProductGroup $group) use ($totalBranchesCount) {
            $itemsByBranch = [];
            $units = [];

            foreach ($group->groupItems as $gi) {
                if ($gi->item) {
                    $itemsByBranch[$gi->branch_id] = [
                        'group_item_id' => $gi->id,
                        'item_id' => $gi->item->id,
                        'name' => $gi->item->item_name,
                        'barcode' => $gi->item->barcode_number,
                        'unit_label' => $gi->item->unit_label ?: 'pcs',
                        'buy_price' => (float) $gi->item->buy_price,
                        'price' => (float) $gi->item->price,
                        'wholesale_price' => (float) $gi->item->wholesale_price,
                        'stock_total' => (int) ($gi->item->front_store_qty + $gi->item->back_store_qty),
                        'link_source' => $gi->link_source,
                    ];

                    $units[] = strtolower(trim($gi->item->unit_label ?: 'pcs'));
                }
            }

            $uniqueUnits = array_unique($units);
            $hasUnitConflict = count($uniqueUnits) > 1;
            $linkedCount = count($itemsByBranch);
            $isComplete = ($totalBranchesCount > 0) && ($linkedCount === $totalBranchesCount);

            return [
                'id' => $group->id,
                'name' => $group->name,
                'created_at' => $group->created_at?->toIso8601String(),
                'linked_branches' => $linkedCount,
                'total_branches' => $totalBranchesCount,
                'is_complete' => $isComplete,
                'has_unit_conflict' => $hasUnitConflict,
                'conflicting_units' => $hasUnitConflict ? array_values($uniqueUnits) : [],
                'items_by_branch' => $itemsByBranch,
            ];
        });

        // Filter status if specified
        $statusFilter = $filters['status'] ?? 'all';
        if ($statusFilter === 'conflicts') {
            $processedGroups = $processedGroups->filter(fn($g) => $g['has_unit_conflict'])->values();
        } elseif ($statusFilter === 'incomplete') {
            $processedGroups = $processedGroups->filter(fn($g) => !$g['is_complete'])->values();
        } elseif ($statusFilter === 'complete') {
            $processedGroups = $processedGroups->filter(fn($g) => $g['is_complete'])->values();
        }

        // Branch-level unlinked item counts
        $unlinkedStats = [];
        foreach ($branches as $branch) {
            $unlinkedCount = Item::where('branch_id', $branch->id)
                ->whereDoesntHave('productGroupItem')
                ->count();
            $unlinkedStats[$branch->id] = $unlinkedCount;
        }

        // Pagination
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : (int) request('per_page', 25);
        if ($perPage < 1) {
            $perPage = 25;
        }

        $page = isset($filters['page']) ? (int) $filters['page'] : (int) request('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $totalFiltered = $processedGroups->count();
        $offset = ($page - 1) * $perPage;
        $itemsForCurrentPage = $processedGroups->slice($offset, $perPage)->values();

        $paginatedGroups = new LengthAwarePaginator(
            $itemsForCurrentPage,
            $totalFiltered,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $allGroupOptions = ProductGroup::where('organization_id', $organization->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'branches' => $branches,
            'groups' => $paginatedGroups,
            'total_groups' => $allGroups->count(),
            'conflict_count' => $allGroups->filter(function ($g) {
                $units = $g->groupItems->map(fn($gi) => strtolower(trim($gi->item?->unit_label ?: 'pcs')))->unique();
                return $units->count() > 1;
            })->count(),
            'unlinked_stats' => $unlinkedStats,
            'all_group_options' => $allGroupOptions,
        ];
    }

    /**
     * Link an item to a product group.
     */
    public static function linkItem(
        int $productGroupId,
        int $branchId,
        int $itemId,
        ?int $userId = null,
        string $linkSource = 'manual'
    ): ProductGroupItem {
        return DB::transaction(function () use ($productGroupId, $branchId, $itemId, $userId, $linkSource) {
            $group = ProductGroup::findOrFail($productGroupId);
            $item = Item::findOrFail($itemId);
            $branch = Branch::findOrFail($branchId);

            if ($item->branch_id !== $branch->id) {
                throw new InvalidArgumentException("Item #{$itemId} does not belong to branch #{$branchId}.");
            }

            if ($branch->organization_id !== $group->organization_id) {
                throw new InvalidArgumentException("Branch does not belong to the organization of product group #{$productGroupId}.");
            }

            // Check: Branch already has an item linked in this group
            $existingInGroup = ProductGroupItem::where('product_group_id', $productGroupId)
                ->where('branch_id', $branchId)
                ->first();

            if ($existingInGroup) {
                if ($existingInGroup->item_id === $itemId) {
                    return $existingInGroup;
                }
                throw new RuntimeException("Branch '{$branch->name}' already has an item linked to product group '{$group->name}'.");
            }

            // Check: Item already belongs to another group
            $existingLink = ProductGroupItem::where('item_id', $itemId)->first();
            if ($existingLink) {
                if ($existingLink->product_group_id === $productGroupId) {
                    return $existingLink;
                }
                $otherGroup = ProductGroup::find($existingLink->product_group_id);
                throw new RuntimeException("Item '{$item->name}' is already linked to group '{$otherGroup?->name}'. Unlink it first.");
            }

            $groupItem = ProductGroupItem::create([
                'product_group_id' => $productGroupId,
                'branch_id' => $branchId,
                'item_id' => $itemId,
                'linked_by' => $userId,
                'link_source' => $linkSource,
            ]);

            ProductLinkLog::create([
                'organization_id' => $group->organization_id,
                'action' => 'link',
                'payload' => [
                    'product_group_id' => $productGroupId,
                    'group_name' => $group->name,
                    'branch_id' => $branchId,
                    'item_id' => $itemId,
                    'item_name' => $item->name,
                    'link_source' => $linkSource,
                ],
                'user_id' => $userId,
            ]);

            return $groupItem;
        });
    }

    /**
     * Unlink an item from its product group.
     */
    public static function unlinkItem(int $itemId, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($itemId, $userId) {
            $groupItem = ProductGroupItem::with(['productGroup', 'item', 'branch'])->where('item_id', $itemId)->first();
            if (!$groupItem) {
                return false;
            }

            $groupId = $groupItem->product_group_id;
            $orgId = $groupItem->productGroup->organization_id;
            $groupName = $groupItem->productGroup->name;
            $itemName = $groupItem->item?->name;
            $branchId = $groupItem->branch_id;

            $groupItem->delete();

            ProductLinkLog::create([
                'organization_id' => $orgId,
                'action' => 'unlink',
                'payload' => [
                    'product_group_id' => $groupId,
                    'group_name' => $groupName,
                    'branch_id' => $branchId,
                    'item_id' => $itemId,
                    'item_name' => $itemName,
                ],
                'user_id' => $userId,
            ]);

            // If group is now empty, delete group
            $remaining = ProductGroupItem::where('product_group_id', $groupId)->count();
            if ($remaining === 0) {
                ProductGroup::where('id', $groupId)->delete();
            }

            return true;
        });
    }

    /**
     * Create a new product group, optionally linking an initial item.
     */
    public static function createGroup(
        int $organizationId,
        string $name,
        ?int $userId = null,
        ?int $initialItemId = null,
        string $linkSource = 'manual'
    ): ProductGroup {
        return DB::transaction(function () use ($organizationId, $name, $userId, $initialItemId, $linkSource) {
            $group = ProductGroup::create([
                'organization_id' => $organizationId,
                'name' => trim($name),
                'created_by' => $userId,
            ]);

            ProductLinkLog::create([
                'organization_id' => $organizationId,
                'action' => 'create_group',
                'payload' => [
                    'product_group_id' => $group->id,
                    'name' => $group->name,
                ],
                'user_id' => $userId,
            ]);

            if ($initialItemId) {
                $item = Item::findOrFail($initialItemId);
                self::linkItem($group->id, $item->branch_id, $item->id, $userId, $linkSource);
            }

            return $group;
        });
    }

    /**
     * Update an existing product group's name.
     */
    public static function updateGroup(
        int $productGroupId,
        string $name,
        ?int $userId = null
    ): ProductGroup {
        return DB::transaction(function () use ($productGroupId, $name, $userId) {
            $group = ProductGroup::findOrFail($productGroupId);
            $trimmedName = trim($name);

            if ($trimmedName === '') {
                throw new InvalidArgumentException("Product group name cannot be empty.");
            }

            $oldName = $group->name;
            $group->update(['name' => $trimmedName]);

            ProductLinkLog::create([
                'organization_id' => $group->organization_id,
                'action' => 'update_group_name',
                'payload' => [
                    'product_group_id' => $group->id,
                    'old_name' => $oldName,
                    'new_name' => $trimmedName,
                ],
                'user_id' => $userId,
            ]);

            return $group;
        });
    }

    /**
     * Merge two product groups into one.
     */
    public static function mergeGroups(
        int $keepGroupId,
        int $mergeGroupId,
        ?int $userId = null,
        array $branchResolutions = []
    ): ProductGroup {
        return DB::transaction(function () use ($keepGroupId, $mergeGroupId, $userId, $branchResolutions) {
            $keepGroup = ProductGroup::with('groupItems')->findOrFail($keepGroupId);
            $mergeGroup = ProductGroup::with('groupItems')->findOrFail($mergeGroupId);

            if ($keepGroup->organization_id !== $mergeGroup->organization_id) {
                throw new InvalidArgumentException("Cannot merge groups from different organizations.");
            }

            $keepItemsByBranch = $keepGroup->groupItems->keyBy('branch_id');
            $mergeItemsByBranch = $mergeGroup->groupItems->keyBy('branch_id');

            foreach ($mergeItemsByBranch as $branchId => $mergeItem) {
                if ($keepItemsByBranch->has($branchId)) {
                    // Conflict: both groups have an item for this branch
                    $chosenItemId = $branchResolutions[$branchId] ?? null;
                    $keepItem = $keepItemsByBranch->get($branchId);

                    if ($chosenItemId && (int) $chosenItemId === (int) $mergeItem->item_id) {
                        // Keep merge item instead: remove existing keep item, reassign merge item
                        $keepItem->delete();
                        $mergeItem->update(['product_group_id' => $keepGroupId]);
                    } else {
                        // Keep the existing keepItem, drop the mergeItem from group
                        $mergeItem->delete();
                    }
                } else {
                    // No conflict for this branch, reassign to keepGroup
                    $mergeItem->update(['product_group_id' => $keepGroupId]);
                }
            }

            ProductLinkLog::create([
                'organization_id' => $keepGroup->organization_id,
                'action' => 'merge',
                'payload' => [
                    'keep_group_id' => $keepGroupId,
                    'merge_group_id' => $mergeGroupId,
                    'keep_name' => $keepGroup->name,
                    'merged_name' => $mergeGroup->name,
                ],
                'user_id' => $userId,
            ]);

            $mergeGroup->delete();

            return $keepGroup->fresh(['groupItems.item', 'groupItems.branch']);
        });
    }

    /**
     * Auto-suggest potential product links across branches.
     * Exact barcode matches are proposed first (eligible for 1-click bulk confirmation).
     * Similar name matches are proposed second (manual review required).
     */
    public static function autoSuggest(int $organizationId, int $templateBranchId): array
    {
        $organization = Organization::with('branches')->findOrFail($organizationId);
        $templateBranch = Branch::findOrFail($templateBranchId);

        $otherBranches = $organization->branches
            ->where('id', '!=', $templateBranchId)
            ->values();

        if ($otherBranches->isEmpty()) {
            return [
                'barcode_matches' => [],
                'name_matches' => [],
                'template_branch' => ['id' => $templateBranch->id, 'name' => $templateBranch->name],
            ];
        }

        $otherBranchIds = $otherBranches->pluck('id')->all();

        // Preload any existing group-branch links in this organization
        $existingGroupItems = ProductGroupItem::whereIn('branch_id', $organization->branches->pluck('id'))
            ->select('product_group_id', 'branch_id')
            ->get();
        $groupBranchMap = [];
        foreach ($existingGroupItems as $gi) {
            $groupBranchMap[$gi->product_group_id][$gi->branch_id] = true;
        }

        // 1. Template Items
        $templateItems = Item::where('branch_id', $templateBranchId)
            ->with(['productGroupItem:id,product_group_id,item_id'])
            ->get(['id', 'branch_id', 'item_name', 'barcode_number', 'unit_label']);

        // 2. Candidate Items from other branches
        $otherItems = Item::whereIn('branch_id', $otherBranchIds)
            ->with([
                'productGroupItem:id,product_group_id,item_id',
                'branch:id,name',
            ])
            ->get(['id', 'branch_id', 'item_name', 'barcode_number', 'unit_label']);

        // 3. Index unlinked candidates for O(1) hash map lookups
        $candidatesByBarcode = [];
        $candidatesByName = []; // [$branchId][$name][] = $item
        $unlinkedByBranch = [];

        foreach ($otherItems as $candidate) {
            if ($candidate->productGroupItem) {
                continue; // Already linked to a product group
            }

            $barcode = trim($candidate->barcode_number ?? '');
            if ($barcode !== '') {
                $candidatesByBarcode[$barcode][] = $candidate;
            }

            $normName = strtolower(trim($candidate->item_name));
            if ($normName !== '') {
                $candidatesByName[$candidate->branch_id][$normName][] = $candidate;
            }

            $unlinkedByBranch[$candidate->branch_id][] = $candidate;
        }

        $barcodeMatches = [];
        $nameMatches = [];
        $matchedCandidateIds = []; // candidate_item_id => true
        $matchedTemplateBranch = []; // [$templateItemId][$branchId] => true

        // Step 1: Exact Barcode Matching (O(1) lookups)
        foreach ($templateItems as $ti) {
            $existingGroupId = $ti->productGroupItem?->product_group_id;
            $barcode = trim($ti->barcode_number ?? '');

            if ($barcode !== '' && isset($candidatesByBarcode[$barcode])) {
                foreach ($candidatesByBarcode[$barcode] as $candidate) {
                    if (isset($matchedCandidateIds[$candidate->id])) {
                        continue;
                    }
                    if (!empty($matchedTemplateBranch[$ti->id][$candidate->branch_id])) {
                        continue;
                    }
                    if ($existingGroupId && !empty($groupBranchMap[$existingGroupId][$candidate->branch_id])) {
                        continue;
                    }

                    $barcodeMatches[] = [
                        'template_item_id' => $ti->id,
                        'template_item_name' => $ti->item_name,
                        'template_barcode' => $barcode,
                        'template_unit' => $ti->unit_label ?: 'pcs',
                        'product_group_id' => $existingGroupId,
                        'suggested_group_name' => $ti->item_name,
                        'target_branch_id' => $candidate->branch_id,
                        'target_branch_name' => $candidate->branch?->name,
                        'target_item_id' => $candidate->id,
                        'target_item_name' => $candidate->item_name,
                        'target_barcode' => $candidate->barcode_number,
                        'target_unit' => $candidate->unit_label ?: 'pcs',
                        'match_type' => 'barcode',
                        'confidence' => 100,
                    ];

                    $matchedCandidateIds[$candidate->id] = true;
                    $matchedTemplateBranch[$ti->id][$candidate->branch_id] = true;
                }
            }
        }

        // Step 2: Exact Name Matching (O(1) hash map lookups)
        foreach ($templateItems as $ti) {
            $existingGroupId = $ti->productGroupItem?->product_group_id;
            $barcode = trim($ti->barcode_number ?? '');
            $tiName = strtolower(trim($ti->item_name));

            if ($tiName === '') {
                continue;
            }

            foreach ($otherBranches as $branch) {
                if (!empty($matchedTemplateBranch[$ti->id][$branch->id])) {
                    continue; // Already matched for this branch
                }
                if ($existingGroupId && !empty($groupBranchMap[$existingGroupId][$branch->id])) {
                    continue;
                }

                if (isset($candidatesByName[$branch->id][$tiName])) {
                    foreach ($candidatesByName[$branch->id][$tiName] as $candidate) {
                        if (isset($matchedCandidateIds[$candidate->id])) {
                            continue;
                        }

                        $nameMatches[] = [
                            'template_item_id' => $ti->id,
                            'template_item_name' => $ti->item_name,
                            'template_barcode' => $barcode,
                            'template_unit' => $ti->unit_label ?: 'pcs',
                            'product_group_id' => $existingGroupId,
                            'suggested_group_name' => $ti->item_name,
                            'target_branch_id' => $candidate->branch_id,
                            'target_branch_name' => $candidate->branch?->name,
                            'target_item_id' => $candidate->id,
                            'target_item_name' => $candidate->item_name,
                            'target_barcode' => $candidate->barcode_number,
                            'target_unit' => $candidate->unit_label ?: 'pcs',
                            'match_type' => 'name',
                            'confidence' => 100,
                        ];

                        $matchedCandidateIds[$candidate->id] = true;
                        $matchedTemplateBranch[$ti->id][$branch->id] = true;
                        break; // One match per template item per branch
                    }
                }
            }
        }

        // Step 3: Fuzzy / Similar Name Matching on remaining unmatched items ONLY
        foreach ($otherBranches as $branch) {
            $remainingCandidates = array_filter(
                $unlinkedByBranch[$branch->id] ?? [],
                fn($c) => !isset($matchedCandidateIds[$c->id])
            );

            if (empty($remainingCandidates)) {
                continue;
            }

            $remainingTemplates = $templateItems->filter(
                fn($ti) => empty($matchedTemplateBranch[$ti->id][$branch->id]) && trim($ti->item_name) !== ''
            );

            if ($remainingTemplates->isEmpty()) {
                continue;
            }

            // Precompute string lengths & normalized names
            $candData = [];
            foreach ($remainingCandidates as $c) {
                $name = strtolower(trim($c->item_name));
                $candData[] = [
                    'item' => $c,
                    'name' => $name,
                    'len' => strlen($name),
                    'first' => $name[0] ?? '',
                    'sound' => soundex($name),
                ];
            }

            foreach ($remainingTemplates as $ti) {
                $tiName = strtolower(trim($ti->item_name));
                $tiLen = strlen($tiName);
                $tiFirst = $tiName[0] ?? '';
                $tiSound = soundex($tiName);
                $existingGroupId = $ti->productGroupItem?->product_group_id;
                $barcode = trim($ti->barcode_number ?? '');

                if ($existingGroupId && !empty($groupBranchMap[$existingGroupId][$branch->id])) {
                    continue;
                }

                foreach ($candData as &$cEntry) {
                    $c = $cEntry['item'];
                    if (isset($matchedCandidateIds[$c->id])) {
                        continue;
                    }

                    // Quick length filter: if lengths differ by more than 25%, similarity < 80%
                    if (abs($tiLen - $cEntry['len']) > max($tiLen, $cEntry['len']) * 0.25) {
                        continue;
                    }

                    // Quick phonetic/initial filter
                    if ($tiFirst !== $cEntry['first'] && $tiSound !== $cEntry['sound']) {
                        continue;
                    }

                    $similarity = 0.0;
                    similar_text($tiName, $cEntry['name'], $similarity);

                    if ($similarity >= 85) {
                        $nameMatches[] = [
                            'template_item_id' => $ti->id,
                            'template_item_name' => $ti->item_name,
                            'template_barcode' => $barcode,
                            'template_unit' => $ti->unit_label ?: 'pcs',
                            'product_group_id' => $existingGroupId,
                            'suggested_group_name' => $ti->item_name,
                            'target_branch_id' => $c->branch_id,
                            'target_branch_name' => $c->branch?->name,
                            'target_item_id' => $c->id,
                            'target_item_name' => $c->item_name,
                            'target_barcode' => $c->barcode_number,
                            'target_unit' => $c->unit_label ?: 'pcs',
                            'match_type' => 'name',
                            'confidence' => round($similarity, 1),
                        ];

                        $matchedCandidateIds[$c->id] = true;
                        $matchedTemplateBranch[$ti->id][$branch->id] = true;
                        break;
                    }
                }
                unset($cEntry);
            }
        }

        return [
            'template_branch' => ['id' => $templateBranch->id, 'name' => $templateBranch->name],
            'barcode_matches' => $barcodeMatches,
            'name_matches' => $nameMatches,
        ];
    }

    /**
     * Bulk confirm matches. Assigns a batch_id so the operation can be undone.
     */
    public static function bulkConfirm(int $organizationId, array $matches, ?int $userId = null): array
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        return DB::transaction(function () use ($organizationId, $matches, $userId) {
            $batchId = (string) Str::uuid();
            $linkedCount = 0;

            $allItemIds = [];
            foreach ($matches as $m) {
                if (!empty($m['template_item_id']))
                    $allItemIds[] = (int) $m['template_item_id'];
                if (!empty($m['target_item_id']))
                    $allItemIds[] = (int) $m['target_item_id'];
            }
            $allItemIds = array_unique($allItemIds);

            if (empty($allItemIds)) {
                return ['batch_id' => $batchId, 'linked_count' => 0];
            }

            // Preload all items and existing productGroupItem in bulk
            $items = Item::whereIn('id', $allItemIds)->get()->keyBy('id');
            $existingGroupItems = ProductGroupItem::whereIn('item_id', $allItemIds)
                ->get()
                ->keyBy('item_id');

            // Preload and track branch-group links in memory: [$groupId][$branchId] = true
            $existingGroupIds = $existingGroupItems->pluck('product_group_id')->unique()->all();
            $branchGroupMap = [];
            if (!empty($existingGroupIds)) {
                $branchGroupRecords = ProductGroupItem::whereIn('product_group_id', $existingGroupIds)
                    ->select('product_group_id', 'branch_id')
                    ->get();
                foreach ($branchGroupRecords as $r) {
                    $branchGroupMap[$r->product_group_id][$r->branch_id] = true;
                }
            }

            $targetItemsLinked = [];
            $successfulMatches = [];
            $groupItemsToInsert = [];
            $now = now()->toDateTimeString();

            // Prepare PDO statement for fast group inserts
            $pdo = DB::connection()->getPdo();
            $insertGroupStmt = $pdo->prepare(
                "INSERT INTO product_groups (organization_id, name, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?)"
            );

            // Track template items to group ID mapping
            $templateGroupIdMap = [];
            foreach ($existingGroupItems as $itemId => $gi) {
                $templateGroupIdMap[$itemId] = $gi->product_group_id;
            }

            foreach ($matches as $match) {
                $templateItemId = (int) ($match['template_item_id'] ?? 0);
                $targetItemId = (int) ($match['target_item_id'] ?? 0);
                $targetBranchId = (int) ($match['target_branch_id'] ?? 0);
                $matchType = $match['match_type'] ?? 'auto_barcode';

                if (!$templateItemId || !$targetItemId || !$targetBranchId) {
                    continue;
                }

                $templateItem = $items->get($templateItemId);
                $targetItem = $items->get($targetItemId);

                if (!$templateItem || !$targetItem) {
                    continue;
                }

                // If target item is already linked, skip
                if ($existingGroupItems->has($targetItemId) || isset($targetItemsLinked[$targetItemId])) {
                    continue;
                }

                // Find or create group for template item
                if (isset($templateGroupIdMap[$templateItemId])) {
                    $groupId = $templateGroupIdMap[$templateItemId];
                } else {
                    $insertGroupStmt->execute([
                        $organizationId,
                        $templateItem->item_name,
                        $userId,
                        $now,
                        $now,
                    ]);
                    $groupId = (int) $pdo->lastInsertId();
                    $templateGroupIdMap[$templateItemId] = $groupId;

                    // Template item group link
                    $groupItemsToInsert[] = [
                        'product_group_id' => $groupId,
                        'branch_id' => $templateItem->branch_id,
                        'item_id' => $templateItem->id,
                        'linked_by' => $userId,
                        'link_source' => 'auto_template',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $branchGroupMap[$groupId][$templateItem->branch_id] = true;
                }

                // Check if target branch already has an item linked to this group
                if (!empty($branchGroupMap[$groupId][$targetBranchId])) {
                    continue;
                }

                $groupItemsToInsert[] = [
                    'product_group_id' => $groupId,
                    'branch_id' => $targetBranchId,
                    'item_id' => $targetItemId,
                    'linked_by' => $userId,
                    'link_source' => $matchType === 'barcode' ? 'auto_barcode' : 'auto_name_confirmed',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $targetItemsLinked[$targetItemId] = true;
                $branchGroupMap[$groupId][$targetBranchId] = true;
                $linkedCount++;

                $successfulMatches[] = [
                    'template_item_id' => $templateItemId,
                    'target_item_id' => $targetItemId,
                    'target_branch_id' => $targetBranchId,
                    'product_group_id' => $groupId,
                ];
            }

            // Chunked batch insert for all product_group_items
            foreach (array_chunk($groupItemsToInsert, 500) as $chunk) {
                ProductGroupItem::insert($chunk);
            }

            ProductLinkLog::create([
                'organization_id' => $organizationId,
                'action' => 'bulk_confirm',
                'batch_id' => $batchId,
                'payload' => [
                    'batch_id' => $batchId,
                    'linked_count' => $linkedCount,
                    'matches' => $successfulMatches,
                ],
                'user_id' => $userId,
            ]);

            // Determine branch for logging (template branch or first branch in organization)
            $logBranchId = null;
            if (!empty($matches[0]['template_item_id'])) {
                $firstTemplate = $items->get((int) $matches[0]['template_item_id']);
                $logBranchId = $firstTemplate?->branch_id;
            }
            if (!$logBranchId && !empty($matches[0]['target_branch_id'])) {
                $logBranchId = (int) $matches[0]['target_branch_id'];
            }
            if (!$logBranchId) {
                $logBranchId = Branch::where('organization_id', $organizationId)->value('id');
            }

            // 1. Log to PosLog (viewable in SuperAdmin Logs and Org Activity Logs)
            ActivityLogger::log(
                'bulk_link',
                "Auto-suggest bulk link confirmed: linked {$linkedCount} items across branches. Batch UUID: {$batchId}",
                $logBranchId,
                [
                    'batch_id'           => $batchId,
                    'linked_count'       => $linkedCount,
                    'organization_id'   => $organizationId,
                    'template_branch_id' => $logBranchId,
                ],
                $userId
            );

            // 2. Log to OrganizationActivityLog (viewable on SuperAdmin Org details page)
            OrganizationActivityLog::create([
                'organization_id' => $organizationId,
                'actor_user_id'   => $userId ?? Auth::id(),
                'action'          => 'bulk_product_link_confirmed',
                'details'         => [
                    'batch_id'     => $batchId,
                    'linked_count' => $linkedCount,
                    'branch_id'    => $logBranchId,
                ],
                'created_at'      => now(),
            ]);

            return [
                'batch_id' => $batchId,
                'linked_count' => $linkedCount,
            ];
        });
    }

    /**
     * Undo a bulk link batch.
     */
    public static function undoBatch(int $organizationId, string $batchId, ?int $userId = null): int
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        return DB::transaction(function () use ($organizationId, $batchId, $userId) {
            $alreadyUndone = ProductLinkLog::where('organization_id', $organizationId)
                ->where('batch_id', $batchId)
                ->where('action', 'undo_batch')
                ->exists();
            if ($alreadyUndone) {
                throw new \Exception("Batch {$batchId} has already been undone.");
            }

            $log = ProductLinkLog::where('organization_id', $organizationId)
                ->where('batch_id', $batchId)
                ->where('action', 'bulk_confirm')
                ->firstOrFail();

            $matches = $log->payload['matches'] ?? [];
            $unlinkedCount = 0;
            $affectedGroupIds = [];

            $targetItemIds = [];
            foreach ($matches as $match) {
                if (!empty($match['target_item_id'])) {
                    $targetItemIds[] = (int) $match['target_item_id'];
                }
            }

            if (!empty($targetItemIds)) {
                $groupItems = ProductGroupItem::whereIn('item_id', $targetItemIds)->get();
                foreach ($groupItems as $gi) {
                    $affectedGroupIds[] = $gi->product_group_id;
                }
                $unlinkedCount = ProductGroupItem::whereIn('item_id', $targetItemIds)->delete();
            }

            // Clean up any empty groups
            $uniqueGroupIds = array_values(array_unique(array_filter($affectedGroupIds)));
            if (!empty($uniqueGroupIds)) {
                $nonEmptyGroupIds = ProductGroupItem::whereIn('product_group_id', $uniqueGroupIds)
                    ->pluck('product_group_id')
                    ->unique()
                    ->all();
                $emptyGroupIds = array_diff($uniqueGroupIds, $nonEmptyGroupIds);
                if (!empty($emptyGroupIds)) {
                    ProductGroup::whereIn('id', $emptyGroupIds)->delete();
                }
            }

            ProductLinkLog::create([
                'organization_id' => $organizationId,
                'action' => 'undo_batch',
                'batch_id' => $batchId,
                'payload' => [
                    'batch_id' => $batchId,
                    'unlinked_count' => $unlinkedCount,
                ],
                'user_id' => $userId,
            ]);

            $logBranchId = Branch::where('organization_id', $organizationId)->value('id');

            // 1. Log to PosLog (viewable in SuperAdmin Logs and Org Activity Logs)
            ActivityLogger::log(
                'undo_batch',
                "Bulk link batch reverted: unlinked {$unlinkedCount} items. Batch UUID: {$batchId}",
                $logBranchId,
                [
                    'batch_id'        => $batchId,
                    'unlinked_count'  => $unlinkedCount,
                    'organization_id' => $organizationId,
                ],
                $userId
            );

            // 2. Log to OrganizationActivityLog (viewable on SuperAdmin Org details page)
            OrganizationActivityLog::create([
                'organization_id' => $organizationId,
                'actor_user_id'   => $userId ?? Auth::id(),
                'action'          => 'bulk_product_link_undone',
                'details'         => [
                    'batch_id'       => $batchId,
                    'unlinked_count' => $unlinkedCount,
                ],
                'created_at'      => now(),
            ]);

            return $unlinkedCount;
        });
    }

    /**
     * Get recent bulk linking batches for an organization.
     */
    public static function getRecentBatches(int $organizationId, int $limit = 10): array
    {
        $undoneBatchIds = ProductLinkLog::where('organization_id', $organizationId)
            ->where('action', 'undo_batch')
            ->pluck('batch_id')
            ->filter()
            ->all();

        return ProductLinkLog::with('user:id,name,username')
            ->where('organization_id', $organizationId)
            ->where('action', 'bulk_confirm')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function ($log) use ($undoneBatchIds) {
                return [
                    'id'               => $log->id,
                    'batch_id'         => $log->batch_id,
                    'linked_count'     => (int) ($log->payload['linked_count'] ?? 0),
                    'is_undone'        => in_array($log->batch_id, $undoneBatchIds, true),
                    'created_at'       => $log->created_at?->toIso8601String(),
                    'created_at_human' => $log->created_at?->diffForHumans(),
                    'user_name'        => $log->user?->name ?? 'System',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Copy an item into other branches and link them into its Product Group in one step.
     * Confirmed requirement: "Include prices" (default true), copied once, not synced.
     * If destination branch uses profit percentage, copy cost and let profit percentage compute selling price.
     */
    public static function copyToBranches(
        int $sourceItemId,
        array $targetBranchIds,
        bool $includePrices = true,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($sourceItemId, $targetBranchIds, $includePrices, $userId) {
            $sourceItem = Item::with(['productGroupItem'])->findOrFail($sourceItemId);
            $sourceBranch = Branch::findOrFail($sourceItem->branch_id);
            $organizationId = $sourceBranch->organization_id;

            // Ensure source item has a product group
            if ($sourceItem->productGroupItem) {
                $productGroupId = $sourceItem->productGroupItem->product_group_id;
            } else {
                $group = ProductGroup::create([
                    'organization_id' => $organizationId,
                    'name' => $sourceItem->item_name,
                    'created_by' => $userId,
                ]);
                $productGroupId = $group->id;

                ProductGroupItem::create([
                    'product_group_id' => $productGroupId,
                    'branch_id' => $sourceBranch->id,
                    'item_id' => $sourceItem->id,
                    'linked_by' => $userId,
                    'link_source' => 'manual',
                ]);
            }

            $createdItems = [];

            foreach ($targetBranchIds as $targetBranchId) {
                $targetBranchId = (int) $targetBranchId;
                if ($targetBranchId === $sourceBranch->id) {
                    continue;
                }

                $targetBranch = Branch::findOrFail($targetBranchId);
                if ($targetBranch->organization_id !== $organizationId) {
                    continue;
                }

                // Check if target branch already has an item in this group
                $alreadyLinked = ProductGroupItem::where('product_group_id', $productGroupId)
                    ->where('branch_id', $targetBranchId)
                    ->exists();

                if ($alreadyLinked) {
                    continue;
                }

                // Find or create category in destination branch
                $targetCategoryId = null;
                if ($sourceItem->category_id) {
                    $sourceCategory = Category::find($sourceItem->category_id);
                    if ($sourceCategory && !empty($sourceCategory->name)) {
                        $targetCategory = Category::firstOrCreate([
                            'branch_id' => $targetBranchId,
                            'name' => $sourceCategory->name,
                        ], [
                            'slug' => $sourceCategory->slug ? $sourceCategory->slug . '-' . $targetBranchId : Str::slug($sourceCategory->name) . '-' . $targetBranchId,
                            'visible_in_menu' => $sourceCategory->visible_in_menu ?? true,
                        ]);
                        $targetCategoryId = $targetCategory->id;
                    }
                }

                // If target branch already has an item with this barcode or name, link it instead of creating duplicate
                $existingItem = null;
                if (!empty($sourceItem->barcode_number)) {
                    $existingItem = Item::where('branch_id', $targetBranchId)
                        ->where('barcode_number', $sourceItem->barcode_number)
                        ->first();
                }

                // If not matched by barcode, check if target branch already has an item with matching name
                if (!$existingItem) {
                    $trimmedName = strtolower(trim($sourceItem->item_name ?? ''));
                    if ($trimmedName !== '') {
                        $existingItem = Item::where('branch_id', $targetBranchId)
                            ->whereRaw('LOWER(TRIM(item_name)) = ?', [$trimmedName])
                            ->first();
                    }
                }

                if ($existingItem) {
                    $existingPgi = ProductGroupItem::where('item_id', $existingItem->id)->first();
                    if ($existingPgi) {
                        if ($existingPgi->product_group_id !== $productGroupId) {
                            self::mergeGroups($productGroupId, $existingPgi->product_group_id, $userId);
                        }
                    } else {
                        $branchInGroup = ProductGroupItem::where('product_group_id', $productGroupId)->where('branch_id', $targetBranchId)->first();
                        if (!$branchInGroup) {
                            ProductGroupItem::create([
                                'product_group_id' => $productGroupId,
                                'branch_id'        => $targetBranchId,
                                'item_id'          => $existingItem->id,
                                'linked_by'        => $userId,
                                'link_source'      => 'copy_match',
                            ]);
                        }
                    }

                    $createdItems[] = [
                        'branch_id' => $targetBranchId,
                        'item_id'   => $existingItem->id,
                        'name'      => $existingItem->item_name,
                    ];
                    continue;
                }

                $targetSettings = PosSettings::forBranch($targetBranchId);
                $buyPrice = $includePrices ? (float) ($sourceItem->buy_price ?? 0) : 0.0;
                $sellingPrice = 0.0;
                $wholesalePrice = 0.0;

                if ($includePrices) {
                    if ($targetSettings->is_use_profit_percentage) {
                        $consumerPct = (float) ($targetSettings->consumer_profit_percent ?? 15);
                        $wholesalePct = (float) ($targetSettings->wholesale_profit_percent ?? 10);
                        $sellingPrice = round($buyPrice * (1 + $consumerPct / 100), 2);
                        $wholesalePrice = round($buyPrice * (1 + $wholesalePct / 100), 2);
                    } else {
                        $sellingPrice = (float) ($sourceItem->price ?? 0);
                        $wholesalePrice = (float) ($sourceItem->wholesale_price ?? 0);
                    }
                }

                // Create new item in target branch (quantities 0)
                $newItem = Item::create([
                    'branch_id' => $targetBranchId,
                    'category_id' => $targetCategoryId,
                    'item_name' => $sourceItem->item_name,
                    'barcode_number' => $sourceItem->barcode_number,
                    'buy_price' => $buyPrice,
                    'price' => $sellingPrice,
                    'wholesale_price' => $wholesalePrice,
                    'unit_label' => $sourceItem->unit_label ?: 'pcs',
                    'front_store_qty' => 0,
                    'back_store_qty' => 0,
                    'is_imei_tracked' => (bool) $sourceItem->is_imei_tracked,
                    'batch_tracking_enabled' => (bool) $sourceItem->batch_tracking_enabled,
                    'item_description' => $sourceItem->item_description,
                ]);

                // Copy additional item units if any
                $sourceUnits = ItemUnit::where('item_id', $sourceItem->id)->get();
                foreach ($sourceUnits as $su) {
                    ItemUnit::create([
                        'item_id' => $newItem->id,
                        'branch_id' => $targetBranchId,
                        'unit_name' => $su->unit_name,
                        'quantity_in_base_units' => $su->quantity_in_base_units,
                        'selling_price' => $includePrices ? $su->selling_price : 0,
                        'wholesale_price' => $includePrices ? $su->wholesale_price : 0,
                        'sort_order' => $su->sort_order,
                        'status' => $su->status,
                    ]);
                }

                // Link to product group
                ProductGroupItem::create([
                    'product_group_id' => $productGroupId,
                    'branch_id' => $targetBranchId,
                    'item_id' => $newItem->id,
                    'linked_by' => $userId,
                    'link_source' => 'copy',
                ]);

                $createdItems[] = [
                    'branch_id' => $targetBranchId,
                    'item_id' => $newItem->id,
                    'name' => $newItem->item_name,
                ];
            }

            ProductLinkLog::create([
                'organization_id' => $organizationId,
                'action' => 'copy_to_branches',
                'payload' => [
                    'source_item_id' => $sourceItemId,
                    'product_group_id' => $productGroupId,
                    'created_items' => $createdItems,
                    'include_prices' => $includePrices,
                ],
                'user_id' => $userId,
            ]);

            return [
                'product_group_id' => $productGroupId,
                'created_items' => $createdItems,
            ];
        });
    }

    /**
     * Get items in a branch that are not yet linked to any product group.
     */
    public static function getUnlinkedItems(int $branchId, ?string $search = null, int $limit = 25): array
    {
        $query = Item::where('branch_id', $branchId)
            ->whereDoesntHave('productGroupItem')
            ->select('id', 'branch_id', 'item_name', 'barcode_number', 'unit_label', 'buy_price', 'price', 'wholesale_price', 'front_store_qty', 'back_store_qty');

        if ($search !== null && trim($search) !== '') {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->where('item_name', 'like', "%{$s}%")
                    ->orWhere('barcode_number', 'like', "%{$s}%");
            });
        }

        $effectiveLimit = max(1, min($limit, 50));

        return $query->orderBy('item_name')->limit($effectiveLimit)->get()->toArray();
    }
}
