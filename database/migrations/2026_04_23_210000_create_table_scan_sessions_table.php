<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_scan_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_table_id')->constrained('restaurant_tables')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('pin', 4);
            $table->enum('status', ['active', 'closed', 'expired'])->default('active');
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['restaurant_table_id', 'status']);

            // PIN must be unique among all currently-active sessions across the system.
            // We enforce that in application code (loop until unique) because MySQL
            // partial-unique indexes are not supported portably.
            $table->index(['status', 'pin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_scan_sessions');
    }
};
