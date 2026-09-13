<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kra_pin')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('country')->default('Kenya');
            $table->string('currency')->default('KES');
            $table->string('business_type')->default('Supermarket');
            $table->enum('plan', ['Basic', 'Pro', 'Enterprise'])->default('Pro');
            $table->enum('billing_cycle', ['Weekly', 'Monthly', 'Quarterly', 'Semi-Annual', 'Annual'])->default('Monthly');
            $table->enum('status', ['Active', 'Suspended', 'Trial', 'Expired'])->default('Trial');
            $table->string('address')->nullable();
            $table->unsignedInteger('branch_count')->default(1);
            $table->unsignedInteger('user_count')->default(5);
            $table->unsignedInteger('storage_used_mb')->default(0);
            $table->unsignedInteger('storage_quota_mb')->default(5120);
            $table->decimal('monthly_revenue', 12, 2)->default(0);
            $table->json('enabled_modules')->nullable();
            $table->json('custom_modules')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
