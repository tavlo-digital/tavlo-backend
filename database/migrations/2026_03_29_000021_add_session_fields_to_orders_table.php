<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Link order to a table_session
            $table->foreignId('table_session_id')
                ->nullable()
                ->after('vendor_id')
                ->constrained('table_sessions')
                ->nullOnDelete();

            // Whether the order has been confirmed by a waiter (for non-prepaid orders)
            $table->boolean('waiter_confirmed')->default(false)->after('table_session_id');
            $table->timestamp('waiter_confirmed_at')->nullable()->after('waiter_confirmed');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['table_session_id']);
            $table->dropColumn(['table_session_id', 'waiter_confirmed', 'waiter_confirmed_at']);
        });
    }
};
