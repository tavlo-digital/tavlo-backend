<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A checkout hold is the claim a customer takes on the orders they are about
 * to settle the moment they reach the payment step, before any Stripe intent
 * or cash request exists. Until then nothing stopped a second guest from
 * covering the same order or splitting one of its items out from under the
 * payer, changing the amount on the screen they were already looking at.
 *
 * payment_pending already marks an order as being settled, and every existing
 * guard and lock banner keys off it — the hold sets that flag and adds who
 * holds it and since when, so it can be released by its owner and expired if
 * they abandon the checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('checkout_hold_by')->nullable()->after('payment_pending');
            $table->timestamp('checkout_hold_at')->nullable()->after('checkout_hold_by');

            $table->index(['checkout_hold_by', 'checkout_hold_at'], 'orders_checkout_hold_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_checkout_hold_index');
            $table->dropColumn(['checkout_hold_by', 'checkout_hold_at']);
        });
    }
};
