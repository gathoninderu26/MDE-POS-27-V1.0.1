<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Invoice Identifiers
            $table->string('invoice_number')->index();
            $table->date('invoice_date')->nullable();
            $table->string('tax_pin')->nullable();
            $table->string('control_unit_code')->nullable();

            // Financial Summary
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('po_expected_total', 12, 2)->default(0);
            $table->decimal('variance_amount', 12, 2)->default(0);

            // Raw Scans & Extracted Data
            $table->text('raw_qr_payload')->nullable();
            $table->json('qr_metadata')->nullable();
            $table->json('extracted_items')->nullable();
            $table->json('reconciliation_matrix')->nullable();

            // Physical Document File
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->string('mime_type')->default('image/jpeg');
            $table->unsignedBigInteger('file_size')->default(0);

            // Reconciliation Status & Verification Audit
            $table->string('reconciliation_status')->default('PENDING_REVIEW'); // MATCHED, PRICE_VARIANCE, QTY_VARIANCE, DISCREPANCY, PENDING_REVIEW
            $table->string('status')->default('Verified'); // Draft, Verified, Disputed, Approved
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
