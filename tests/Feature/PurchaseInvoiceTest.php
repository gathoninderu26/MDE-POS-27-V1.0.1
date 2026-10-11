<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Supplier $supplier;

    private Branch $branch;

    private Product $product;

    private Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::create([
            'name' => 'Test Retailer',
            'slug' => 'test-retailer',
            'email' => 'retailer@example.com',
            'business_type' => 'Retail',
            'status' => 'Active',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'Manager',
        ]);

        $this->supplier = Supplier::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Brookside Dairies Ltd',
            'status' => 'Active',
        ]);

        $this->branch = Branch::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Westlands Branch',
            'status' => 'Active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'name' => 'Fresh Milk 500ml',
            'sku' => 'MLK-001',
            'cost_price' => 50.00,
            'selling_price' => 65.00,
            'stock_quantity' => 10,
            'status' => 'Active',
        ]);

        $structuredNotes = json_encode([
            [
                'product_id' => $this->product->id,
                'name' => 'Fresh Milk 500ml',
                'sku' => 'MLK-001',
                'package_unit' => 'Cartons',
                'packs' => 5,
                'quantity' => 50,
                'unit_cost' => 50.00,
                'total' => 2500.00,
            ],
        ]);

        $this->purchase = Purchase::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'po_number' => 'PO-0001',
            'order_date' => now()->toDateString(),
            'items_description' => 'Fresh Milk 500ml (5 Cartons / 50 units)',
            'total_amount' => 2500.00,
            'status' => 'Pending',
            'notes' => $structuredNotes,
        ]);
    }

    public function test_invoice_parser_service_extracts_etims_qr_payload(): void
    {
        $service = new InvoiceParserService;
        $qrPayload = 'https://etims.kra.go.ke/verify?inv=INV-2026-9901&pin=P051234567Z&tot=2500.00&date=2026-09-14';

        $parsed = $service->parseQrPayload($qrPayload);

        $this->assertTrue($parsed['is_detected']);
        $this->assertEquals('INV-2026-9901', $parsed['invoice_number']);
        $this->assertEquals('P051234567Z', $parsed['tax_pin']);
        $this->assertEquals(2500.00, $parsed['total_amount']);
        $this->assertEquals('2026-09-14', $parsed['invoice_date']);
    }

    public function test_invoice_parser_service_detects_price_and_quantity_variance(): void
    {
        $service = new InvoiceParserService;

        // Supplier billed 60 units @ 55 instead of 50 units @ 50
        $invoicedItems = [
            [
                'name' => 'Fresh Milk 500ml',
                'sku' => 'MLK-001',
                'quantity' => 60,
                'unit_cost' => 55.00,
                'total' => 3300.00,
            ],
        ];

        $comparison = $service->compare($this->purchase, $invoicedItems, 3300.00);

        $this->assertEquals('PRICE_VARIANCE', $comparison['reconciliation_status']);
        $this->assertEquals(800.00, $comparison['variance_total']);
        $this->assertCount(1, $comparison['rows']);
        $this->assertEquals('BOTH_VARIANCE', $comparison['rows'][0]['status']);
    }

    public function test_user_can_upload_physical_invoice_and_receive_comparison(): void
    {
        $file = UploadedFile::fake()->create('invoice_scan.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->user)
            ->withSession(['current_tenant_id' => $this->tenant->id])
            ->postJson("/purchases/{$this->purchase->id}/invoice/upload", [
                'invoice_file' => $file,
                'qr_payload' => 'https://etims.kra.go.ke/verify?inv=INV-9081&pin=P051234567Z&tot=2500',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'qr' => [
                'invoice_number' => 'INV-9081',
                'tax_pin' => 'P051234567Z',
            ],
        ]);

        $this->assertNotNull($response->json('file_path'));
        Storage::disk('local')->assertExists($response->json('file_path'));
    }

    public function test_user_can_reconcile_invoice_and_auto_receive_inventory(): void
    {
        $filePath = "tenants/{$this->tenant->id}/invoices/test.jpg";
        Storage::disk('local')->put($filePath, 'fake-image-content');

        $initialStock = $this->product->stock_quantity; // 10

        $response = $this->actingAs($this->user)
            ->withSession(['current_tenant_id' => $this->tenant->id])
            ->post("/purchases/{$this->purchase->id}/invoice/reconcile", [
                'file_path' => $filePath,
                'file_name' => 'invoice.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'invoice_number' => 'INV-BROOKSIDE-401',
                'invoice_date' => '2026-09-14',
                'tax_pin' => 'P051234567Z',
                'total_amount' => 2500.00,
                'items' => [
                    [
                        'name' => 'Fresh Milk 500ml',
                        'sku' => 'MLK-001',
                        'product_id' => $this->product->id,
                        'quantity' => 50,
                        'unit_cost' => 50.00,
                        'total' => 2500.00,
                    ],
                ],
                'auto_receive_stock' => true,
            ]);

        $response->assertRedirect();

        // Assert PurchaseInvoice created
        $this->assertDatabaseHas('purchase_invoices', [
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $this->purchase->id,
            'invoice_number' => 'INV-BROOKSIDE-401',
            'total_amount' => 2500.00,
            'status' => 'Verified',
        ]);

        // Assert Purchase status updated to Received
        $this->assertEquals('Received', $this->purchase->fresh()->status);

        // Assert inventory stock increased by 50 units (10 + 50 = 60)
        $this->assertEquals($initialStock + 50, $this->product->fresh()->stock_quantity);
    }

    public function test_user_can_view_stored_invoice_document(): void
    {
        $filePath = "tenants/{$this->tenant->id}/invoices/receipt.jpg";
        Storage::disk('local')->put($filePath, 'image-bytes');

        PurchaseInvoice::create([
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $this->purchase->id,
            'invoice_number' => 'INV-100',
            'total_amount' => 2500.00,
            'file_path' => $filePath,
            'file_name' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'reconciliation_status' => 'MATCHED',
            'status' => 'Verified',
        ]);

        $response = $this->actingAs($this->user)
            ->withSession(['current_tenant_id' => $this->tenant->id])
            ->get("/purchases/{$this->purchase->id}/invoice/document");

        $response->assertOk();
    }

    public function test_user_can_store_invoice_softcopy_directly_without_extracted_lines(): void
    {
        $file = UploadedFile::fake()->create('raw_physical_scan.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->withSession(['current_tenant_id' => $this->tenant->id])
            ->postJson("/purchases/{$this->purchase->id}/invoice/upload", [
                'invoice_file' => $file,
                'attach_only' => true,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'attached' => true,
        ]);

        $this->assertDatabaseHas('purchase_invoices', [
            'tenant_id' => $this->tenant->id,
            'purchase_id' => $this->purchase->id,
            'reconciliation_status' => 'ATTACHED_ONLY',
            'status' => 'Verified',
        ]);
    }
}
