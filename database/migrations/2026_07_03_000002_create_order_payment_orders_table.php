<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payment_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')->constrained('order_payments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->unique(['order_payment_id', 'order_id']);
            $table->index('order_id');
        });

        $now = now();

        DB::table('order_payments')
            ->select(['id', 'order_id', 'amount'])
            ->orderBy('id')
            ->chunkById(500, function ($payments) use ($now) {
                DB::table('order_payment_orders')->insert(
                    $payments->map(fn ($payment) => [
                        'order_payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'amount' => $payment->amount,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_orders');
    }
};
