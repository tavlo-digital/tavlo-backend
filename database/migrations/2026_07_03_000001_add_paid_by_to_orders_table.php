<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('paid_by')
                ->nullable()
                ->after('customer_id')
                ->constrained('customers')
                ->nullOnDelete();

            $table->index('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropIndex(['paid_by']);
            $table->dropColumn('paid_by');
        });
    }
};
