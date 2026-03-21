<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('vendor_public_id');
            $table->unsignedInteger('orders_count')->default(0)->after('risk_level');
            $table->decimal('revenue_total', 12, 2)->default(0)->after('orders_count');
            $table->unsignedInteger('users_used')->default(0)->after('revenue_total');
            $table->string('payment_status')->default('paid')->after('users_used');
            $table->timestamp('payment_last_success')->nullable()->after('payment_status');
            $table->unsignedInteger('payment_failures_24h')->default(0)->after('payment_last_success');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'orders_count',
                'revenue_total',
                'users_used',
                'payment_status',
                'payment_last_success',
                'payment_failures_24h',
            ]);
        });
    }
};
