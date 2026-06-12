<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ReconcileStaleSubscriptions extends Command
{
    protected $signature = 'subscriptions:reconcile-stale';

    protected $description = 'Poll Stripe for subscriptions whose status may be out of sync after a missed webhook.';

    public function handle(): int
    {
        $stripe = $this->stripe();

        $staleSubscriptions = Subscription::query()
            ->whereNotNull('stripe_subscription_id')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();

        $reconciled = 0;

        foreach ($staleSubscriptions as $subscription) {
            try {
                $stripeSub = $stripe->subscriptions->retrieve($subscription->stripe_subscription_id);
            } catch (\Throwable $e) {
                $this->warn("Could not retrieve {$subscription->stripe_subscription_id}: {$e->getMessage()}");
                continue;
            }

            $stripeStatus = $stripeSub->status;

            $statusMap = [
                'active'            => 'active',
                'past_due'          => 'past_due',
                'unpaid'            => 'unpaid',
                'canceled'          => 'cancelled',
                'incomplete'        => 'incomplete',
                'incomplete_expired' => 'expired',
                'trialing'          => 'trialing',
                'paused'            => 'paused',
            ];

            $mappedStatus = $statusMap[$stripeStatus] ?? $stripeStatus;

            if ($subscription->status === $mappedStatus) {
                continue;
            }

            $previousStatus = $subscription->status;

            $updates = [
                'status'            => $mappedStatus,
                'next_billing_date' => isset($stripeSub->current_period_end)
                    ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                    : $subscription->next_billing_date,
                'auto_renew'        => !($stripeSub->cancel_at_period_end ?? false),
            ];

            if (in_array($mappedStatus, ['cancelled', 'expired'])) {
                $updates['cancelled_at'] = $subscription->cancelled_at ?? Carbon::now();
                $updates['auto_renew'] = false;
            }

            $subscription->update($updates);

            $vendor = $subscription->vendor;
            if ($vendor) {
                if ($mappedStatus === 'active' && (!$vendor->status || $vendor->status === 'pending')) {
                    $vendor->update(['status' => 'active']);
                    $this->info("  → Activated vendor #{$vendor->id}");
                } elseif (in_array($mappedStatus, ['cancelled', 'expired']) && $vendor->status === 'active') {
                    $vendor->update(['status' => 'pending']);
                    $this->info("  → Deactivated vendor #{$vendor->id}");
                }
            }

            SubscriptionEvent::create([
                'subscription_id' => $subscription->id,
                'event_type'      => 'status_reconciled',
                'metadata'        => [
                    'previous_status' => $previousStatus,
                    'new_status'      => $mappedStatus,
                    'stripe_status'   => $stripeStatus,
                    'source'          => 'reconcile_command',
                ],
            ]);

            $reconciled++;
            $this->info("Reconciled subscription #{$subscription->id} (vendor #{$subscription->vendor_id}): {$previousStatus} → {$mappedStatus}");
        }

        $this->info("Reconciled {$reconciled} stale subscription(s).");

        return self::SUCCESS;
    }

    private function stripe(): StripeClient
    {
        $secret = trim((string) config('services.stripe.secret'));

        if ($secret === '') {
            throw new ServiceUnavailableHttpException(
                null,
                'Stripe is not configured. Set STRIPE_SECRET and clear Laravel config cache.'
            );
        }

        return new StripeClient($secret);
    }
}
