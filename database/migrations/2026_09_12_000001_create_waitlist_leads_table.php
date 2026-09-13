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
        Schema::create('waitlist_leads', function (Blueprint $table) {
            $table->id();
            $table->string('business_name')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('business_type')->default('Supermarket');
            $table->string('type')->default('waitlist'); // waitlist | callback_request | demo_inquiry
            $table->text('message')->nullable();
            $table->string('status')->default('Pending'); // Pending | Contacted | Converted | Archived
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_leads');
    }
};
