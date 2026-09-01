<?php

namespace App\Console\Commands;

use App\Models\OrderPayment;
use App\Services\PaymentGuardService;
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
            // A cash request carries no intent. It is not Stripe's to reconcile,
            // and asking Stripe about a null id only threw once a minute.
            if (! $payment->stripe_payment_intent_id) {
                continue;
            }

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

                // The same order can be under a card attempt and a standing cash
                // request. This payment is terminal now, so whatever is still
                // returned here is another live lock that has to survive it —
                // otherwise this command quietly unlocked a cash request every
                // minute and took the order off the waiter's screen.
                $stillHeldIds = PaymentGuardService::activePaymentsCovering($orders->pluck('id'))
                    ->flatMap(fn (OrderPayment $live) => $live->orders->isNotEmpty()
                        ? $live->orders->pluck('id')
                        : collect([$live->order_id]))
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique();

                foreach ($orders as $order) {
                    if ($stillHeldIds->contains((int) $order->id)) {
                        continue;
                    }

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
