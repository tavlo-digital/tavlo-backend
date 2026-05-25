<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropUnique('menu_categories_vendor_id_slug_unique');
            $table->dropColumn(['name', 'slug']);
            $table->unique(['vendor_id', 'master_menu_category_id'], 'menu_categories_vendor_master_unique');
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropUnique('menu_categories_vendor_master_unique');
            $table->string('name')->nullable()->after('master_menu_category_id');
            $table->string('slug')->nullable()->after('name');
            $table->unique(['vendor_id', 'slug']);
        });
    }
};
