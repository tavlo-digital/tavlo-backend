<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISSUE-013: Lock item price at cart-add time to prevent live price changes
 * silently affecting active carts (e.g., happy-hour ending mid-session).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            // Stores the unit price (after discount if applicable) at the moment
            // the item was added to the cart. NULL for legacy rows — those fall back
            // to the live menu item price for backwards compatibility.
            $table->decimal('stored_unit_price', 10, 2)->nullable()->after('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn('stored_unit_price');
        });
    }
};
