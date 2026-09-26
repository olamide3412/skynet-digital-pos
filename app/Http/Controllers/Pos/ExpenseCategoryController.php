<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    /**
     * Store a custom expense category for this branch.
     */
    public function store(Request $request, $branchParam = null)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch) {
            abort(404, 'Branch not found.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $name = trim($validated['name']);

        // Check if category already exists globally or in this branch
        $exists = ExpenseCategory::where(function ($q) use ($branch) {
            $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
        })->where('name', $name)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "An expense category named '{$name}' already exists.",
            ]);
        }

        $category = ExpenseCategory::create([
            'branch_id' => $branch->id,
            'name'      => $name,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'category' => $category,
                'message'  => 'Expense category created.',
            ]);
        }

        return back()->with('success', "Expense category '{$category->name}' created.");
    }

    /**
     * Update a custom expense category.
     */
    public function update(Request $request, $branchParam, ExpenseCategory $category)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $category->branch_id !== $branch->id) {
            abort(403, 'Cannot edit global or other branch categories.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $name = trim($validated['name']);

        $exists = ExpenseCategory::where(function ($q) use ($branch) {
            $q->whereNull('branch_id')->orWhere('branch_id', $branch->id);
        })->where('name', $name)->where('id', '!=', $category->id)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "An expense category named '{$name}' already exists.",
            ]);
        }

        $category->update(['name' => $name]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'category' => $category,
                'message'  => 'Expense category updated.',
            ]);
        }

        return back()->with('success', 'Expense category updated.');
    }

    /**
     * Delete a custom expense category.
     */
    public function destroy(Request $request, $branchParam, ExpenseCategory $category)
    {
        $branch = current_branch() ?? ($branchParam ? Branch::where('slug', $branchParam)->firstOrFail() : null);
        if (!$branch || $category->branch_id !== $branch->id) {
            abort(403, 'Cannot delete global or other branch categories.');
        }

        if ($category->expenses()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete this category because it has expenses linked to it.']);
        }

        $category->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Expense category deleted.',
            ]);
        }

        return back()->with('success', 'Expense category deleted.');
    }
}
