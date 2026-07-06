<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->change();
            $table->string('stripe_payment_intent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable(false)->change();
            $table->string('stripe_payment_intent_id')->nullable(false)->change();
        });
    }
};
