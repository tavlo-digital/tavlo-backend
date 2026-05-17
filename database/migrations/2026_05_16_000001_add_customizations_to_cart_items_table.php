<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->json('paid_addons')->nullable()->after('notes');
            $table->json('free_addons')->nullable()->after('paid_addons');
            $table->json('removed_items')->nullable()->after('free_addons');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['paid_addons', 'free_addons', 'removed_items']);
        });
    }
};
