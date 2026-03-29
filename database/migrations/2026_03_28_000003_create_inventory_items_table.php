<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->string('unit')->default('g'); // g, ml, pieces, kg, liters
            $table->decimal('min_stock', 10, 2)->default(0);
            $table->decimal('cost_per_unit', 10, 4)->default(0);
            $table->string('supplier')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->boolean('auto_reorder')->default(false);

            // Nutrition per 100g/100ml
            $table->json('nutrition')->nullable(); // {calories, fat, protein, carbs per 100g}

            $table->timestamps();

            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
