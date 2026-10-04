<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();
        $search = $request->input('search');

        $categories = Category::when($branch, function ($query) use ($branch) {
            $query->where('branch_id', $branch->id)
                  ->orWhereNull('branch_id');
        })
        ->when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })
        ->withCount(['items' => function ($query) use ($branch) {
            if ($branch) {
                $query->where('branch_id', $branch->id);
            }
        }])
        ->withSum(['items as total_stock_qty' => function ($query) use ($branch) {
            if ($branch) {
                $query->where('branch_id', $branch->id);
            }
        }], 'front_store_qty')
        ->orderBy('name')
        ->paginate(25)
        ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters'    => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
        ]);

        Category::create([
            'branch_id' => $branch?->id,
            'name'      => $request->name,
            'slug'      => $request->slug ? \Str::slug($request->slug) : \Str::slug($request->name),
        ]);

        return back()->with('success', 'Category created successfully');
    }

    public function update(Request $request, $branchParam, Category $category)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => $request->slug ? \Str::slug($request->slug) : \Str::slug($request->name),
        ]);

        return back()->with('success', 'Category updated successfully');
    }

    public function destroy($branchParam, Category $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted successfully');
    }
}
