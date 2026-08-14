<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_order_public_id')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('supplier_id');
            $table->string('supplier_name');
            $table->string('supplier_email')->nullable();
            $table->string('supplier_phone', 50)->nullable();
            $table->string('ordering_method', 20);
            $table->string('ordering_url', 2048)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->string('currency', 3);
            $table->date('estimated_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->text('dispatch_error')->nullable();
            $table->string('created_by_type', 30)->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status', 'created_at']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->timestamp('inventory_deducted_at')->nullable()->after('picked_up_at');
            $table->index('inventory_deducted_at');
        });

        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('inventory_item_id')->constrained()->nullOnDelete();
            $table->foreignId('cart_item_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->unique(['cart_item_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_stock_movements', function (Blueprint $table) {
            $table->dropUnique(['cart_item_id', 'inventory_item_id']);
            $table->dropConstrainedForeignId('cart_item_id');
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex(['inventory_deducted_at']);
            $table->dropColumn('inventory_deducted_at');
        });

        Schema::dropIfExists('inventory_purchase_orders');
    }
};
