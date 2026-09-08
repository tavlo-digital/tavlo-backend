<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vendor-entered costs that have no other real data source in Tavlo —
     * rent, repairs, new equipment, insurance, etc. `is_recurring` +
     * `recurrence_frequency` are metadata only (labels the entry, e.g.
     * "this rent payment repeats monthly") — this does NOT auto-generate
     * future rows; the vendor logs each real payment as it happens, same
     * cash-basis philosophy as the rest of the Financial Reports feature.
     */
    public function up(): void
    {
        Schema::create('financial_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 40);
            $table->string('payee')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30)->nullable();
            $table->timestamp('occurred_at');
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency', 20)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_expenses');
    }
};
