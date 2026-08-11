<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReorderPointController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();

        $query = Item::where('branch_id', $branch->id)
            ->with(['category:id,name'])
            ->orderBy('item_name');

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by keyword
        if ($request->filled('search')) {
            $search = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', $search)
                  ->orWhere('barcode_number', 'like', $search);
            });
        }

        $allItems = $query->get();

        // Calculate summary counts
        $totalCount    = $allItems->count();
        $reorderItems  = $allItems->filter(fn($item) => $item->needs_reorder);
        $reorderCount  = $reorderItems->count();
        $criticalCount = $allItems->filter(fn($item) => ((int) $item->front_store_qty + (int) $item->back_store_qty) <= 0)->count();
        $adequateCount = $totalCount - $reorderCount;

        // Apply Tab Filter
        $tab = $request->get('tab', 'reorder'); // Default to showing reorder needed items
        $filteredCollection = match($tab) {
            'reorder'  => $allItems->filter(fn($item) => $item->needs_reorder),
            'critical' => $allItems->filter(fn($item) => ((int) $item->front_store_qty + (int) $item->back_store_qty) <= 0),
            'adequate' => $allItems->filter(fn($item) => !$item->needs_reorder),
            default    => $allItems,
        };

        // Paginate manually / slice collection
        $page     = (int) $request->get('page', 1);
        $perPage  = 25;
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $filteredCollection->forPage($page, $perPage)->values(),
            $filteredCollection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $categories = Category::where(fn($q) => $q->where('branch_id', $branch->id)->orWhereNull('branch_id'))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Inventory/ReorderPoints', [
            'items'         => $paginated,
            'summary'       => [
                'total_count'    => $totalCount,
                'reorder_count'  => $reorderCount,
                'critical_count' => $criticalCount,
                'adequate_count' => $adequateCount,
            ],
            'categories'    => $categories,
            'filters'       => [
                'tab'         => $tab,
                'category_id' => $request->get('category_id', ''),
                'search'      => $request->get('search', ''),
            ],
        ]);
    }

    public function update(Request $request, $branchParam, Item $item)
    {
        $branch = current_branch();
        if ($item->branch_id !== $branch->id) {
            abort(403);
        }

        $data = $request->validate([
            'reorder_point' => 'required|integer|min:0',
            'reorder_unit'  => 'required|in:unit,pack,carton',
        ]);

        $item->update($data);

        ActivityLogger::stock(
            "Updated reorder point for '{$item->item_name}' to {$data['reorder_point']} {$data['reorder_unit']}(s)",
            $branch->id,
            [
                'item_id'       => $item->id,
                'reorder_point' => $data['reorder_point'],
                'reorder_unit'  => $data['reorder_unit'],
            ]
        );

        return back()->with('success', "Reorder point for '{$item->item_name}' updated successfully.");
    }
}
