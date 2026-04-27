<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop legacy table_session_id (FK to table_sessions)
            if (Schema::hasColumn('orders', 'table_session_id')) {
                try {
                    $table->dropForeign(['table_session_id']);
                } catch (\Throwable $e) {
                    // FK may not exist
                }
                $table->dropColumn('table_session_id');
            }

            // Drop legacy customer_id (FK to customers)
            if (Schema::hasColumn('orders', 'customer_id')) {
                try {
                    $table->dropForeign(['customer_id']);
                } catch (\Throwable $e) {
                    // FK may not exist
                }
                try {
                    $table->dropIndex(['customer_id']);
                } catch (\Throwable $e) {
                    // index may not exist
                }
                $table->dropColumn('customer_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'table_scan_session_id')) {
                $table->foreignId('table_scan_session_id')
                    ->nullable()
                    ->after('vendor_id')
                    ->constrained('table_scan_sessions')
                    ->nullOnDelete();

                $table->index('table_scan_session_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'table_scan_session_id')) {
                try {
                    $table->dropForeign(['table_scan_session_id']);
                } catch (\Throwable $e) {
                }
                try {
                    $table->dropIndex(['table_scan_session_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('table_scan_session_id');
            }

            if (! Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('order_public_id')
                    ->constrained('customers')
                    ->nullOnDelete();
                $table->index('customer_id');
            }

            if (! Schema::hasColumn('orders', 'table_session_id')) {
                $table->foreignId('table_session_id')
                    ->nullable()
                    ->after('vendor_id')
                    ->constrained('table_sessions')
                    ->nullOnDelete();
            }
        });
    }
};
