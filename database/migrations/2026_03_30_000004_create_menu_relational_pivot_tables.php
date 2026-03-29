<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Menu Item Translations ───────────────────────────────────────────
        Schema::create('menu_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('language', 10);              // en, de, tr, ar, fr ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['menu_item_id', 'language']);
            $table->index('menu_item_id');
        });

        // ── Menu Item Allergens pivot ────────────────────────────────────────
        Schema::create('menu_item_allergens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['menu_item_id', 'allergen_id']);
        });

        // ── Menu Item Tags pivot ─────────────────────────────────────────────
        Schema::create('menu_item_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('special_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['menu_item_id', 'special_tag_id']);
        });

        // ── Menu Item Ingredients (recipe mapping) ───────────────────────────
        Schema::create('menu_item_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->decimal('quantity', 10, 3);          // quantity per serving
            $table->string('unit', 20)->default('g');    // g, kg, ml, piece
            $table->boolean('is_critical')->default(false); // auto-disable item when stock=0
            $table->timestamps();

            $table->unique(['menu_item_id', 'inventory_item_id']);
            $table->index('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_ingredients');
        Schema::dropIfExists('menu_item_tags');
        Schema::dropIfExists('menu_item_allergens');
        Schema::dropIfExists('menu_item_translations');
    }
};
