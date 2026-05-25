<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->foreignId('master_menu_category_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('master_menu_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('master_menu_category_id');
        });
    }
};
