<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Modules\CommerceIntegration\Services\CommerceWebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $branch = current_branch();
        $search = $request->input('search');

        $brands = Brand::when($branch, function ($query) use ($branch) {
            $query->where(function ($q) use ($branch) {
                $q->where('branch_id', $branch->id)
                  ->orWhereNull('branch_id');
            });
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

        return Inertia::render('Brands/Index', [
            'brands'  => $brands,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $branch = current_branch();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'logo'        => 'nullable|image|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('brands', 'public')
            : null;

        $brand = Brand::create([
            'branch_id'   => $branch?->id,
            'name'        => trim($validated['name']),
            'slug'        => !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'logo'        => $logoPath,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        if ($branch) {
            try {
                CommerceWebhookDispatcher::dispatch($branch->id, 'brand.created', [
                    'pos_brand_id' => $brand->id,
                    'name'         => $brand->name,
                    'slug'         => $brand->slug,
                    'logo_url'     => $brand->logo_url,
                    'is_active'    => (bool) $brand->is_active,
                ]);
            } catch (\Throwable) {}
        }

        if ($request->wantsJson()) {
            return response()->json(['brand' => $brand]);
        }

        return back()->with('success', 'Brand created successfully');
    }

    public function update(Request $request, $branchParam, Brand $brand)
    {
        $branch = current_branch();

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'logo'        => 'nullable|image|max:2048',
            'is_active'   => 'nullable|boolean',
        ]);

        $updates = [
            'name'        => trim($validated['name']),
            'slug'        => !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']),
            'description' => $validated['description'] ?? $brand->description,
            'is_active'   => isset($validated['is_active']) ? (bool) $validated['is_active'] : $brand->is_active,
        ];

        if ($request->hasFile('logo')) {
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $updates['logo'] = $request->file('logo')->store('brands', 'public');
        }

        $brand->update($updates);

        if ($branch) {
            try {
                CommerceWebhookDispatcher::dispatch($branch->id, 'brand.updated', [
                    'pos_brand_id' => $brand->id,
                    'name'         => $brand->name,
                    'slug'         => $brand->slug,
                    'logo_url'     => $brand->logo_url,
                    'is_active'    => (bool) $brand->is_active,
                ]);
            } catch (\Throwable) {}
        }

        return back()->with('success', 'Brand updated successfully');
    }

    public function destroy($branchParam, Brand $brand)
    {
        $branch = current_branch();
        $posBrandId = $brand->id;

        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->delete();

        if ($branch) {
            try {
                CommerceWebhookDispatcher::dispatch($branch->id, 'brand.deleted', [
                    'pos_brand_id' => $posBrandId,
                ]);
            } catch (\Throwable) {}
        }

        return back()->with('success', 'Brand deleted successfully');
    }
}

