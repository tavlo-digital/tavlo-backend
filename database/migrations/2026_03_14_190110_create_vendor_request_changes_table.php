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
        Schema::create('vendor_request_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('restaurant_name')->nullable();
            $table->string('legal_entity_name')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->text('admin_notes')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->string('status', 20)->default('pending'); // 'pending' | 'approved' | 'rejected'
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_request_changes');
    }
};
