<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('kitchen_released_at')->nullable()->after('confirmed_at');
            $table->index('kitchen_released_at', 'orders_kitchen_released_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_kitchen_released_at_index');
            $table->dropColumn('kitchen_released_at');
        });
    }
};
