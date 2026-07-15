<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->index(
                ['customer_id', 'status', 'scanned_at'],
                'table_sessions_customer_active_index',
            );
            $table->index(
                ['vendor_id', 'restaurant_table_id', 'status'],
                'table_sessions_table_active_index',
            );
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(
                ['parent_order_id', 'payment_received', 'status'],
                'orders_unpaid_side_order_index',
            );
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->index('order_id', 'cart_items_order_id_index');
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->index(
                ['order_id', 'status'],
                'order_payments_order_status_index',
            );
        });

        // The sharing graph is stored as JSON. PostgreSQL can use this GIN
        // expression index for whereJsonContains(shared_order_ids, ...).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE INDEX cart_items_shared_order_ids_gin_index '
                .'ON cart_items USING GIN ((shared_order_ids::jsonb))',
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS cart_items_shared_order_ids_gin_index');
        }

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropIndex('order_payments_order_status_index');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_order_id_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_unpaid_side_order_index');
        });

        Schema::table('table_scan_sessions', function (Blueprint $table) {
            $table->dropIndex('table_sessions_customer_active_index');
            $table->dropIndex('table_sessions_table_active_index');
        });
    }
};
