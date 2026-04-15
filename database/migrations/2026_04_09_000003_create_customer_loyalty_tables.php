<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_balance')->default(0);
            $table->unsignedInteger('total_earned')->default(0);
            $table->unsignedInteger('total_redeemed')->default(0);
            $table->timestamps();

            $table->unique(['customer_id', 'vendor_id']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // earned, redeemed
            $table->integer('points');
            $table->string('reference_type')->nullable(); // order, redemption
            $table->unsignedBigInteger('reference_id')->nullable(); // order_id etc.
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('customer_loyalty_points');
    }
};
