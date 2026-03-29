<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('low_stock_alerts')->default(true);
            $table->boolean('auto_reorder_enabled')->default(false);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->boolean('track_nutrition')->default(true);
            $table->boolean('link_menu_items')->default(true);
            $table->json('settings')->nullable(); // any extra settings
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_settings');
    }
};
