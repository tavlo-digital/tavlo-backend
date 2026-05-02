<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'guest_count',
                'items_count',
                'items',
                'shared_items',
                'ready_at',
                'picked_up_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('guest_count')->default(1);
            $table->unsignedInteger('items_count')->default(0);
            $table->json('items')->nullable();
            $table->json('shared_items')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
        });
    }
};
