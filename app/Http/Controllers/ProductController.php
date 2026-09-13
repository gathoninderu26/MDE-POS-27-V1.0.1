<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BarcodeLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $products = Product::where('tenant_id', $tenantId)
            ->with('branch')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku ?? 'SKU-' . $p->id,
                'barcode' => $p->barcode,
                'category' => $p->category ?? 'General',
                'cost_price' => (float) $p->cost_price,
                'selling_price' => (float) $p->selling_price,
                'stock_quantity' => (int) $p->stock_quantity,
                'reorder_level' => (int) $p->reorder_level,
                'unit' => $p->unit,
                'status' => $p->status,
                'branch_id' => $p->branch_id,
                'branch_name' => $p->branch?->name ?? 'All Branches',
            ]);

        $branches = Branch::where('tenant_id', $tenantId)->get(['id', 'name']);

        $categories = ProductCategory::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'default_unit', 'default_tax_rate']);

        return Inertia::render('products/Index', [
            'products' => $products,
            'branches' => $branches,
            'categories' => $categories,
            'initialCategory' => $request->query('category'),
        ]);
    }

    public function inventory(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $products = Product::where('tenant_id', $tenantId)
            ->with('branch')
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku ?? 'SKU-' . $p->id,
                'barcode' => $p->barcode,
                'category' => $p->category ?? 'General',
                'cost_price' => (float) $p->cost_price,
                'selling_price' => (float) $p->selling_price,
                'stock_quantity' => (int) $p->stock_quantity,
                'reorder_level' => (int) $p->reorder_level,
                'unit' => $p->unit,
                'status' => $p->status,
                'branch_id' => $p->branch_id,
                'branch_name' => $p->branch?->name ?? 'HQ Main Store',
            ]);

        $branches = Branch::where('tenant_id', $tenantId)->get(['id', 'name']);

        return Inertia::render('inventory/Index', [
            'products' => $products,
            'branches' => $branches,
        ]);
    }

    public function barcodeLookup(Request $request, BarcodeLookupService $lookupService): JsonResponse
    {
        $barcode = $request->query('barcode');
        if (!$barcode || strlen(trim($barcode)) < 3) {
            return response()->json(['found' => false, 'message' => 'Invalid barcode']);
        }

        $result = $lookupService->lookup(trim($barcode));
        return response()->json($result);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        Product::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'status' => 'Active',
        ]);

        if (!empty($validated['barcode']) && !empty($validated['name'])) {
            app(BarcodeLookupService::class)->remember($validated['barcode'], [
                'name' => $validated['name'],
                'category' => $validated['category'] ?? 'General',
                'source' => 'pos_catalog',
            ]);
        }

        return back()->with('success', "Product \"{$validated['name']}\" created.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($product->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'cost_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'reorder_level' => 'sometimes|integer|min:0',
            'unit' => 'nullable|string|max:50',
            'status' => 'sometimes|string',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $product->update($validated);

        $barcodeToRemember = $validated['barcode'] ?? $product->barcode;
        $nameToRemember = $validated['name'] ?? $product->name;
        if (!empty($barcodeToRemember) && !empty($nameToRemember)) {
            app(BarcodeLookupService::class)->remember($barcodeToRemember, [
                'name' => $nameToRemember,
                'category' => $validated['category'] ?? $product->category ?? 'General',
                'source' => 'pos_catalog',
            ]);
        }

        return back()->with('success', "Product \"{$product->name}\" updated.");
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($product->tenant_id === $tenant->id, 403);

        $name = $product->name;
        $product->delete();

        return back()->with('success', "Product \"{$name}\" deleted.");
    }

    public function adjustStock(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($tenant, 403);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'change' => 'required|integer',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500',
        ]);

        $product = Product::where('tenant_id', $tenant->id)->findOrFail($validated['product_id']);
        $newStock = max(0, $product->stock_quantity + (int) $validated['change']);
        $product->update(['stock_quantity' => $newStock]);

        return back()->with('success', "Stock for \"{$product->name}\" updated to {$newStock} units ({$validated['reason']}).");
    }

    public function transferStock(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($tenant, 403);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'from_branch' => 'nullable|string|max:255',
            'to_branch' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $product = Product::where('tenant_id', $tenant->id)->findOrFail($validated['product_id']);
        if ($product->stock_quantity < $validated['quantity']) {
            return back()->with('error', "Insufficient stock for transfer. Available: {$product->stock_quantity} units.");
        }

        $product->decrement('stock_quantity', $validated['quantity']);

        return back()->with('success', "Dispatched {$validated['quantity']} units of \"{$product->name}\" to {$validated['to_branch']}.");
    }

    public function import(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($tenant, 403);

        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string|max:255',
            'products.*.sku' => 'nullable|string|max:100',
            'products.*.barcode' => 'nullable|string|max:100',
            'products.*.category' => 'nullable|string|max:100',
            'products.*.cost_price' => 'nullable|numeric|min:0',
            'products.*.selling_price' => 'required|numeric|min:0',
            'products.*.stock_quantity' => 'nullable|integer|min:0',
            'products.*.reorder_level' => 'nullable|integer|min:0',
            'products.*.unit' => 'nullable|string|max:50',
            'update_existing' => 'nullable|boolean',
        ]);

        $importedCount = 0;
        $updatedCount = 0;
        $updateExisting = $validated['update_existing'] ?? true;

        foreach ($validated['products'] as $item) {
            $name = trim($item['name']);
            if (empty($name)) {
                continue;
            }

            $sku = !empty($item['sku']) ? trim((string) $item['sku']) : null;
            $barcode = !empty($item['barcode']) ? trim((string) $item['barcode']) : null;

            // Search for existing product by SKU, Barcode, or case-insensitive Name
            $existing = null;
            if ($sku) {
                $existing = Product::where('tenant_id', $tenant->id)->where('sku', $sku)->first();
            }
            if (!$existing && $barcode) {
                $existing = Product::where('tenant_id', $tenant->id)->where('barcode', $barcode)->first();
            }
            if (!$existing) {
                $existing = Product::where('tenant_id', $tenant->id)->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            }

            $costPrice = isset($item['cost_price']) && is_numeric($item['cost_price']) ? (float) $item['cost_price'] : 0;
            $sellingPrice = isset($item['selling_price']) && is_numeric($item['selling_price']) ? (float) $item['selling_price'] : 0;
            $stockQty = isset($item['stock_quantity']) && is_numeric($item['stock_quantity']) ? (int) $item['stock_quantity'] : 0;
            $reorderLevel = isset($item['reorder_level']) && is_numeric($item['reorder_level']) ? (int) $item['reorder_level'] : 5;
            $category = !empty($item['category']) ? trim((string) $item['category']) : 'General';
            $unit = !empty($item['unit']) ? trim((string) $item['unit']) : 'Piece';

            if ($existing) {
                if ($updateExisting) {
                    $existing->update([
                        'name' => $name,
                        'category' => $category,
                        'cost_price' => $costPrice > 0 ? $costPrice : $existing->cost_price,
                        'selling_price' => $sellingPrice > 0 ? $sellingPrice : $existing->selling_price,
                        'stock_quantity' => $stockQty > 0 ? $stockQty : $existing->stock_quantity,
                        'reorder_level' => $reorderLevel,
                        'unit' => $unit,
                        'barcode' => $barcode ?? $existing->barcode,
                        'sku' => $sku ?? $existing->sku,
                    ]);
                    $updatedCount++;
                }
            } else {
                if (!$sku) {
                    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $category ?: 'GEN'), 0, 3));
                    if (strlen($prefix) < 3) {
                        $prefix = 'PRD';
                    }
                    $count = Product::where('tenant_id', $tenant->id)->count() + 1;
                    $sku = $prefix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
                }

                Product::create([
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'sku' => $sku,
                    'barcode' => $barcode,
                    'category' => $category,
                    'cost_price' => $costPrice,
                    'selling_price' => $sellingPrice,
                    'stock_quantity' => $stockQty,
                    'reorder_level' => $reorderLevel,
                    'unit' => $unit,
                    'status' => 'Active',
                ]);
                $importedCount++;
            }
        }

        $msg = "Imported {$importedCount} new products" . ($updatedCount > 0 ? " and updated {$updatedCount} existing products." : ".");
        return back()->with('success', $msg);
    }
}
