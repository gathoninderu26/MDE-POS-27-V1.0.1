<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\InvoiceParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $tenant = $this->currentTenant($request);
        $tenantId = $tenant?->id;

        $purchases = Purchase::where('tenant_id', $tenantId)
            ->with(['supplier', 'branch', 'user', 'invoice.verifiedBy'])
            ->latest()
            ->get()
            ->map(fn ($p) => [
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
                'invoice' => $p->invoice ? [
                    'id' => $p->invoice->id,
                    'invoiceNumber' => $p->invoice->invoice_number,
                    'invoiceDate' => $p->invoice->invoice_date?->format('Y-m-d'),
                    'taxPin' => $p->invoice->tax_pin,
                    'controlUnitCode' => $p->invoice->control_unit_code,
                    'totalAmount' => (float) $p->invoice->total_amount,
                    'varianceAmount' => (float) $p->invoice->variance_amount,
                    'reconciliationStatus' => $p->invoice->reconciliation_status,
                    'status' => $p->invoice->status,
                    'hasDocument' => ! empty($p->invoice->file_path) && $p->invoice->file_path !== 'stored',
                    'filePath' => $p->invoice->file_path,
                    'fileName' => $p->invoice->file_name,
                    'verifiedAt' => $p->invoice->verified_at?->format('Y-m-d H:i'),
                    'verifiedByName' => $p->invoice->verifiedBy?->name ?? 'Verified',
                    'matrix' => $p->invoice->reconciliation_matrix,
                    'extractedItems' => $p->invoice->extracted_items,
                    'rawQrPayload' => $p->invoice->raw_qr_payload,
                    'notes' => $p->invoice->notes,
                ] : null,
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
            $lpoNumber = 'LPO-'.str_pad((string) $lpoCount, 4, '0', STR_PAD_LEFT);
            $poNumber = $lpoNumber;
        } else {
            $poCount = Purchase::where('tenant_id', $tenant->id)->where(function ($q) {
                $q->where('document_type', 'PO')->orWhereNull('document_type');
            })->count() + 1;
            $poNumber = 'PO-'.str_pad((string) $poCount, 4, '0', STR_PAD_LEFT);
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

        if (! empty($validated['supplier_id'])) {
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
        if (! empty($purchase->notes)) {
            $items = json_decode($purchase->notes, true);
            if (is_array($items)) {
                $receivedIds = $validated['received_items'] ?? null;

                foreach ($items as $item) {
                    $prodId = $item['product_id'] ?? null;
                    if ($receivedIds !== null && ! in_array($prodId, $receivedIds)) {
                        continue;
                    }

                    if (! empty($prodId) && ! empty($item['quantity'])) {
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

    /**
     * Upload physical invoice (file or camera snapshot), extract QR/fiscal data,
     * and return live comparison matrix against PO line items.
     */
    public function uploadInvoice(Request $request, Purchase $purchase, InvoiceParserService $parser): JsonResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($purchase->tenant_id === $tenant->id, 403);

        $request->validate([
            'invoice_file' => 'nullable|file|mimes:jpeg,png,webp,jpg,pdf|max:10240',
            'image_base64' => 'nullable|string',
            'qr_payload' => 'nullable|string|max:2000',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|string|max:50',
            'total_amount' => 'nullable|numeric|min:0',
        ]);

        $filePath = null;
        $fileName = null;
        $mimeType = 'image/jpeg';
        $fileSize = 0;

        $disk = Storage::disk('local');
        $tenantDir = "tenants/{$tenant->id}/invoices/".date('Y/m');

        if ($request->hasFile('invoice_file')) {
            $file = $request->file('invoice_file');
            $fileName = $file->getClientOriginalName();
            $mimeType = $file->getClientMimeType();
            $fileSize = $file->getSize();
            $filePath = $file->store($tenantDir, 'local');
        } elseif ($request->filled('image_base64')) {
            $base64Data = $request->input('image_base64');
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $type = strtolower($type[1]);
                $mimeType = "image/{$type}";
            } else {
                $type = 'jpg';
            }

            $decoded = base64_decode($base64Data);
            $fileName = 'scan_'.time().'.'.$type;
            $fileSize = strlen($decoded);
            $filePath = "{$tenantDir}/{$fileName}";
            $disk->put($filePath, $decoded);
        }

        if (! $filePath) {
            return response()->json(['error' => 'No valid invoice document or image was provided.'], 422);
        }

        // Parse QR payload if detected by client or passed
        $qrPayload = $request->input('qr_payload');
        $parsedQr = $parser->parseQrPayload($qrPayload);

        // Extract PO items to prepare initial comparison
        $defaultInvoicedTotal = $request->filled('total_amount')
            ? (float) $request->input('total_amount')
            : ($parsedQr['total_amount'] ?? (float) $purchase->total_amount);
        $initialInvoicedItems = [];

        if (! empty($purchase->notes)) {
            $decoded = json_decode($purchase->notes, true);
            if (is_array($decoded)) {
                $initialInvoicedItems = array_map(function ($item) {
                    return [
                        'product_id' => $item['product_id'] ?? null,
                        'name' => $item['name'] ?? 'Item',
                        'sku' => $item['sku'] ?? '',
                        'quantity' => (float) ($item['quantity'] ?? $item['total_units'] ?? 1),
                        'unit_cost' => (float) ($item['unit_cost'] ?? 0),
                        'total' => (float) ($item['total'] ?? $item['line_total'] ?? 0),
                    ];
                }, $decoded);
            }
        }

        if (empty($initialInvoicedItems)) {
            $initialInvoicedItems = [[
                'product_id' => null,
                'name' => $purchase->items_description ?: 'General Supplies',
                'sku' => '',
                'quantity' => 1,
                'unit_cost' => $defaultInvoicedTotal,
                'total' => $defaultInvoicedTotal,
            ]];
        }

        $comparison = $parser->compare($purchase, $initialInvoicedItems, $defaultInvoicedTotal);

        // If requested to store softcopy directly without item extraction
        if ($request->boolean('attach_only')) {
            $user = $request->user();
            $invNum = $request->input('invoice_number')
                ?: ($parsedQr['invoice_number'] ?? ('INV-'.($purchase->document_type === 'LPO' ? ($purchase->lpo_number ?? $purchase->po_number) : $purchase->po_number)));
            $invDate = $request->input('invoice_date')
                ?: ($parsedQr['invoice_date'] ?? now()->toDateString());
            $invTotal = $defaultInvoicedTotal;

            PurchaseInvoice::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'purchase_id' => $purchase->id,
                ],
                [
                    'supplier_id' => $purchase->supplier_id,
                    'invoice_number' => $invNum,
                    'invoice_date' => $invDate,
                    'tax_pin' => $parsedQr['tax_pin'] ?? null,
                    'control_unit_code' => $parsedQr['control_unit_code'] ?? null,
                    'subtotal' => $invTotal,
                    'tax_amount' => 0,
                    'total_amount' => $invTotal,
                    'po_expected_total' => (float) $purchase->total_amount,
                    'variance_amount' => 0,
                    'raw_qr_payload' => $qrPayload,
                    'extracted_items' => $initialInvoicedItems,
                    'reconciliation_matrix' => $comparison['rows'],
                    'file_path' => $filePath,
                    'file_name' => $fileName ?? 'invoice_scan.jpg',
                    'mime_type' => $mimeType ?? 'image/jpeg',
                    'file_size' => $fileSize ?? 0,
                    'reconciliation_status' => 'ATTACHED_ONLY',
                    'status' => 'Verified',
                    'verified_by_user_id' => $user->id,
                    'verified_at' => now(),
                    'notes' => 'Physical invoice softcopy archived.',
                ]
            );

            return response()->json([
                'success' => true,
                'attached' => true,
                'message' => "Invoice {$invNum} stored and attached to {$purchase->po_number}.",
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);
        }

        return response()->json([
            'success' => true,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'qr' => $parsedQr,
            'comparison' => $comparison,
            'po' => [
                'id' => $purchase->id,
                'poNumber' => $purchase->po_number,
                'supplier' => $purchase->supplier?->name,
                'amount' => (float) $purchase->total_amount,
            ],
        ]);
    }

    /**
     * Save finalized reconciliation, persist PurchaseInvoice record,
     * and optionally auto-receive stock and update product quantities.
     */
    public function reconcileInvoice(Request $request, Purchase $purchase, InvoiceParserService $parser): RedirectResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($purchase->tenant_id === $tenant->id, 403);
        $user = $request->user();

        $validated = $request->validate([
            'file_path' => 'required|string',
            'file_name' => 'nullable|string',
            'mime_type' => 'nullable|string',
            'file_size' => 'nullable|integer',
            'invoice_number' => 'nullable|string|max:100',
            'invoice_date' => 'nullable|date',
            'tax_pin' => 'nullable|string|max:50',
            'control_unit_code' => 'nullable|string|max:100',
            'raw_qr_payload' => 'nullable|string',
            'total_amount' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
            'items.*.name' => 'nullable|string',
            'items.*.sku' => 'nullable|string',
            'items.*.product_id' => 'nullable|integer',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.total' => 'nullable|numeric|min:0',
            'auto_receive_stock' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $invoiceNumber = ! empty($validated['invoice_number'])
            ? trim($validated['invoice_number'])
            : ('INV-'.($purchase->document_type === 'LPO' ? ($purchase->lpo_number ?? $purchase->po_number) : $purchase->po_number));

        $totalAmount = isset($validated['total_amount']) ? (float) $validated['total_amount'] : (float) $purchase->total_amount;

        $items = ! empty($validated['items']) ? $validated['items'] : [[
            'name' => $purchase->items_description ?: 'General Supplies (Softcopy Attached)',
            'sku' => '',
            'product_id' => null,
            'quantity' => 1,
            'unit_cost' => $totalAmount,
            'total' => $totalAmount,
        ]];

        $hasExtractedItems = ! empty($validated['items']) && count($validated['items']) > 0;
        $comparison = $parser->compare($purchase, $items, $totalAmount);
        $reconciliationStatus = $hasExtractedItems ? $comparison['reconciliation_status'] : 'ATTACHED_ONLY';

        $resolvedFilePath = $validated['file_path'];
        $existingInv = $purchase->invoice;
        if (($resolvedFilePath === 'stored' || empty($resolvedFilePath)) && $existingInv && ! empty($existingInv->file_path) && $existingInv->file_path !== 'stored') {
            $resolvedFilePath = $existingInv->file_path;
        }

        PurchaseInvoice::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'purchase_id' => $purchase->id,
            ],
            [
                'supplier_id' => $purchase->supplier_id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
                'tax_pin' => $validated['tax_pin'] ?? null,
                'control_unit_code' => $validated['control_unit_code'] ?? null,
                'subtotal' => $totalAmount,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'po_expected_total' => (float) $purchase->total_amount,
                'variance_amount' => $comparison['variance_total'],
                'raw_qr_payload' => $validated['raw_qr_payload'] ?? null,
                'extracted_items' => $items,
                'reconciliation_matrix' => $comparison['rows'],
                'file_path' => $resolvedFilePath,
                'file_name' => $validated['file_name'] ?? ($existingInv?->file_name ?? 'invoice_scan.jpg'),
                'mime_type' => $validated['mime_type'] ?? ($existingInv?->mime_type ?? 'image/jpeg'),
                'file_size' => $validated['file_size'] ?? ($existingInv?->file_size ?? 0),
                'reconciliation_status' => $reconciliationStatus,
                'status' => 'Verified',
                'verified_by_user_id' => $user->id,
                'verified_at' => now(),
                'notes' => $validated['notes'] ?? ($hasExtractedItems ? null : 'Physical invoice softcopy archived.'),
            ]
        );

        $autoReceive = $validated['auto_receive_stock'] ?? true;
        if ($autoReceive) {
            $purchase->update(['status' => 'Received']);

            $restockedCount = 0;
            $totalUnitsAdded = 0;

            foreach ($validated['items'] as $item) {
                $prodId = $item['product_id'] ?? null;
                $qty = (int) ($item['quantity'] ?? 0);

                if (! empty($prodId) && $qty > 0) {
                    $product = Product::where('tenant_id', $tenant->id)->find($prodId);
                    if ($product) {
                        $product->increment('stock_quantity', $qty);
                        $restockedCount++;
                        $totalUnitsAdded += $qty;
                    }
                }
            }

            $ref = $purchase->document_type === 'LPO' ? ($purchase->lpo_number ?? $purchase->po_number) : $purchase->po_number;

            return back()->with('success', "Invoice {$validated['invoice_number']} verified & attached to {$ref}. +{$totalUnitsAdded} units stocked into inventory.");
        }

        return back()->with('success', "Invoice {$validated['invoice_number']} reconciled and stored successfully.");
    }

    /**
     * Securely stream/download the stored physical invoice document.
     */
    public function viewInvoiceDocument(Request $request, Purchase $purchase): BinaryFileResponse
    {
        $tenant = $this->currentTenant($request);
        abort_unless($purchase->tenant_id === $tenant->id, 403);

        $invoice = $purchase->invoice;
        abort_unless($invoice && ! empty($invoice->file_path), 404, 'Invoice document not found.');

        $disk = Storage::disk('local');
        $filePath = $invoice->file_path;

        if (! $disk->exists($filePath) && ! empty($invoice->file_name)) {
            $allFiles = $disk->allFiles("tenants/{$tenant->id}/invoices");
            foreach ($allFiles as $candidate) {
                if (basename($candidate) === $invoice->file_name) {
                    $filePath = $candidate;
                    $invoice->update(['file_path' => $filePath]);
                    break;
                }
            }
        }

        abort_unless($disk->exists($filePath), 404, 'File missing from storage.');

        $fullPath = $disk->path($filePath);

        return response()->file($fullPath, [
            'Content-Type' => $invoice->mime_type ?? 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.($invoice->file_name ?? 'invoice.jpg').'"',
        ]);
    }
}
