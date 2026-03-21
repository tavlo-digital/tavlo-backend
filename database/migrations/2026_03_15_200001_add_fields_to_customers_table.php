<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('customer_public_id')->unique()->after('id');
            $table->string('account_type')->default('registered')->after('password');
            $table->string('risk_level')->default('none')->after('account_type');
            $table->string('risk_tooltip')->nullable()->after('risk_level');
            $table->unsignedInteger('orders_count')->default(0)->after('risk_tooltip');
            $table->decimal('total_spend', 12, 2)->default(0)->after('orders_count');
            $table->timestamp('last_active_at')->nullable()->after('total_spend');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_public_id',
                'account_type',
                'risk_level',
                'risk_tooltip',
                'orders_count',
                'total_spend',
                'last_active_at',
            ]);
        });
    }
};
