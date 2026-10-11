<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'device_policy')) {
                $table->jsonb('device_policy')->nullable()->after('custom_modules');
            }
            if (! Schema::hasColumn('tenants', 'network_policy')) {
                $table->jsonb('network_policy')->nullable()->after('device_policy');
            }
            if (! Schema::hasColumn('tenants', 'settings')) {
                $table->jsonb('settings')->nullable()->after('network_policy');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $colsToDrop = [];
            if (Schema::hasColumn('tenants', 'device_policy')) $colsToDrop[] = 'device_policy';
            if (Schema::hasColumn('tenants', 'network_policy')) $colsToDrop[] = 'network_policy';
            if (Schema::hasColumn('tenants', 'settings')) $colsToDrop[] = 'settings';
            if (! empty($colsToDrop)) {
                $table->dropColumn($colsToDrop);
            }
        });
    }
};
