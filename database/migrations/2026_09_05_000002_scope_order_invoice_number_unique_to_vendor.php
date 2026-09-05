<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice numbers are minted per vendor — each vendor carries its own prefix
 * and counter in vendor_settings — but the constraint added alongside the
 * column checked invoice_number platform-wide. Two vendors on the default
 * INV/1001 therefore raced for one number: the first to confirm a payment
 * claimed INV-0001001 and the second collided on it. The failure did not
 * settle, either. Allocation runs as a savepoint inside the caller's
 * transaction, so the collision rolled the counter increment back with it and
 * the next attempt recomputed the same doomed number, blocking that vendor's
 * payment confirmation for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_invoice_number_unique');
            $table->unique(['vendor_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['vendor_id', 'invoice_number']);
            $table->unique('invoice_number');
        });
    }
};
