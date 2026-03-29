<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_active (soft-delete flag) to menu_items
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('sort_order');
            $table->index('is_active');
        });

        // Add tax_category_id FK to menu_categories
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_category_id')->nullable()->after('default_tax_category');
            $table->foreign('tax_category_id')->references('id')->on('tax_categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropForeign(['tax_category_id']);
            $table->dropColumn('tax_category_id');
        });
    }
};
