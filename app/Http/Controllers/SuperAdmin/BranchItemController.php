<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\GlobalItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BranchItemController extends Controller
{
    public function index(Request $request, Branch $branch)
    {
        // Branch items
        $branchItems = Item::where('branch_id', $branch->id)
            ->with('category')
            ->when($request->search, fn($q) => $q
                ->where('item_name', 'like', '%' . $request->search . '%')
                ->orWhere('barcode_number', 'like', '%' . $request->search . '%')
            )
            ->latest()
            ->paginate(25)
            ->withQueryString();

        // Get barcodes of items already imported into this branch
        $importedBarcodes = Item::where('branch_id', $branch->id)
            ->whereNotNull('barcode_number')
            ->pluck('barcode_number')
            ->toArray();

        // Global master items available for import
        $globalItems = GlobalItem::all()->map(fn (GlobalItem $gi) => [
            'id'               => $gi->id,
            'item_name'        => $gi->item_name,
            'barcode_number'   => $gi->barcode_number,
            'buy_price'        => $gi->buy_price,
            'price'            => $gi->price,
            'category_hint'    => $gi->category_hint,
            'is_imported'      => in_array($gi->barcode_number, $importedBarcodes),
        ]);

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Branches/Items', [
            'branch'       => $branch,
            'items'        => $branchItems,
            'globalItems'  => $globalItems,
            'categories'   => $categories,
            'filters'      => $request->only('search'),
        ]);
    }

    /**
     * Import selected global items into the branch catalog.
     */
    public function importBatch(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'global_item_ids'   => 'required|array|min:1',
            'global_item_ids.*' => 'exists:global_items,id',
        ]);

        $globalItems = GlobalItem::whereIn('id', $data['global_item_ids'])->get();
        $importedCount = 0;
        $skippedCount = 0;

        foreach ($globalItems as $gItem) {
            // Check if item already exists in branch (by barcode OR exact item name)
            $exists = Item::where('branch_id', $branch->id)
                ->where(function ($q) use ($gItem) {
                    if ($gItem->barcode_number) {
                        $q->where('barcode_number', $gItem->barcode_number);
                    }
                    $q->orWhere('item_name', $gItem->item_name);
                })
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            // Find or create matching branch category by category_hint
            $categoryId = null;
            if ($gItem->category_hint) {
                $category = Category::firstOrCreate([
                    'slug' => Str::slug($gItem->category_hint),
                ], [
                    'name' => $gItem->category_hint,
                    'slug' => Str::slug($gItem->category_hint),
                ]);
                $categoryId = $category->id;
            }

            $gItem->importToBranch($branch, $categoryId);
            $importedCount++;
        }

        $message = "Imported {$importedCount} new item(s) into {$branch->name}.";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} duplicate item(s) skipped automatically).";
        }

        return redirect()
            ->route('superadmin.branches.items.index', $branch->slug)
            ->with('success', $message);
    }

    /**
     * Import ALL global master items into the branch.
     */
    public function importAll(Branch $branch)
    {
        $globalItems = GlobalItem::all();
        $importedCount = 0;
        $skippedCount = 0;

        foreach ($globalItems as $gItem) {
            $exists = Item::where('branch_id', $branch->id)
                ->where(function ($q) use ($gItem) {
                    if ($gItem->barcode_number) {
                        $q->where('barcode_number', $gItem->barcode_number);
                    }
                    $q->orWhere('item_name', $gItem->item_name);
                })
                ->exists();

            if ($exists) {
                $skippedCount++;
                continue;
            }

            $categoryId = null;
            if ($gItem->category_hint) {
                $category = Category::firstOrCreate([
                    'slug' => Str::slug($gItem->category_hint),
                ], [
                    'name' => $gItem->category_hint,
                    'slug' => Str::slug($gItem->category_hint),
                ]);
                $categoryId = $category->id;
            }

            $gItem->importToBranch($branch, $categoryId);
            $importedCount++;
        }

        $message = "Imported {$importedCount} master items into {$branch->name}.";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} existing items skipped).";
        }

        return redirect()
            ->route('superadmin.branches.items.index', $branch->slug)
            ->with('success', $message);
    }

    /**
     * Remove an item from the branch catalog.
     */
    public function destroy(Branch $branch, Item $item)
    {
        if ($item->branch_id !== $branch->id) {
            abort(403, 'Item does not belong to this branch.');
        }

        $item->delete();

        return redirect()
            ->route('superadmin.branches.items.index', $branch->slug)
            ->with('success', 'Item removed from branch catalog.');
    }
}
