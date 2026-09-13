<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $today = now()->startOfDay();
        $startOfMonth = now()->startOfMonth();

        $branchStockValues = Product::where('tenant_id', $tenantId)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, SUM(cost_price * stock_quantity) as val')
            ->groupBy('branch_id')
            ->pluck('val', 'branch_id');

        $branchProductCounts = Product::where('tenant_id', $tenantId)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, COUNT(*) as cnt')
            ->groupBy('branch_id')
            ->pluck('cnt', 'branch_id');

        $branchTodaySales = Sale::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $today)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, SUM(total) as total_sales, COUNT(*) as tx_count')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $branchMonthSales = Sale::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $startOfMonth)
            ->whereNotNull('branch_id')
            ->selectRaw('branch_id, SUM(total) as month_sales')
            ->groupBy('branch_id')
            ->pluck('month_sales', 'branch_id');

        $staffUsers = User::where('tenant_id', $tenantId)
            ->select('id', 'name', 'role', 'phone', 'email', 'status')
            ->get();

        $branches = Branch::where('tenant_id', $tenantId)
            ->latest()
            ->get()
            ->map(function ($b) use ($branchStockValues, $branchProductCounts, $branchTodaySales, $branchMonthSales) {
                $todayData = $branchTodaySales->get($b->id);
                return [
                    'id' => (string) $b->id,
                    'tenantId' => (string) $b->tenant_id,
                    'name' => $b->name,
                    'code' => $b->code ?? 'BR-' . $b->id,
                    'branchType' => $b->type ?: 'Retail Branch',
                    'location' => $b->location ?? 'Not specified',
                    'phone' => $b->phone ?? 'N/A',
                    'manager' => $b->manager ?? 'Unassigned',
                    'currentStockValue' => (float) ($branchStockValues->get($b->id) ?? 0),
                    'productsCount' => (int) ($branchProductCounts->get($b->id) ?? 0),
                    'todaySales' => (float) ($todayData?->total_sales ?? 0),
                    'monthlySales' => (float) ($branchMonthSales->get($b->id) ?? 0),
                    'transactionsToday' => (int) ($todayData?->tx_count ?? 0),
                    'operatingHours' => $b->operating_hours ?? '08:00 AM - 08:00 PM',
                    'wifiSSID' => $b->wifi_ssid ?? '',
                    'status' => $b->status ?: 'Active',
                    'isMain' => (bool) $b->is_main,
                ];
            });

        $tenantMasterProductsCount = Product::where('tenant_id', $tenantId)->count();

        return Inertia::render('branches/Index', [
            'branches' => $branches,
            'staffUsers' => $staffUsers,
            'masterProductsCount' => $tenantMasterProductsCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'manager' => 'nullable|string|max:255',
            'operating_hours' => 'nullable|string|max:255',
            'wifi_ssid' => 'nullable|string|max:255',
            'is_main' => 'nullable|boolean',
            'clone_products' => 'nullable|boolean',
        ]);

        // Auto-generate a clean outlet code if not specified
        if (empty($validated['code'])) {
            $words = preg_split('/\s+/', trim($validated['name']));
            $prefix = count($words) > 1
                ? strtoupper(substr($words[0], 0, 2) . substr($words[1], 0, 1))
                : strtoupper(substr($validated['name'], 0, 3));
            $count = Branch::where('tenant_id', $tenant->id)->count() + 1;
            $validated['code'] = $prefix . '-' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
        }

        $cloneProducts = (bool) ($validated['clone_products'] ?? false);
        unset($validated['clone_products']);

        $branch = Branch::create([
            ...$validated,
            'tenant_id' => $tenant->id,
            'status' => 'Active',
        ]);

        // If user requested cloning product definitions into new branch
        if ($cloneProducts) {
            $masterProducts = Product::where('tenant_id', $tenant->id)->get();
            $uniqueProducts = $masterProducts->unique(fn($p) => $p->sku ?: ($p->barcode ?: $p->name));

            foreach ($uniqueProducts as $p) {
                Product::create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'category' => $p->category,
                    'cost_price' => $p->cost_price,
                    'selling_price' => $p->selling_price,
                    'stock_quantity' => 0,
                    'reorder_level' => $p->reorder_level,
                    'unit' => $p->unit,
                    'expiry_date' => $p->expiry_date,
                ]);
            }
        }

        return back()->with('success', "Branch \"{$branch->name}\" ({$branch->code}) registered successfully.");
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($branch->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'manager' => 'nullable|string|max:255',
            'operating_hours' => 'nullable|string|max:255',
            'wifi_ssid' => 'nullable|string|max:255',
            'status' => 'nullable|string',
            'is_main' => 'nullable|boolean',
        ]);

        $branch->update($validated);

        return back()->with('success', "Branch \"{$branch->name}\" updated successfully.");
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($branch->tenant_id === $tenant->id, 403);

        $name = $branch->name;

        // Safety check: Prevent deleting branch with historical sales records
        if ($branch->sales()->exists()) {
            return back()->with('error', "Cannot delete \"{$name}\" because it has recorded sales history. You can suspend or deactivate it instead.");
        }

        // Delete associated empty products if any
        Product::where('tenant_id', $tenant->id)->where('branch_id', $branch->id)->delete();

        $branch->delete();

        return back()->with('success', "Branch \"{$name}\" deleted successfully.");
    }
}
