<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('order_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('orders')
                ->nullOnDelete();

            $table->index(['table_scan_session_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex(['table_scan_session_id', 'order_id']);
            $table->dropForeign(['order_id']);
            $table->dropColumn('order_id');
        });
    }
};
