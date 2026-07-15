<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->json('order_ids')->nullable()->after('order_id');
        });

        DB::table('order_payments')
            ->select(['id', 'order_id'])
            ->orderBy('id')
            ->chunkById(500, function ($payments) {
                foreach ($payments as $payment) {
                    $orderIds = DB::table('order_payment_orders')
                        ->where('order_payment_id', $payment->id)
                        ->orderBy('order_id')
                        ->pluck('order_id')
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all();

                    if ($orderIds === [] && $payment->order_id !== null) {
                        $orderIds = [(int) $payment->order_id];
                    }

                    DB::table('order_payments')
                        ->where('id', $payment->id)
                        ->update(['order_ids' => json_encode($orderIds)]);
                }
            });

        // Older order creation paths incorrectly used payment_pending to mean
        // "unpaid". A real pending payment always has a Stripe transaction ID,
        // a cash payment method, or an active order_payments row.
        DB::table('orders')
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->whereNull('transaction_id')
            ->whereNull('payment_method')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_payments as anchor_payment')
                    ->whereColumn('anchor_payment.order_id', 'orders.id')
                    ->whereNotIn('anchor_payment.status', ['succeeded', 'canceled', 'failed', 'payment_failed']);
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('order_payment_orders as covered_order')
                    ->join('order_payments as covered_payment', 'covered_payment.id', '=', 'covered_order.order_payment_id')
                    ->whereColumn('covered_order.order_id', 'orders.id')
                    ->whereNotIn('covered_payment.status', ['succeeded', 'canceled', 'failed', 'payment_failed']);
            })
            ->update(['payment_pending' => false]);
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('order_ids');
        });
    }
};
