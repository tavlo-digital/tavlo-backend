<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->json('order_ids')->nullable()->after('notes');
            $table->timestamp('preparing_start_at')->nullable()->after('order_ids');
            $table->timestamp('ready_at')->nullable()->after('preparing_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['order_ids', 'preparing_start_at', 'ready_at']);
        });
    }
};
