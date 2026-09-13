<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $purchases = Purchase::where('tenant_id', $tenantId)
            ->with(['supplier', 'branch', 'user'])
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'poNumber' => $p->po_number,
                'documentType' => $p->document_type ?? 'PO',
                'lpoNumber' => $p->lpo_number,
                'lpoPurpose' => $p->lpo_purpose,
                'supplier' => $p->supplier?->name ?? 'Direct Purchase',
                'supplierId' => $p->supplier_id,
                'branch' => $p->branch?->name ?? 'Main Store',
                'branchId' => $p->branch_id,
                'date' => $p->order_date?->format('Y-m-d'),
                'expectedDate' => $p->expected_date?->format('Y-m-d') ?? 'N/A',
                'createdByName' => $p->user?->name ?? 'Procurement Officer',
                'createdByEmail' => $p->user?->email,
                'orderTime' => $p->created_at?->format('Y-m-d H:i') ?? $p->order_date?->format('Y-m-d H:i'),
                'syncedAt' => $p->status === 'Received' ? ($p->updated_at?->format('Y-m-d H:i') ?? null) : null,
                'items' => $p->items_description ?? 'General Supplies',
                'amount' => (float) $p->total_amount,
                'paidAmount' => (float) $p->paid_amount,
                'paymentStatus' => $p->payment_status,
                'status' => $p->status,
                'notes' => $p->notes,
            ]);

        $suppliers = Supplier::where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->get(['id', 'name']);

        $branches = Branch::where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->get(['id', 'name']);

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', 'Active')
            ->get(['id', 'name', 'sku', 'barcode', 'cost_price', 'selling_price', 'stock_quantity', 'reorder_level', 'unit']);

        return Inertia::render('purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'branches' => $branches,
            'products' => $products,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        $user = $request->user();

        $validated = $request->validate([
            'document_type' => 'nullable|in:PO,LPO',
            'lpo_purpose' => 'nullable|string|max:100',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'branch_id' => 'nullable|exists:branches,id',
            'items_description' => 'nullable|string',
            'total_amount' => 'required|numeric|min:0',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $docType = $validated['document_type'] ?? 'PO';

        if ($docType === 'LPO') {
            $lpoCount = Purchase::where('tenant_id', $tenant->id)->where('document_type', 'LPO')->count() + 1;
            $lpoNumber = 'LPO-' . str_pad((string) $lpoCount, 4, '0', STR_PAD_LEFT);
            $poNumber = $lpoNumber;
        } else {
            $poCount = Purchase::where('tenant_id', $tenant->id)->where(function ($q) {
                $q->where('document_type', 'PO')->orWhereNull('document_type');
            })->count() + 1;
            $poNumber = 'PO-' . str_pad((string) $poCount, 4, '0', STR_PAD_LEFT);
            $lpoNumber = null;
        }

        $purchase = Purchase::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'document_type' => $docType,
            'po_number' => $poNumber,
            'lpo_number' => $lpoNumber,
            'lpo_purpose' => $validated['lpo_purpose'] ?? null,
            'order_date' => now()->toDateString(),
            'expected_date' => $validated['expected_date'] ?? now()->addDays(2)->toDateString(),
            'items_description' => $validated['items_description'] ?? ($docType === 'LPO' ? ($validated['lpo_purpose'] ?? 'Local Purchase Order') : 'General Supplies'),
            'total_amount' => $validated['total_amount'],
            'paid_amount' => 0,
            'payment_status' => 'Pending',
            'status' => 'Pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        if (!empty($validated['supplier_id'])) {
            $supplier = Supplier::find($validated['supplier_id']);
            if ($supplier) {
                $supplier->increment('total_purchases', $validated['total_amount']);
                $supplier->increment('outstanding_balance', $validated['total_amount']);
            }
        }

        $label = $docType === 'LPO' ? "Local Purchase Order {$lpoNumber}" : "Purchase order {$poNumber}";
        return back()->with('success', "{$label} created successfully.");
    }

    public function receiveStock(Request $request, Purchase $purchase): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($purchase->tenant_id === $tenant->id, 403);

        $validated = $request->validate([
            'received_items' => 'nullable|array',
            'receiving_notes' => 'nullable|string|max:500',
        ]);

        $purchase->update(['status' => 'Received']);

        $restockedCount = 0;
        $totalUnitsAdded = 0;

        // Check if notes contains JSON structured bulk items
        if (!empty($purchase->notes)) {
            $items = json_decode($purchase->notes, true);
            if (is_array($items)) {
                $receivedIds = $validated['received_items'] ?? null;

                foreach ($items as $item) {
                    $prodId = $item['product_id'] ?? null;
                    if ($receivedIds !== null && !in_array($prodId, $receivedIds)) {
                        continue;
                    }

                    if (!empty($prodId) && !empty($item['quantity'])) {
                        $product = Product::where('tenant_id', $tenant->id)->find($prodId);
                        if ($product) {
                            $qty = (int) $item['quantity'];
                            $product->increment('stock_quantity', $qty);
                            $restockedCount++;
                            $totalUnitsAdded += $qty;
                        }
                    }
                }
            }
        }

        $ref = $purchase->document_type === 'LPO' ? ($purchase->lpo_number ?? $purchase->po_number) : $purchase->po_number;
        return back()->with('success', "{$ref} received & synced: +{$totalUnitsAdded} units updated across {$restockedCount} products.");
    }

    public function destroy(Request $request, Purchase $purchase): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($purchase->tenant_id === $tenant->id, 403);

        $purchase->delete();

        return back()->with('success', 'Purchase order deleted.');
    }
}
