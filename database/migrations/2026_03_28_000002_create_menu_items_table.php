<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('image_url')->nullable();
            $table->boolean('available')->default(true);

            // Nutrition
            $table->unsignedInteger('calories')->default(0);
            $table->decimal('fat', 8, 2)->default(0);
            $table->decimal('carbs', 8, 2)->default(0);
            $table->decimal('protein', 8, 2)->default(0);

            // Tax
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->string('tax_category')->default('food'); // food, drinks_non_alcoholic, drinks_alcoholic, takeaway

            // Dietary & allergens
            $table->string('dietary_preference')->nullable(); // vegan, vegetarian, gluten-free, none
            $table->json('allergies')->nullable();       // ["gluten","dairy",...]
            $table->json('special_tags')->nullable();     // ["chef_special","popular",...]

            // Discount
            $table->boolean('has_discount')->default(false);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discounted_price', 10, 2)->nullable();

            // Addons / removable items
            $table->json('paid_addons')->nullable();      // [{name,price},...]
            $table->json('free_addons')->nullable();       // ["extra sauce",...]
            $table->json('removable_items')->nullable();   // ["onions",...]

            // Translations (JSON blob keyed by locale)
            $table->json('translations')->nullable();

            // Ingredients (recipe link with quantities)
            $table->json('ingredients')->nullable(); // [{ingredientId, ingredientName, quantity, unit, isCritical},...]

            // Stats
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('ordered_count')->default(0);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('vendor_id');
            $table->index('menu_category_id');
            $table->index('available');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
