<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
            $table->dropIndex(['menu_item_id']);
            $table->dropColumn('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->index('menu_item_id');
        });
    }
};
