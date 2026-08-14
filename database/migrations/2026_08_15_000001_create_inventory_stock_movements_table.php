<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('source', 100);
            $table->decimal('quantity_change', 12, 3);
            $table->decimal('quantity_before', 12, 3);
            $table->decimal('quantity_after', 12, 3);
            $table->string('actor_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_movements');
    }
};
