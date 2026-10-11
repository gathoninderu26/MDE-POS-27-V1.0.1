<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\CloudSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $sales = Sale::where('tenant_id', $tenantId)
            ->with(['branch', 'user', 'items.product'])
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'receipt_number' => $s->receipt_number,
                'subtotal' => (float) $s->subtotal,
                'tax_amount' => (float) $s->tax_amount,
                'discount_amount' => (float) $s->discount_amount,
                'total' => (float) $s->total,
                'profit' => (float) $s->profit,
                'payment_method' => $s->payment_method,
                'payment_status' => $s->payment_status,
                'mpesa_code' => $s->mpesa_code,
                'mpesa_payer_name' => $s->mpesa_payer_name,
                'customer_name' => $s->customer_name,
                'customer_phone' => $s->customer_phone,
                'cashier' => $s->user?->name ?? 'Staff',
                'branch' => $s->branch?->name ?? 'Main Store',
                'created_at' => $s->created_at->format('Y-m-d H:i'),
                'items_count' => $s->items->count(),
                'items' => $s->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ]),
            ]);

        return Inertia::render('sales/Index', [
            'sales' => $sales,
        ]);
    }

    public function pos(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'category' => $p->category ?? 'General',
                'costPrice' => (float) $p->cost_price,
                'sellingPrice' => (float) $p->selling_price,
                'price' => (float) $p->selling_price,
                'stock' => (int) $p->stock_quantity,
                'unit' => $p->unit,
            ]);

        $branches = Branch::where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->get(['id', 'name', 'code']);

        return Inertia::render('pos/Index', [
            'products' => $products,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();

        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.product_name' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.cost_price' => 'nullable|numeric|min:0',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'mpesa_code' => 'nullable|string|max:20',
            'mpesa_payer_name' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($tenant, $user, $validated) {
            $totalCost = 0;
            foreach ($validated['items'] as $item) {
                $cost = (float) ($item['cost_price'] ?? 0);
                $totalCost += ($cost * $item['quantity']);
            }

            $profit = max(0, (float) $validated['total'] - $totalCost);
            $dailyCount = Sale::where('tenant_id', $tenant->id)->whereDate('created_at', today())->count() + 1;
            $receiptNumber = 'REC-'.date('ymd').'-'.str_pad((string) $dailyCount, 4, '0', STR_PAD_LEFT).'-'.strtoupper(Str::random(3));

            $sale = Sale::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $validated['branch_id'] ?? null,
                'user_id' => $user->id,
                'receipt_number' => $receiptNumber,
                'subtotal' => $validated['subtotal'],
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'total' => $validated['total'],
                'profit' => $profit,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'Completed',
                'mpesa_code' => $validated['mpesa_code'] ?? null,
                'mpesa_payer_name' => $validated['mpesa_payer_name'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $unitPrice = (float) $item['unit_price'];
                $qty = (int) $item['quantity'];
                $itemTotal = $unitPrice * $qty;
                $cost = (float) ($item['cost_price'] ?? 0);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'cost_price' => $cost,
                    'total' => $itemTotal,
                ]);

                // Decrement stock if product exists (scoped strictly to current tenant)
                if (! empty($item['product_id'])) {
                    Product::where('tenant_id', $tenant->id)
                        ->where('id', $item['product_id'])
                        ->decrement('stock_quantity', $qty);
                }
            }

            // Enqueue store-and-forward cloud sync outbox record (MySQL -> Supabase)
            app(CloudSyncService::class)->recordChange(
                $tenant->id,
                'sale',
                (string) $sale->id,
                'create',
                [
                    'id' => $sale->id,
                    'tenant_id' => $tenant->id,
                    'branch_id' => $sale->branch_id,
                    'user_id' => $sale->user_id,
                    'receipt_number' => $sale->receipt_number,
                    'subtotal' => (float) $sale->subtotal,
                    'tax_amount' => (float) $sale->tax_amount,
                    'discount_amount' => (float) $sale->discount_amount,
                    'total' => (float) $sale->total,
                    'profit' => (float) $sale->profit,
                    'payment_method' => $sale->payment_method,
                    'payment_status' => $sale->payment_status,
                    'mpesa_code' => $sale->mpesa_code,
                    'customer_name' => $sale->customer_name,
                    'items' => $validated['items'],
                    'created_at' => $sale->created_at?->toIso8601String(),
                ]
            );
        });

        return back()->with('success', 'Sale completed successfully.');
    }
}
