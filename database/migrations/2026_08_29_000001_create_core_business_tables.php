<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->default('Main HQ');
            $table->string('location')->nullable();
            $table->string('phone')->nullable();
            $table->string('manager')->nullable();
            $table->decimal('current_stock_value', 12, 2)->default(0);
            $table->string('operating_hours')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->string('status')->default('Active');
            $table->boolean('is_main')->default(false);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('category')->nullable();
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(5);
            $table->string('unit')->default('pcs');
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->string('payment_method')->default('Cash');
            $table->string('payment_status')->default('Completed');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('clock_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('clocked_in_at');
            $table->timestamp('clocked_out_at')->nullable();
            $table->string('status')->default('Active'); // Active, Completed
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('category')->default('General');
            $table->decimal('amount', 10, 2)->default(0);
            $table->date('date');
            $table->string('payment_method')->default('Cash');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('clock_sessions');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
        Schema::dropIfExists('branches');
    }
};
