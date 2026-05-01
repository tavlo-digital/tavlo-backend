<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('order_public_id')
                    ->constrained('customers')
                    ->nullOnDelete();

                $table->index('customer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
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
    }
};
