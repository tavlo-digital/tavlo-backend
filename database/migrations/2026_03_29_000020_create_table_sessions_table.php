<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            // null for takeaway sessions
            $table->unsignedInteger('table_number')->nullable();
            $table->string('table_name')->nullable();

            // closed | active
            $table->string('status')->default('active');

            // Batch window: seconds after first order before releasing to kitchen
            // null = order was released immediately (or takeaway)
            $table->timestamp('batch_started_at')->nullable();
            $table->unsignedInteger('batch_window_seconds')->default(90);
            $table->timestamp('batch_released_at')->nullable();

            // Tracks course progress: drinks, appetizers, mains, desserts
            $table->string('current_course')->nullable(); // drinks|appetizers|mains|desserts

            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index(['vendor_id', 'table_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
