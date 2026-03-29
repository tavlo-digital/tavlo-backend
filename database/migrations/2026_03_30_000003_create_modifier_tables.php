<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Modifier Groups ──────────────────────────────────────────────────
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');                    // "Size", "Toppings", "Cooking Level"
            $table->string('type')->default('single'); // single | multiple | remove
            $table->unsignedInteger('min_selection')->default(0);
            $table->unsignedInteger('max_selection')->default(1);
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('vendor_id');
        });

        // ── Modifier Options ─────────────────────────────────────────────────
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');                         // "Small", "Extra Cheese"
            $table->decimal('price_adjustment', 10, 2)->default(0); // 0 for free, positive for paid
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('modifier_group_id');
        });

        // ── Item ↔ Modifier Group pivot ──────────────────────────────────────
        Schema::create('menu_item_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['menu_item_id', 'modifier_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_modifier_groups');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
    }
};
