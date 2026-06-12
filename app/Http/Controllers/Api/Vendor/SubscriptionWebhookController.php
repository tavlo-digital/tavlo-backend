<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\StripeWebhookLog;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class SubscriptionWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature') ?? '',
                config('services.stripe.subscription_webhook_secret')
            );
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            StripeWebhookLog::create([
                'event_type'    => 'unknown',
                'http_status'   => 400,
                'outcome'       => 'signature_invalid',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $type = $event->type;
        $object = $event->data->object;

        $result = match ($type) {
            'checkout.session.completed'        => $this->handleCheckoutCompleted($object),
            'checkout.session.expired'          => $this->handleCheckoutExpired($object),
            'customer.subscription.updated'     => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted'     => $this->handleSubscriptionDeleted($object),
            'invoice.paid'                      => $this->handleInvoicePaid($object),
            'invoice.payment_failed'            => $this->handleInvoicePaymentFailed($object),
            default                             => 'ignored',
        };

        StripeWebhookLog::create([
            'event_type'      => $type,
            'stripe_event_id' => $event->id,
            'http_status'     => 200,
            'outcome'         => $result,
            'metadata'        => [
                'object_id' => $object->id ?? null,
            ],
        ]);

        return response()->json(['received' => true]);
    }

    private function handleCheckoutCompleted(object $session): string
    {
        if ($session->mode !== 'subscription') {
            return 'ignored_non_subscription';
        }

        $vendorId = $session->metadata->vendor_id ?? $session->client_reference_id ?? null;
        $planId   = $session->metadata->plan_id ?? null;
        $cycle    = $session->metadata->cycle ?? 'monthly';

        if (!$vendorId) {
            return 'missing_vendor_id';
        }

        $vendor = Vendor::find($vendorId);
        if (!$vendor) {
            return 'vendor_not_found';
        }

        $existing = $vendor->subscriptions()->latest()->first();
        if ($existing && $existing->stripe_subscription_id === $session->subscription) {
            return 'already_processed';
        }

        if ($existing && $existing->status === 'active') {
            $existing->update([
                'status'                  => 'superseded',
                'cancelled_at'            => Carbon::now(),
            ]);
        }

        $subscription = Subscription::create([
            'vendor_id'              => $vendor->id,
            'plan_id'                => $planId,
            'status'                 => 'active',
            'billing_cycle'          => $cycle,
            'start_date'             => Carbon::now(),
            'next_billing_date'      => $cycle === 'yearly' ? Carbon::now()->addYear() : Carbon::now()->addMonth(),
            'auto_renew'             => true,
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id'     => $session->customer,
        ]);

        if (!$vendor->status || $vendor->status === 'pending') {
            $vendor->update(['status' => 'active']);
        }

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type'      => 'subscription_created',
            'new_plan_id'     => $planId,
            'metadata'        => [
                'source'              => 'stripe_checkout',
                'checkout_session_id' => $session->id,
            ],
        ]);

        return 'subscription_created';
    }

    private function handleCheckoutExpired(object $session): string
    {
        $vendorId = $session->metadata->vendor_id ?? $session->client_reference_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        StripeWebhookLog::create([
            'event_type'      => 'checkout.session.expired',
            'stripe_event_id' => $session->id,
            'http_status'     => 200,
            'outcome'         => 'checkout_expired',
            'metadata'        => [
                'vendor_id' => $vendorId,
                'plan_id'   => $planId,
            ],
        ]);

        return 'checkout_expired';
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): string
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return 'subscription_not_found';
        }

        $stripeStatus = $stripeSubscription->status;
        $statusMap = [
            'active'   => 'active',
            'past_due' => 'past_due',
            'unpaid'   => 'unpaid',
            'paused'   => 'paused',
            'trialing' => 'trialing',
        ];

        $newStatus = $statusMap[$stripeStatus] ?? $stripeStatus;

        $subscription->update([
            'status'            => $newStatus,
            'next_billing_date' => isset($stripeSubscription->current_period_end)
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                : $subscription->next_billing_date,
            'auto_renew'        => !($stripeSubscription->cancel_at_period_end ?? false),
        ]);

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type'      => 'subscription_updated',
            'metadata'        => [
                'stripe_status' => $stripeStatus,
                'source'        => 'stripe_webhook',
            ],
        ]);

        return 'subscription_updated';
    }

    private function handleSubscriptionDeleted(object $stripeSubscription): string
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSubscription->id)->first();

        if (!$subscription) {
            return 'subscription_not_found';
        }

        $subscription->update([
            'status'       => 'cancelled',
            'cancelled_at' => Carbon::now(),
            'auto_renew'   => false,
        ]);

        $vendor = $subscription->vendor;
        if ($vendor && $vendor->status === 'active') {
            $hasOtherActive = $vendor->subscriptions()
                ->where('id', '!=', $subscription->id)
                ->where('status', 'active')
                ->exists();

            if (!$hasOtherActive) {
                $vendor->update(['status' => 'pending']);
            }
        }

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type'      => 'subscription_cancelled',
            'metadata'        => ['source' => 'stripe_webhook'],
        ]);

        return 'subscription_cancelled';
    }

    private function handleInvoicePaid(object $stripeInvoice): string
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();

        if (!$subscription) {
            return 'subscription_not_found';
        }

        Invoice::updateOrCreate(
            ['stripe_invoice_id' => $stripeInvoice->id],
            [
                'subscription_id'      => $subscription->id,
                'invoice_number'       => $stripeInvoice->number,
                'amount'               => $stripeInvoice->amount_paid / 100,
                'vat'                  => ($stripeInvoice->tax ?? 0) / 100,
                'currency'             => strtoupper($stripeInvoice->currency),
                'status'               => 'paid',
                'billing_period_start' => isset($stripeInvoice->period_start) ? Carbon::createFromTimestamp($stripeInvoice->period_start) : null,
                'billing_period_end'   => isset($stripeInvoice->period_end) ? Carbon::createFromTimestamp($stripeInvoice->period_end) : null,
                'due_date'             => isset($stripeInvoice->due_date) ? Carbon::createFromTimestamp($stripeInvoice->due_date) : null,
                'paid_at'              => Carbon::now(),
                'pdf_url'              => $stripeInvoice->invoice_pdf ?? null,
                'stripe_hosted_url'    => $stripeInvoice->hosted_invoice_url ?? null,
            ]
        );

        if ($subscription->status !== 'active') {
            $subscription->update(['status' => 'active']);
        }

        return 'invoice_recorded';
    }

    private function handleInvoicePaymentFailed(object $stripeInvoice): string
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeInvoice->subscription)->first();

        if (!$subscription) {
            return 'subscription_not_found';
        }

        $subscription->update(['status' => 'past_due']);

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type'      => 'payment_failed',
            'metadata'        => [
                'stripe_invoice_id' => $stripeInvoice->id,
                'attempt_count'     => $stripeInvoice->attempt_count ?? null,
                'source'            => 'stripe_webhook',
            ],
        ]);

        return 'payment_failed_recorded';
    }
}
