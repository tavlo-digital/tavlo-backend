<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('vat_amount');
            $table->decimal('loyalty_discount', 8, 2)->default(0.00)->after('loyalty_points_redeemed');
            $table->unsignedBigInteger('promotion_id')->nullable()->after('loyalty_discount');
            $table->foreign('promotion_id')->references('id')->on('promotions')->nullOnDelete();
            $table->decimal('promotion_discount', 8, 2)->default(0.00)->after('promotion_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['promotion_id']);
            $table->dropColumn(['loyalty_points_redeemed', 'loyalty_discount', 'promotion_id', 'promotion_discount']);
        });
    }
};
