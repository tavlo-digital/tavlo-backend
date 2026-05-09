<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Vendor;
use App\Services\StripePaymentService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController extends Controller
{
    public function __construct(private readonly StripePaymentService $stripe)
    {
    }

    /**
     * GET /api/customer/payment-methods?restaurant_id=...
     */
    public function methods(Request $request): JsonResponse
    {
        $data = $request->validate([
            'restaurant_id' => ['nullable', 'string', 'max:255'],
            'vendor_public_id' => ['nullable', 'string', 'max:255'],
        ]);

        $restaurantId = trim((string) ($data['restaurant_id'] ?? $data['vendor_public_id'] ?? ''));
        $restaurantId = trim($restaurantId, '{}');

        if ($restaurantId === '') {
            throw ValidationException::withMessages([
                'restaurant_id' => ['The restaurant id field is required.'],
            ]);
        }

        $vendor = Vendor::with('vendorSetting')
            ->where(function (Builder $query) use ($restaurantId) {
                $query->where('vendor_public_id', $restaurantId)
                    ->orWhere('slug', $restaurantId);

                if (ctype_digit($restaurantId)) {
                    $query->orWhere('id', (int) $restaurantId);
                }
            })
            ->first();

        if (! $vendor) {
            return response()->json(['message' => 'Restaurant not found.'], 404);
        }

        $settings = $vendor->vendorSetting;

        return response()->json([
            'method' => [
                'on-site' => (bool) ($settings?->accept_on_site ?? true),
                'stripe' => (bool) (
                    $settings?->stripe_enabled
                    && $settings->stripe_account_id
                    && $settings->stripe_onboarding_complete
                ),
            ],
        ]);
    }

    /**
     * POST /api/customer/payments/create-intent
     */
    public function createIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) && ! is_int($value)) {
                        $fail("The {$attribute} field must be a string or integer.");

                        return;
                    }

                    if (mb_strlen((string) $value) > 255) {
                        $fail("The {$attribute} field must not be greater than 255 characters.");
                    }
                },
            ],
            'customer_id' => ['required', 'integer'],
        ]);

        $customer = $request->user();
        $customerId = (int) $data['customer_id'];

        if ($customerId !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'customer_id' => ['The provided customer identifier does not match the authenticated customer.'],
            ]);
        }

        $order = $this->customerOrder((string) $data['order_id'], $customer->id);

        if ($order->payment_received) {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        $settings = $order->vendor?->vendorSetting;
        if (! $settings?->stripe_enabled || ! $settings->stripe_account_id || ! $settings->stripe_onboarding_complete) {
            return response()->json(['message' => 'Stripe payments are not enabled for this restaurant.'], 422);
        }

        $amount = $this->finalOrderAmount($order);
        if ($amount <= 0) {
            return response()->json(['message' => 'Order amount must be greater than zero.'], 422);
        }

        $currency = $settings->currency ?: $order->currency;
        $metadata = [
            'order_id' => (string) $order->id,
            'order_public_id' => (string) $order->order_public_id,
            'vendor_id' => (string) $order->vendor_id,
            'customer_id' => (string) $customer->id,
            'payment_for' => $order->table_scan_session_id ? 'dine_in' : 'order',
        ];

        if ($order->table_scan_session_id) {
            $metadata['table_session_id'] = (string) $order->table_scan_session_id;
        }

        $intent = $this->stripe->createPaymentIntent(
            $this->stripeAmountMinor($amount, $currency),
            $currency,
            $settings->stripe_account_id,
            $metadata
        );

        DB::transaction(function () use ($order, $customer, $settings, $intent, $amount, $currency, $metadata) {
            OrderPayment::create([
                'order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'customer_id' => $customer->id,
                'table_scan_session_id' => $order->table_scan_session_id,
                'stripe_account_id' => $settings->stripe_account_id,
                'stripe_payment_intent_id' => $intent['id'],
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'status' => $intent['status'] ?? 'pending',
                'payment_method' => $intent['payment_method'] ?? null,
                'metadata' => $metadata,
            ]);

            $order->update([
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'payment_method' => 'stripe',
                'transaction_id' => $intent['id'],
                'payment_pending' => true,
                'payment_received' => false,
            ]);
        });

        return response()->json([
            'clientSecret' => $intent['client_secret'],
            'paymentIntentId' => $intent['id'],
        ]);
    }

    /**
     * GET /api/customer/payments/verify?payment_intent=pi_xxx
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_intent' => ['required', 'string', 'max:255'],
        ]);

        $payment = OrderPayment::with('order')
            ->where('stripe_payment_intent_id', $data['payment_intent'])
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        $intent = $this->stripe->retrievePaymentIntent($data['payment_intent']);
        $this->assertIntentMatchesPayment($intent, $payment);
        $this->syncPaymentIntentStatus($payment, $intent);

        return response()->json([
            'status' => $intent['status'] ?? $payment->fresh()->status,
            'orderStatus' => $this->orderStatus($intent['status'] ?? $payment->status),
        ]);
    }

    /**
     * POST /api/customer/payments/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $event = $this->stripe->parseWebhookEvent(
                $request->getContent(),
                $request->header('Stripe-Signature')
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $type = $event['type'];
        if (! in_array($type, [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'payment_intent.processing',
        ], true)) {
            return response()->json(['received' => true]);
        }

        $intent = $event['payment_intent'];
        $payment = OrderPayment::with('order')
            ->where('stripe_payment_intent_id', $intent['id'])
            ->first();

        if (! $payment) {
            return response()->json(['received' => true, 'ignored' => true]);
        }

        $this->syncPaymentIntentStatus($payment, $intent, $type);

        return response()->json(['received' => true]);
    }

    private function customerOrder(string $orderId, int $customerId): Order
    {
        return Order::with([
            'vendor.vendorSetting',
            'tableScanSession',
        ])
            ->where(function (Builder $query) use ($orderId) {
                $query->where('order_public_id', $orderId);

                if (ctype_digit($orderId)) {
                    $query->orWhere('id', (int) $orderId);
                }
            })
            ->where(function (Builder $query) use ($customerId) {
                $query
                    ->where('customer_id', $customerId)
                    ->orWhereHas(
                        'tableScanSession',
                        fn (Builder $session) => $session->where('customer_id', $customerId)
                    );
            })
            ->firstOrFail();
    }

    private function finalOrderAmount(Order $order): float
    {
        if (! $order->table_scan_session_id) {
            return round((float) $order->amount, 2);
        }

        $itemsTotal = $this->computeTableOrderAmount($order);

        if ($itemsTotal <= 0) {
            return round((float) $order->amount, 2);
        }

        return round($itemsTotal + (float) ($order->service_fee ?? 0) + (float) ($order->vat_amount ?? 0), 2);
    }

    private function computeTableOrderAmount(Order $order): float
    {
        $owned = CartItem::with('menuItem:id,price')
            ->where('table_scan_session_id', $order->table_scan_session_id)
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price')
            ->whereJsonContains('order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $order->table_scan_session_id)
            ->get();

        return round($owned->merge($sharedInto)->sum(function (CartItem $item) {
            $unitPrice = $item->menuItem ? (float) $item->menuItem->price : 0.0;
            $lineTotal = $unitPrice * $item->quantity;
            $shareCount = 1 + count($item->order_ids ?? []);

            return $lineTotal / $shareCount;
        }), 2);
    }

    private function stripeAmountMinor(float $amount, string $currency): int
    {
        $zeroDecimalCurrencies = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
            'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimalCurrencies, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    private function assertIntentMatchesPayment(array $intent, OrderPayment $payment): void
    {
        $metadata = $intent['metadata'] ?? [];

        if (isset($metadata['order_id']) && (string) $metadata['order_id'] !== (string) $payment->order_id) {
            abort(422, 'PaymentIntent does not match this order.');
        }

        $metadataCustomerId = $metadata['customer_id'] ?? $metadata['customerId'] ?? null;
        if ($metadataCustomerId !== null && (string) $metadataCustomerId !== (string) $payment->customer_id) {
            abort(422, 'PaymentIntent does not match this customer.');
        }
    }

    private function syncPaymentIntentStatus(OrderPayment $payment, array $intent, ?string $eventType = null): void
    {
        $status = $this->statusFromStripe($intent['status'] ?? $payment->status, $eventType);
        $now = now();

        $payment->update([
            'status' => $status,
            'payment_method' => $intent['payment_method'] ?? $payment->payment_method,
            'last_verified_at' => $now,
            'paid_at' => $status === 'succeeded' ? ($payment->paid_at ?? $now) : $payment->paid_at,
            'failed_at' => in_array($status, ['failed', 'canceled'], true) ? ($payment->failed_at ?? $now) : $payment->failed_at,
        ]);

        $order = $payment->order;
        if (! $order) {
            return;
        }

        if ($status === 'succeeded') {
            $order->update([
                'payment_method' => 'stripe',
                'transaction_id' => $payment->stripe_payment_intent_id,
                'payment_pending' => false,
                'payment_received' => true,
                'payment_confirmed_at' => $order->payment_confirmed_at ?? $now,
            ]);

            return;
        }

        if (in_array($status, ['failed', 'canceled'], true)) {
            $order->update([
                'payment_method' => 'stripe',
                'transaction_id' => $payment->stripe_payment_intent_id,
                'payment_pending' => false,
                'payment_received' => false,
                'payment_note' => 'Stripe payment was not completed.',
            ]);

            return;
        }

        $order->update([
            'payment_method' => 'stripe',
            'transaction_id' => $payment->stripe_payment_intent_id,
            'payment_pending' => true,
            'payment_received' => false,
        ]);
    }

    private function statusFromStripe(string $status, ?string $eventType = null): string
    {
        return match ($eventType) {
            'payment_intent.payment_failed' => 'failed',
            'payment_intent.canceled' => 'canceled',
            default => $status,
        };
    }

    private function orderStatus(string $status): string
    {
        if ($status === 'succeeded') {
            return 'paid';
        }

        if (in_array($status, ['failed', 'canceled'], true)) {
            return 'failed';
        }

        return 'pending';
    }
}
