<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        // Load all categories for this tenant
        $categories = ProductCategory::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Load products once, group by category name
        $products = Product::where('tenant_id', $tenantId)->get();

        $productsByCategory = $products->groupBy('category');

        // All distinct category names used in products (including uncategorised)
        $usedCategoryNames = $products->pluck('category')->filter()->unique()->values();

        // Build enriched category list
        $enriched = $categories->map(function (ProductCategory $cat) use ($productsByCategory) {
            $items = $productsByCategory->get($cat->name, collect());
            $count = $items->count();
            $stockUnits = $items->sum('stock_quantity');
            $stockValue = $items->sum(fn ($p) => $p->cost_price * $p->stock_quantity);
            $retailValue = $items->sum(fn ($p) => $p->selling_price * $p->stock_quantity);

            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'description' => $cat->description,
                'color' => $cat->color,
                'icon' => $cat->icon,
                'default_unit' => $cat->default_unit,
                'default_tax_rate' => (float) $cat->default_tax_rate,
                'is_active' => $cat->is_active,
                'sort_order' => $cat->sort_order,
                'product_count' => $count,
                'stock_units' => (int) $stockUnits,
                'stock_value' => (float) $stockValue,
                'retail_value' => (float) $retailValue,
            ];
        });

        // Detect product categories not yet formalised
        $formalNames = $categories->pluck('name');
        $orphanedNames = $usedCategoryNames->diff($formalNames)->values();

        return Inertia::render('products/Categories', [
            'categories' => $enriched,
            'orphanedNames' => $orphanedNames,
            'totalProducts' => $products->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'default_unit' => 'nullable|string|max:50',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        ProductCategory::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'slug' => Str::slug($validated['name']),
            'color' => $validated['color'] ?? '#3b82f6',
            'icon' => $validated['icon'] ?? 'Package',
            'default_unit' => $validated['default_unit'] ?? 'Piece',
            'default_tax_rate' => $validated['default_tax_rate'] ?? 16.00,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', "Category \"{$validated['name']}\" created.");
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($productCategory->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'default_unit' => 'nullable|string|max:50',
            'default_tax_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $oldName = $productCategory->name;

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $productCategory->update($validated);

        if (isset($validated['name']) && $validated['name'] !== $oldName) {
            Product::where('tenant_id', $tenant->id)
                ->where('category', $oldName)
                ->update(['category' => $validated['name']]);
        }

        return back()->with('success', "Category \"{$productCategory->name}\" updated.");
    }

    public function destroy(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($productCategory->tenant_id === $tenant->id, 403);

        // Check if products use this category
        $productCount = Product::where('tenant_id', $tenant->id)
            ->where('category', $productCategory->name)
            ->count();

        if ($productCount > 0) {
            return back()->with('error', "Cannot delete \"{$productCategory->name}\" — {$productCount} product(s) are assigned to it. Reassign them first.");
        }

        $name = $productCategory->name;
        $productCategory->delete();

        return back()->with('success', "Category \"{$name}\" deleted.");
    }

    /**
     * Import an orphaned (informal) category name into the formal categories table.
     */
    public function importOrphaned(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default_unit' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        $exists = ProductCategory::where('tenant_id', $tenant->id)
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->with('error', "Category \"{$validated['name']}\" already exists.");
        }

        ProductCategory::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'color' => $validated['color'] ?? '#64748b',
            'icon' => 'Package',
            'default_unit' => $validated['default_unit'] ?? 'Piece',
            'default_tax_rate' => 16.00,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        return back()->with('success', "Imported \"{$validated['name']}\" as a formal category.");
    }
}
