<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tenant isolation: null means super-admin, otherwise scoped to tenant
            $table->foreignId('tenant_id')
                  ->nullable()
                  ->constrained('tenants')
                  ->nullOnDelete()
                  ->after('id');

            // Role within tenant context
            $table->string('role')->default('Owner')->after('tenant_id');

            // Platform-level super admin flag
            $table->boolean('is_super_admin')->default(false)->after('role');

            // Extra profile fields
            $table->string('phone')->nullable()->after('is_super_admin');
            $table->enum('status', ['Active', 'Inactive', 'Suspended'])->default('Active')->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'role', 'is_super_admin', 'phone', 'status']);
        });
    }
};
