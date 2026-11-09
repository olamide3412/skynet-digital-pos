<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\GlobalItem;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BranchItemController extends Controller
{
    public function index(Request $request, Branch $branch)
    {
        // Branch items
        $branchItems = Item::where('branch_id', $branch->id)
            ->with(['category', 'brand:id,name', 'ecommerceLinks:id,pos_item_id,is_active'])
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sq) use ($request) {
                    $sq->where('item_name', 'like', '%' . $request->search . '%')
                       ->orWhere('barcode_number', 'like', '%' . $request->search . '%');
                });
            })
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
            'brand_hint'       => $gi->brand_hint,
            'is_imported'      => in_array($gi->barcode_number, $importedBarcodes),
        ]);

        $categories = Category::where('branch_id', $branch->id)->orWhereNull('branch_id')->orderBy('name')->get(['id', 'name']);
        $brands     = Brand::where('branch_id', $branch->id)->orWhereNull('branch_id')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Branches/Items', [
            'branch'       => $branch,
            'items'        => $branchItems,
            'globalItems'  => $globalItems,
            'categories'   => $categories,
            'brands'       => $brands,
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

        DB::beginTransaction();
        try {
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

                // Find or create matching branch category by category_hint (scoped strictly to target branch)
                $categoryId = null;
                if ($gItem->category_hint) {
                    $category = Category::firstOrCreate([
                        'name'      => $gItem->category_hint,
                        'branch_id' => $branch->id,
                    ], [
                        'name'      => $gItem->category_hint,
                        'slug'      => Str::slug($gItem->category_hint) . '-' . $branch->id . '-' . rand(100, 999),
                        'branch_id' => $branch->id,
                    ]);
                    $categoryId = $category->id;
                }

                // Find or create matching branch brand by brand_hint (scoped strictly to target branch)
                $brandId = null;
                if ($gItem->brand_hint) {
                    $brand = Brand::firstOrCreate([
                        'name'      => $gItem->brand_hint,
                        'branch_id' => $branch->id,
                    ], [
                        'name'      => $gItem->brand_hint,
                        'slug'      => Str::slug($gItem->brand_hint) . '-' . $branch->id . '-' . rand(100, 999),
                        'branch_id' => $branch->id,
                        'is_active' => true,
                    ]);
                    $brandId = $brand->id;
                }

                $gItem->importToBranch($branch, $categoryId, $brandId);
                $importedCount++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Import batch failed and was rolled back: ' . $e->getMessage());
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
        $categoryCache = [];
        $brandCache = [];

        DB::beginTransaction();
        try {
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
                    $cHint = trim($gItem->category_hint);
                    if (!isset($categoryCache[$cHint])) {
                        $category = Category::firstOrCreate([
                            'name'      => $cHint,
                            'branch_id' => $branch->id,
                        ], [
                            'name'      => $cHint,
                            'slug'      => Str::slug($cHint) . '-' . $branch->id . '-' . rand(100, 999),
                            'branch_id' => $branch->id,
                        ]);
                        $categoryCache[$cHint] = $category->id;
                    }
                    $categoryId = $categoryCache[$cHint];
                }

                $brandId = null;
                if ($gItem->brand_hint) {
                    $bHint = trim($gItem->brand_hint);
                    if (!isset($brandCache[$bHint])) {
                        $brand = Brand::firstOrCreate([
                            'name'      => $bHint,
                            'branch_id' => $branch->id,
                        ], [
                            'name'      => $bHint,
                            'slug'      => Str::slug($bHint) . '-' . $branch->id . '-' . rand(100, 999),
                            'branch_id' => $branch->id,
                            'is_active' => true,
                        ]);
                        $brandCache[$bHint] = $brand->id;
                    }
                    $brandId = $brandCache[$bHint];
                }

                $gItem->importToBranch($branch, $categoryId, $brandId);
                $importedCount++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Import all failed and was rolled back: ' . $e->getMessage());
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

        if ($item->isLinkedToEcommerce()) {
            return redirect()
                ->route('superadmin.branches.items.index', $branch->slug)
                ->with('error', "Cannot delete item '{$item->name}'. It is currently linked to an active product in your online Ecommerce store. Please unlink it from Ecommerce first.");
        }

        try {
            $item->delete();
        } catch (\Throwable $e) {
            return redirect()
                ->route('superadmin.branches.items.index', $branch->slug)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('superadmin.branches.items.index', $branch->slug)
            ->with('success', 'Item removed from branch catalog.');
    }
}
