<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            // Document type: 'PO' (Purchase Order) or 'LPO' (Local Purchase Order)
            $table->string('document_type')->default('PO')->after('po_number');

            // Separate LPO reference number (e.g. LPO-0001)
            $table->string('lpo_number')->nullable()->after('document_type');

            // LPO purpose / category (e.g. Maintenance, Casual Buying, Perishables)
            $table->string('lpo_purpose')->nullable()->after('lpo_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'lpo_number', 'lpo_purpose']);
        });
    }
};
