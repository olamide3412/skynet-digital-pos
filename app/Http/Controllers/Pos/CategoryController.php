<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index()
    {
        $branch = current_branch();

        $categories = Category::when($branch, function ($query) use ($branch) {
            $query->where('branch_id', $branch->id)
                  ->orWhereNull('branch_id');
        })->orderBy('name')->paginate(20);

        return Inertia::render('Categories/Index', [
            'categories' => $categories
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
