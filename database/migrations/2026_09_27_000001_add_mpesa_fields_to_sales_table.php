<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('mpesa_code')->nullable()->after('payment_method');
            $table->string('mpesa_payer_name')->nullable()->after('mpesa_code');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['mpesa_code', 'mpesa_payer_name']);
        });
    }
};
