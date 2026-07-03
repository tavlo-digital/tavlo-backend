<?php

namespace App\Console\Commands;

use App\Models\OrderPayment;
use App\Services\StripePaymentService;
use Illuminate\Console\Command;

class ReconcileStalePayments extends Command
{
    protected $signature = 'payments:reconcile-stale';

    protected $description = 'Poll Stripe for orders stuck in payment_pending after webhook delivery may have failed.';

    public function handle(StripePaymentService $stripe): int
    {
        $stalePayments = OrderPayment::query()
            ->where('status', '!=', 'succeeded')
            ->where('created_at', '<', now()->subMinutes(10))
            ->whereHas('order', fn ($q) => $q
                ->where('payment_received', false)
                ->where('payment_pending', true)
            )
            ->with(['order', 'orders'])
            ->get();

        $reconciled = 0;

        foreach ($stalePayments as $payment) {
            try {
                $intent = $stripe->retrievePaymentIntent($payment->stripe_payment_intent_id);
            } catch (\Throwable $e) {
                $this->warn("Could not retrieve {$payment->stripe_payment_intent_id}: {$e->getMessage()}");
                continue;
            }

            $stripeStatus = $intent['status'] ?? null;
            $orders = $payment->orders->isNotEmpty()
                ? $payment->orders
                : collect([$payment->order])->filter();

            if ($stripeStatus === 'succeeded') {
                $now = now();

                $payment->update([
                    'status' => 'succeeded',
                    'payment_method' => $intent['payment_method'] ?? $payment->payment_method,
                    'last_verified_at' => $now,
                    'paid_at' => $payment->paid_at ?? $now,
                ]);

                foreach ($orders as $order) {
                    $order->update([
                        'payment_method' => 'stripe',
                        'transaction_id' => $payment->stripe_payment_intent_id,
                        'payment_pending' => false,
                        'payment_received' => true,
                        'payment_confirmed_at' => $order->payment_confirmed_at ?? $now,
                    ]);
                }

                $reconciled++;
                $this->info("Reconciled {$orders->count()} order(s) via {$payment->stripe_payment_intent_id}");
            } elseif (in_array($stripeStatus, ['canceled', 'requires_payment_method'], true)) {
                $payment->update([
                    'status' => $stripeStatus === 'canceled' ? 'canceled' : 'failed',
                    'last_verified_at' => now(),
                    'failed_at' => $payment->failed_at ?? now(),
                ]);

                foreach ($orders as $order) {
                    $order->update([
                        'payment_pending' => false,
                        'payment_received' => false,
                        'payment_note' => 'Stripe payment was not completed.',
                    ]);
                }
            }
        }

        $this->info("Reconciled {$reconciled} stale payment(s).");

        return self::SUCCESS;
    }
}
