<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('url_key', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status === 'active' ? 1 : 0);
        }

        return Inertia::render('Admin/Products/Index', [
            'products' => $query->paginate(15)->withQueryString(),
            'categories' => Category::whereNull('parent_id')
                ->with(['children' => fn($q) => $q->orderBy('name')])
                ->with('attributes')
                ->orderBy('name')
                ->get(),
            'store_settings' => \App\Models\StoreSetting::allAsArray(),
            'filters' => $request->only(['search', 'category_id', 'status'])
        ]);
    }

    /** AJAX: search products for the related-products picker */
    public function searchRelated(Request $request)
    {
        $q = trim($request->get('q', ''));
        $exclude = (int) $request->get('exclude', 0);

        $query = Product::orderBy('name')->select('id', 'name', 'image', 'url_key');
        if ($q)
            $query->where('name', 'like', '%' . $q . '%');
        if ($exclude)
            $query->where('id', '!=', $exclude);

        return response()->json($query->limit(15)->get());
    }

    /** Return minimal product data for pre-selected IDs (used when editing) */
    public function relatedByIds(Request $request)
    {
        $ids = array_filter(explode(',', $request->get('ids', '')), 'is_numeric');
        return response()->json(
            Product::whereIn('id', $ids)->select('id', 'name', 'image', 'url_key')->get()
        );
    }

    private function rules(bool $isUpdate = false, ?Product $product = null): array
    {
        $uniqueSuffix = $isUpdate ? ",{$product->id}" : '';
        return [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'url_key' => "required|string|max:255|unique:products,url_key{$uniqueSuffix}",
            'product_number' => "nullable|string|max:100|unique:products,product_number{$uniqueSuffix}",
            'sku' => "nullable|string|max:100|unique:products,sku{$uniqueSuffix}",
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0',
            'special_price_from' => 'nullable|date',
            'special_price_to' => 'nullable|date|after_or_equal:special_price_from',
            'stock' => 'required|integer|min:0',
            'is_new' => 'boolean',
            'is_featured' => 'boolean',
            'visible_individually' => 'boolean',
            'status' => 'boolean',
            'show_stock_level' => 'boolean',
            'related_products' => 'nullable|array',
            'related_products.*' => 'exists:products,id',
            'variants' => 'nullable|string',
            'attributes' => 'nullable|string',
            'image' => 'nullable|image|max:3072',
            'images' => 'nullable|array|max:10',
            'images.*' => 'image|max:3072',
        ];
    }

    private function processRequest(Request $request, array $validated): array
    {
        $validated['slug'] = Str::slug($validated['name']) . '-' . uniqid();

        // Booleans sent as strings from FormData
        foreach (['is_new', 'is_featured', 'visible_individually', 'status', 'show_stock_level'] as $bool) {
            $validated[$bool] = filter_var($validated[$bool] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        // related_products comes as a JSON string from FormData
        if (isset($validated['related_products']) && is_string($validated['related_products'])) {
            $validated['related_products'] = json_decode($validated['related_products'], true) ?: [];
        }

        if ($request->has('variants') && $request->input('variants')) {
            $validated['variants'] = json_decode($request->input('variants'), true);
        }

        if ($request->has('attributes') && $request->input('attributes')) {
            $validated['attributes'] = json_decode($request->input('attributes'), true);
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($validated['image']);
        }

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                $paths[] = $file->store('products', 'public');
            }
            $validated['images'] = $paths;
        } else {
            unset($validated['images']);
        }

        return $validated;
    }

    public function store(Request $request)
    {
        // Decode related_products JSON string before validation (FormData sends strings)
        if ($request->has('related_products') && is_string($request->related_products)) {
            $request->merge(['related_products' => json_decode($request->related_products, true) ?: []]);
        }

        $validated = $request->validate($this->rules());
        $validated = $this->processRequest($request, $validated);

        Product::create($validated);
        return back()->with('success', 'Product created successfully.');
    }

    public function update(Request $request, Product $product)
    {
        // Decode related_products JSON string before validation (FormData sends strings)
        if ($request->has('related_products') && is_string($request->related_products)) {
            $request->merge(['related_products' => json_decode($request->related_products, true) ?: []]);
        }

        $validated = $request->validate($this->rules(true, $product));
        $validated = $this->processRequest($request, $validated);

        $product->update($validated);
        return back()->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }
}
