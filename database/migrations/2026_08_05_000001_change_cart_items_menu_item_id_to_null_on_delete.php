<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);

            $table->unsignedBigInteger('menu_item_id')->nullable()->change();

            $table->foreign('menu_item_id')
                ->references('id')
                ->on('menu_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);

            $table->unsignedBigInteger('menu_item_id')->nullable(false)->change();

            $table->foreign('menu_item_id')
                ->references('id')
                ->on('menu_items')
                ->cascadeOnDelete();
        });
    }
};
