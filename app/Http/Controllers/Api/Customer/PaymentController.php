<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\StripeWebhookLog;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\NotificationService;
use App\Services\StripePaymentService;
use App\Services\TaxCalculationService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController extends Controller
{
    private const TERMINAL_PAYMENT_STATUSES = ['succeeded', 'canceled', 'failed', 'payment_failed'];

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
     * POST /api/customer/payments/pay-for
     */
    public function payFor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
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
        ]);

        $payer = $request->user();
        $targetCustomerId = (int) $data['customer_id'];
        $targetOrderId = (string) $data['order_id'];

        if ($targetCustomerId === (int) $payer->id) {
            throw ValidationException::withMessages([
                'customer_id' => ['You cannot assign your own orders to yourself.'],
            ]);
        }

        $payerSession = $this->activeSession((int) $payer->id);
        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $targetSession = TableScanSession::with('customer:id,first_name,last_name')
            ->where('customer_id', $targetCustomerId)
            ->where('vendor_id', $payerSession->vendor_id)
            ->where('restaurant_table_id', $payerSession->restaurant_table_id)
            ->where('status', 'active')
            ->latest('scanned_at')
            ->first();

        if (! $targetSession) {
            return response()->json([
                'message' => 'The selected customer is not active at your table.',
            ], 422);
        }

        $orders = DB::transaction(function () use ($targetSession, $payer, $targetOrderId) {
            $orders = Order::where('table_scan_session_id', $targetSession->id)
                ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
                ->where('payment_received', false)
                ->where(function (Builder $match) use ($targetOrderId) {
                    $match->where('order_public_id', $targetOrderId);

                    if (ctype_digit($targetOrderId)) {
                        $match->orWhere('id', (int) $targetOrderId);
                    }
                })
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'order_id' => ['The selected order is not an eligible unpaid order for this customer.'],
                ]);
            }

            if ($orders->contains(fn (Order $order) => $order->paid_by !== null
                && (int) $order->paid_by !== (int) $payer->id)) {
                abort(409, 'One or more orders are already assigned to another payer.');
            }

            Order::whereIn('id', $orders->pluck('id'))
                ->whereNull('paid_by')
                ->update(['paid_by' => $payer->id]);

            return $orders->map(function (Order $order) use ($payer) {
                $order->paid_by = $payer->id;

                return $order;
            });
        });

        $payerIdentity = $this->customerIdentity($payer);
        $targetIdentity = $this->customerIdentity($targetSession->customer);
        $snapshots = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('id', $orders->pluck('id'))
            ->get()
            ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
            ->values()->all();
        NotificationService::notifyTableCustomers(
            $payerSession->restaurant_table_id,
            'order_updated',
            "{$payerIdentity['name']} is paying for {$targetIdentity['name']}'s orders.",
            [
                'template' => 'payment.assigned',
                'customer_id' => $payer->id,
                'payer_id' => $payer->id,
                'payer_name' => $payerIdentity['name'],
                'target_customer_id' => $targetCustomerId,
                'customer_name' => $targetIdentity['name'],
                'order_ids' => $orders->pluck('id')->values()->all(),
                'order_snapshots' => $snapshots,
            ],
            false,
        );

        return response()->json([
            'message' => 'Orders assigned for payment.',
            'paid_by' => $payerIdentity,
            'orders_count' => $orders->count(),
            'orders' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'order_public_id' => $order->order_public_id,
            ])->values(),
        ]);
    }

    /**
     * DELETE /api/customer/payments/pay-for/{customerId}
     */
    public function releasePayFor(Request $request, int $customerId): JsonResponse
    {
        $payer = $request->user();
        $payerSession = $this->activeSession((int) $payer->id);

        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $targetSession = TableScanSession::with('customer:id,first_name,last_name')
            ->where('customer_id', $customerId)
            ->where('vendor_id', $payerSession->vendor_id)
            ->where('restaurant_table_id', $payerSession->restaurant_table_id)
            ->where('status', 'active')
            ->latest('scanned_at')
            ->first();

        if (! $targetSession) {
            return response()->json([
                'message' => 'The selected customer is not active at your table.',
            ], 422);
        }

        $releasedCount = DB::transaction(function () use ($targetSession, $payer) {
            $orders = Order::where('table_scan_session_id', $targetSession->id)
                ->where('paid_by', $payer->id)
                ->where('payment_received', false)
                ->lockForUpdate()
                ->get();

            if ($orders->isEmpty()) {
                return 0;
            }

            $hasActivePayment = OrderPayment::whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
                ->where(function (Builder $query) use ($orders) {
                    $orderIds = $orders->pluck('id');
                    $query->whereIn('order_id', $orderIds)
                        ->orWhereHas('orders', fn (Builder $covered) => $covered->whereIn('orders.id', $orderIds));
                })
                ->exists();

            if ($hasActivePayment) {
                abort(409, 'Orders with an active payment cannot be released.');
            }

            return Order::whereIn('id', $orders->pluck('id'))
                ->update(['paid_by' => null]);
        });

        if ($releasedCount > 0) {
            $payerIdentity = $this->customerIdentity($payer);
            $targetIdentity = $this->customerIdentity($targetSession->customer);
            $releasedSnapshots = Order::with('paidBy:id,first_name,last_name')
                ->where('table_scan_session_id', $targetSession->id)
                ->where('payment_received', false)
                ->whereNull('paid_by')
                ->get()
                ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                ->values()->all();
            NotificationService::notifyTableCustomers(
                $payerSession->restaurant_table_id,
                'order_updated',
                "{$payerIdentity['name']} is no longer paying for {$targetIdentity['name']}'s orders.",
                [
                    'template' => 'payment.assignment_released',
                    'customer_id' => $payer->id,
                    'payer_id' => $payer->id,
                    'payer_name' => $payerIdentity['name'],
                    'target_customer_id' => $customerId,
                    'customer_name' => $targetIdentity['name'],
                    'released_orders_count' => $releasedCount,
                    'order_snapshots' => $releasedSnapshots,
                ],
                false,
            );
        }

        return response()->json([
            'message' => 'Payment assignment released.',
            'released_orders_count' => $releasedCount,
        ]);
    }

    /**
     * POST /api/customer/payments/create-intent
     */
    public function createIntent(Request $request): JsonResponse
    {
        $customer = $request->user();

        $payerSession = $this->activeSession((int) $customer->id);
        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $orders = $this->payableSessionOrders($payerSession, (int) $customer->id);

        if ($orders->isEmpty()) {
            $assignedElsewhere = Order::where('table_scan_session_id', $payerSession->id)
                ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
                ->where('payment_received', false)
                ->whereNotNull('paid_by')
                ->where('paid_by', '!=', $customer->id)
                ->exists();

            if ($assignedElsewhere) {
                return response()->json([
                    'message' => 'This order is assigned to another payer.',
                ], 409);
            }

            return response()->json(['message' => 'You have no unpaid orders to pay for.'], 422);
        }

        $order = $orders->first(fn (Order $candidate) => $this->orderOwnerCustomerId($candidate) === (int) $customer->id)
            ?? $orders->first();
        $orderIds = $orders->pluck('id');

        foreach ($orders->pluck('table_scan_session_id')->filter()->unique() as $sessionId) {
            $unboundCount = CartItem::where('table_scan_session_id', $sessionId)
                ->whereNull('order_id')
                ->count();
            if ($unboundCount > 0) {
                return response()->json([
                    'message'            => 'You have items in your cart that have not been submitted. Please confirm your full order before paying.',
                    'unbound_item_count' => $unboundCount,
                ], 422);
            }
        }

        $settings = $order->vendor?->vendorSetting;
        if (! $settings?->stripe_enabled || ! $settings->stripe_account_id || ! $settings->stripe_onboarding_complete) {
            return response()->json(['message' => 'Stripe payments are not enabled for this restaurant.'], 422);
        }

        $amounts = $orders->mapWithKeys(fn (Order $coveredOrder) => [
            $coveredOrder->id => $this->finalOrderAmount($coveredOrder),
        ]);
        $amount = round((float) $amounts->sum(), 2);

        if ($amount <= 0 || $amounts->contains(fn (float $orderAmount) => $orderAmount <= 0)) {
            return response()->json(['message' => 'Order amount must be greater than zero.'], 422);
        }

        $currency = $order->vendor?->currency ?? $order->currency ?? 'EUR';
        $metadata = [
            'order_id' => (string) $order->id,
            'order_public_id' => (string) $order->order_public_id,
            'vendor_id' => (string) $order->vendor_id,
            'customer_id' => (string) $customer->id,
            'payment_for' => $order->table_scan_session_id ? 'dine_in' : 'order',
            'covered_order_count' => (string) $orders->count(),
        ];

        if ($order->table_scan_session_id) {
            $metadata['table_session_id'] = (string) $order->table_scan_session_id;
        }

        try {
            $intent = DB::transaction(function () use ($order, $orderIds, $amounts, $customer, $settings, $amount, $currency, $metadata) {
                $lockedOrders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();

                $existingPayment = OrderPayment::where('customer_id', $customer->id)
                    ->whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
                    ->where(function (Builder $query) use ($orderIds) {
                        $query->whereIn('order_id', $orderIds)
                            ->orWhereHas('orders', fn (Builder $covered) => $covered->whereIn('orders.id', $orderIds));
                    })
                    ->latest()
                    ->first();

                if ($existingPayment) {
                    $existingIntent = $this->stripe->retrievePaymentIntent($existingPayment->stripe_payment_intent_id);
                    if (($existingIntent['status'] ?? null) === 'succeeded') {
                        $this->syncPaymentIntentStatus($existingPayment, $existingIntent);

                        return $existingIntent;
                    }

                    if (($existingIntent['status'] ?? null) !== 'canceled') {
                        return $existingIntent;
                    }

                    $this->syncPaymentIntentStatus($existingPayment, $existingIntent);
                }

                if ($lockedOrders->contains(fn (Order $locked) => $locked->payment_received)) {
                    abort(409, 'One or more orders are already paid.');
                }

                if ($lockedOrders->contains(function (Order $locked) use ($customer) {
                    if ($locked->paid_by !== null) {
                        return (int) $locked->paid_by !== (int) $customer->id;
                    }

                    return $this->orderOwnerCustomerId($locked) !== (int) $customer->id;
                })) {
                    abort(409, 'One or more orders are no longer assigned to this payer.');
                }

                $intent = $this->stripe->createPaymentIntent(
                    $this->stripeAmountMinor($amount, $currency),
                    $currency,
                    $settings->stripe_account_id,
                    $metadata
                );

                $payment = OrderPayment::create([
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

                $payment->orders()->attach($amounts->mapWithKeys(fn ($orderAmount, $id) => [
                    $id => ['amount' => $orderAmount],
                ])->all());

                foreach ($lockedOrders as $lockedOrder) {
                    $lockedOrder->update([
                        'amount' => $amounts[$lockedOrder->id],
                        'service_fee' => $lockedOrder->service_fee ?? 0,
                        'currency' => strtoupper($currency),
                        'payment_method' => 'stripe',
                        'transaction_id' => $intent['id'],
                        'payment_pending' => true,
                        'payment_received' => false,
                    ]);
                }

                return $intent;
            });
        } catch (StripeInvalidRequestException $e) {
            \Log::error("Stripe payment intent failed for vendor {$order->vendor_id}: {$e->getMessage()}");
            return response()->json([
                'message' => 'Online payments are not available for this restaurant yet. Please pay on-site.',
            ], 422);
        }

        if ($order->table_scan_session_id) {
            $tableId = $order->tableScanSession?->restaurant_table_id;
            if ($tableId) {
                $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'A guest';
                $snapshots = Order::with('paidBy:id,first_name,last_name')
                    ->whereIn('id', $orderIds)->get()
                    ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                    ->values()->all();
                NotificationService::notifyTableCustomers(
                    $tableId,
                    'payment_updated',
                    "{$customerName} initiated a payment.",
                    [
                        'template' => 'payment.initiated',
                        'customer_id' => $customer->id,
                        'customer_name' => $customerName,
                        'order_id' => $order->id,
                        'order_snapshots' => $snapshots,
                    ],
                );
            }
        }

        return response()->json([
            'clientSecret' => $intent['client_secret'],
            'paymentIntentId' => $intent['id'],
        ]);
    }

    /**
     * POST /api/customer/payments/request-cash
     */
    public function requestCash(Request $request): JsonResponse
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
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = $request->user();
        $customerId = (int) $data['customer_id'];

        if ($customerId !== (int) $customer->id) {
            throw ValidationException::withMessages([
                'customer_id' => ['The provided customer identifier does not match the authenticated customer.'],
            ]);
        }

        $order = $this->customerOrder((string) $data['order_id'], $customer->id);
        $ownerCustomerId = $this->orderOwnerCustomerId($order);

        if ($order->payment_received) {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        if ($ownerCustomerId === (int) $customer->id
            && $order->paid_by !== null
            && (int) $order->paid_by !== (int) $customer->id) {
            return response()->json([
                'message' => 'This order is assigned to another payer.',
            ], 409);
        }

        $orders = $this->groupedOrdersForPayment($order, (int) $customer->id);
        $orderIds = $orders->pluck('id');

        foreach ($orders->pluck('table_scan_session_id')->filter()->unique() as $sessionId) {
            $unboundCount = CartItem::where('table_scan_session_id', $sessionId)
                ->whereNull('order_id')
                ->count();
            if ($unboundCount > 0) {
                return response()->json([
                    'message'            => 'You have items in your cart that have not been submitted. Please confirm your full order before paying.',
                    'unbound_item_count' => $unboundCount,
                ], 422);
            }
        }

        $amounts = $orders->mapWithKeys(fn (Order $coveredOrder) => [
            $coveredOrder->id => $this->finalOrderAmount($coveredOrder),
        ]);
        $amount = round((float) $amounts->sum(), 2);

        if ($amount <= 0 || $amounts->contains(fn (float $orderAmount) => $orderAmount <= 0)) {
            return response()->json(['message' => 'Order amount must be greater than zero.'], 422);
        }

        $currency = $order->vendor?->currency ?? $order->currency ?? 'EUR';

        DB::transaction(function () use ($order, $orderIds, $amounts, $customer, $amount, $currency, $data) {
            $lockedOrders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();

            if ($lockedOrders->contains(fn (Order $locked) => $locked->payment_received)) {
                abort(409, 'One or more orders are already paid.');
            }

            OrderPayment::create([
                'order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'customer_id' => $customer->id,
                'table_scan_session_id' => $order->table_scan_session_id,
                'stripe_account_id' => null,
                'stripe_payment_intent_id' => null,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'status' => 'cash_requested',
                'payment_method' => 'cash',
                'metadata' => [
                    'notes' => $data['notes'] ?? null,
                    'order_id' => (string) $order->id,
                    'order_public_id' => (string) $order->order_public_id,
                    'vendor_id' => (string) $order->vendor_id,
                    'customer_id' => (string) $customer->id,
                    'covered_order_count' => (string) $lockedOrders->count(),
                ],
            ]);

            foreach ($lockedOrders as $lockedOrder) {
                $lockedOrder->update([
                    'amount' => $amounts[$lockedOrder->id],
                    'service_fee' => $lockedOrder->service_fee ?? 0,
                    'currency' => strtoupper($currency),
                    'payment_method' => 'cash',
                    'payment_pending' => true,
                    'payment_received' => false,
                ]);
            }
        });

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'A guest';
        $tableId = $order->tableScanSession?->restaurant_table_id;

        if ($tableId) {
            $snapshots = Order::with('paidBy:id,first_name,last_name')
                ->whereIn('id', $orderIds)->get()
                ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                ->values()->all();
            NotificationService::notifyTableCustomers(
                $tableId,
                'payment_updated',
                "{$customerName} requested cash payment.",
                [
                    'template' => 'payment.cash_requested',
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'order_id' => $order->id,
                    'notes' => $data['notes'] ?? null,
                    'order_snapshots' => $snapshots,
                ],
            );
        }

        return response()->json([
            'message' => 'Cash payment requested. A waiter will come to your table.',
            'amount' => $amount,
            'currency' => strtoupper($currency),
        ]);
    }

    /**
     * POST /api/customer/payments/update-intent
     */
    public function updateIntent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_intent_id' => ['required', 'string', 'max:255'],
            'tip_amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $customer = $request->user();

        $paymentIntentId = $this->normalizePaymentIntentId($data['payment_intent_id']);
        $payment = OrderPayment::with([
            'order.vendor.vendorSetting',
            'order.vendor.countryRecord',
            'order.tableScanSession',
            'orders.tableScanSession',
        ])
            ->where('stripe_payment_intent_id', $paymentIntentId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $order = $payment->order ?? $this->paymentOrders($payment)->first();

        if (! $order) {
            return response()->json(['message' => 'No order is associated with this payment.'], 422);
        }

        if ($order->payment_received) {
            return response()->json(['message' => 'Order is already paid.'], 422);
        }

        $intent = $this->stripe->retrievePaymentIntent($paymentIntentId);
        $this->assertIntentMatchesPayment($intent, $payment);

        if (in_array($intent['status'] ?? $payment->status, ['succeeded', 'processing'], true)) {
            return response()->json(['message' => 'PaymentIntent cannot be updated in its current status.'], 422);
        }

        $currency = $payment->currency ?: $order->currency ?: ($order->vendor?->currency ?? 'EUR');
        $coveredOrders = $this->paymentOrders($payment);
        $baseAmounts = $coveredOrders->mapWithKeys(fn (Order $coveredOrder) => [
            $coveredOrder->id => $this->finalOrderAmount($coveredOrder),
        ]);
        $baseAmount = round((float) $baseAmounts->sum(), 2);
        $tipAmount = round((float) $data['tip_amount'], 2);
        $tipOrder = $coveredOrders->first(
            fn (Order $coveredOrder) => $this->orderOwnerCustomerId($coveredOrder) === (int) $customer->id
        );

        if ($tipAmount > 0 && ! $tipOrder) {
            return response()->json([
                'message' => 'A tip cannot be added when paying only for another customer’s orders.',
            ], 422);
        }

        $payableAmount = round($baseAmount + $tipAmount, 2);

        if ($payableAmount <= 0) {
            return response()->json(['message' => 'Payment amount must be greater than zero.'], 422);
        }

        $metadata = array_merge($intent['metadata'] ?? [], [
            'order_id' => (string) $order->id,
            'order_public_id' => (string) $order->order_public_id,
            'vendor_id' => (string) $order->vendor_id,
            'customer_id' => (string) $customer->id,
            'tip_amount' => number_format($tipAmount, 2, '.', ''),
            'base_amount' => number_format($baseAmount, 2, '.', ''),
            'payable_amount' => number_format($payableAmount, 2, '.', ''),
        ]);

        try {
            $updatedIntent = $this->stripe->updatePaymentIntent(
                $paymentIntentId,
                $this->stripeAmountMinor($payableAmount, $currency),
                $currency,
                $metadata
            );
        } catch (StripeInvalidRequestException $e) {
            \Log::error("Stripe payment intent update failed for order {$order->id}: {$e->getMessage()}");
            return response()->json(['message' => 'PaymentIntent could not be updated.'], 422);
        }

        DB::transaction(function () use ($coveredOrders, $tipOrder, $payment, $baseAmounts, $tipAmount, $payableAmount, $currency, $updatedIntent, $metadata) {
            foreach ($coveredOrders as $coveredOrder) {
                $coveredOrder->update([
                    'amount' => $baseAmounts[$coveredOrder->id],
                    'service_fee' => $coveredOrder->service_fee ?? 0,
                    'tip_amount' => $tipOrder && $coveredOrder->is($tipOrder) ? $tipAmount : 0,
                    'currency' => strtoupper($currency),
                    'payment_method' => 'stripe',
                    'transaction_id' => $updatedIntent['id'],
                    'payment_pending' => true,
                    'payment_received' => false,
                ]);

                if ($payment->orders->contains('id', $coveredOrder->id)) {
                    $payment->orders()->updateExistingPivot($coveredOrder->id, [
                        'amount' => $baseAmounts[$coveredOrder->id],
                    ]);
                }
            }

            $payment->update([
                'amount' => $payableAmount,
                'currency' => strtoupper($currency),
                'status' => $updatedIntent['status'] ?? $payment->status,
                'payment_method' => $updatedIntent['payment_method'] ?? $payment->payment_method,
                'metadata' => $metadata,
            ]);
        });

        if ($order->table_scan_session_id) {
            $tableId = $order->tableScanSession?->restaurant_table_id;
            if ($tableId) {
                $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'A guest';
                $snapshots = $coveredOrders->map(fn (Order $o) => NotificationService::orderSnapshot($o->fresh()->load('paidBy')))
                    ->values()->all();
                NotificationService::notifyTableCustomers(
                    $tableId,
                    'payment_updated',
                    "{$customerName} updated the payment.",
                    [
                        'template' => 'payment.updated',
                        'customer_id' => $customer->id,
                        'customer_name' => $customerName,
                        'order_id' => $order->id,
                        'payment_id' => $payment->id,
                        'order_snapshots' => $snapshots,
                    ],
                );
            }
        }

        return response()->json([
            'clientSecret' => $updatedIntent['client_secret'],
            'paymentIntentId' => $updatedIntent['id'],
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

        $payment = OrderPayment::with(['order.tableScanSession', 'orders.tableScanSession'])
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
        } catch (UnexpectedValueException|SignatureVerificationException $e) {
            StripeWebhookLog::create([
                'event_type' => 'unknown',
                'http_status' => 400,
                'outcome' => 'signature_invalid',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $type = $event['type'];
        $intent = $event['payment_intent'];

        if (! in_array($type, [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'payment_intent.processing',
        ], true)) {
            StripeWebhookLog::create([
                'event_type' => $type,
                'stripe_payment_intent_id' => $intent['id'] ?? null,
                'http_status' => 200,
                'outcome' => 'ignored_event_type',
            ]);

            return response()->json(['received' => true]);
        }

        $payment = OrderPayment::with(['order.tableScanSession', 'orders.tableScanSession'])
            ->where('stripe_payment_intent_id', $intent['id'])
            ->first();

        if (! $payment) {
            StripeWebhookLog::create([
                'event_type' => $type,
                'stripe_payment_intent_id' => $intent['id'],
                'http_status' => 200,
                'outcome' => 'payment_not_found',
            ]);

            return response()->json(['received' => true, 'ignored' => true]);
        }

        $this->syncPaymentIntentStatus($payment, $intent, $type);

        if ($type === 'payment_intent.succeeded' && $payment->table_scan_session_id) {
            $session = $payment->order?->tableScanSession;
            if ($session) {
                $affectedOrders = $this->paymentOrders($payment);
                $snapshots = $affectedOrders->map(fn (Order $o) => NotificationService::orderSnapshot($o->fresh()->load('paidBy')))
                    ->values()->all();
                NotificationService::notifyTableCustomers(
                    $session->restaurant_table_id,
                    'payment_updated',
                    'A payment has been completed on this table.',
                    [
                        'template' => 'payment.completed',
                        'order_id' => $payment->order_id,
                        'payment_id' => $payment->id,
                        'order_snapshots' => $snapshots,
                    ],
                );
            }
        }

        StripeWebhookLog::create([
            'event_type' => $type,
            'stripe_payment_intent_id' => $intent['id'],
            'http_status' => 200,
            'outcome' => 'processed',
            'metadata' => [
                'order_id' => $payment->order_id,
                'status_applied' => $payment->fresh()->status,
            ],
        ]);

        return response()->json(['received' => true]);
    }

    private function customerOrder(string $orderId, int $customerId): Order
    {
        return Order::with([
            'vendor.vendorSetting',
            'vendor.countryRecord',
            'tableScanSession',
            'paidBy:id,first_name,last_name',
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
                    ->orWhere('paid_by', $customerId)
                    ->orWhereHas(
                        'tableScanSession',
                        fn (Builder $session) => $session->where('customer_id', $customerId)
                    );
            })
            ->firstOrFail();
    }

    private function activeSession(int $customerId): ?TableScanSession
    {
        return TableScanSession::where('customer_id', $customerId)
            ->where('status', 'active')
            ->latest('scanned_at')
            ->first();
    }

    private function orderOwnerCustomerId(Order $order): ?int
    {
        if ($order->customer_id) {
            return (int) $order->customer_id;
        }

        if ($order->relationLoaded('tableScanSession')) {
            return $order->tableScanSession?->customer_id
                ? (int) $order->tableScanSession->customer_id
                : null;
        }

        $customerId = $order->tableScanSession()->value('customer_id');

        return $customerId ? (int) $customerId : null;
    }

    private function payableSessionOrders(TableScanSession $payerSession, int $payerId): EloquentCollection
    {
        $activeSessionIds = TableScanSession::where('vendor_id', $payerSession->vendor_id)
            ->where('restaurant_table_id', $payerSession->restaurant_table_id)
            ->where('status', 'active')
            ->pluck('id');

        return Order::with([
            'vendor.vendorSetting',
            'vendor.countryRecord',
            'tableScanSession',
            'paidBy:id,first_name,last_name',
        ])
            ->whereIn('table_scan_session_id', $activeSessionIds)
            ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
            ->where('payment_received', false)
            ->where(function (Builder $query) use ($payerSession, $payerId) {
                $query->where(function (Builder $own) use ($payerSession, $payerId) {
                    $own->where('table_scan_session_id', $payerSession->id)
                        ->where(function (Builder $unclaimed) use ($payerId) {
                            $unclaimed->whereNull('paid_by')->orWhere('paid_by', $payerId);
                        });
                })->orWhere('paid_by', $payerId);
            })
            ->orderBy('id')
            ->get()
            ->values();
    }

    private function groupedOrdersForPayment(Order $anchor, int $payerId): EloquentCollection
    {
        $orders = new EloquentCollection([$anchor]);

        if (! $anchor->table_scan_session_id) {
            return $orders;
        }

        $anchorSession = $anchor->tableScanSession;
        $payerSession = $this->activeSession($payerId);

        if (! $anchorSession || ! $payerSession
            || (int) $payerSession->vendor_id !== (int) $anchorSession->vendor_id
            || (int) $payerSession->restaurant_table_id !== (int) $anchorSession->restaurant_table_id) {
            abort(409, 'The order is not part of your active table visit.');
        }

        $activeSessionIds = TableScanSession::where('vendor_id', $payerSession->vendor_id)
            ->where('restaurant_table_id', $payerSession->restaurant_table_id)
            ->where('status', 'active')
            ->pluck('id');

        $assignedOrders = Order::with([
            'vendor.vendorSetting',
            'vendor.countryRecord',
            'tableScanSession',
            'paidBy:id,first_name,last_name',
        ])
            ->where('paid_by', $payerId)
            ->whereIn('table_scan_session_id', $activeSessionIds)
            ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
            ->where('payment_received', false)
            ->get();

        return $orders->merge($assignedOrders)->unique('id')->values();
    }

    private function paymentOrders(OrderPayment $payment): EloquentCollection
    {
        if (! $payment->relationLoaded('orders')) {
            $payment->load('orders.tableScanSession');
        }

        if ($payment->orders->isNotEmpty()) {
            return $payment->orders;
        }

        if (! $payment->relationLoaded('order')) {
            $payment->load('order.tableScanSession');
        }

        return $payment->order
            ? new EloquentCollection([$payment->order])
            : new EloquentCollection();
    }

    private function customerIdentity($customer): array
    {
        return [
            'id' => $customer?->id,
            'name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'Guest',
        ];
    }

    private function finalOrderAmount(Order $order): float
    {
        if (! $order->table_scan_session_id) {
            return round((float) $order->amount, 2);
        }

        $vendorCountry = $order->vendor?->country ?? 'AT';
        $serviceFeeRate = (float) ($order->vendor?->vendorSetting?->service_fee_rate ?? 0);
        $itemsGrossTotal = $this->computeTableOrderAmount($order, $vendorCountry);

        if ($itemsGrossTotal <= 0) {
            return round((float) $order->amount, 2);
        }

        $serviceFee = round($itemsGrossTotal * ($serviceFeeRate / 100), 2);
        $order->service_fee = $serviceFee;

        return round($itemsGrossTotal + $serviceFee, 2);
    }

    private function computeTableOrderAmount(Order $order, string $vendorCountry = 'AT'): float
    {
        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
            ->where('order_id', $order->id)
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $order->table_scan_session_id)
            ->get();

        return round($owned->merge($sharedInto)->sum(function (CartItem $item) use ($vendorCountry) {
            $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);

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

    private function normalizePaymentIntentId(string $paymentIntentId): string
    {
        return str_contains($paymentIntentId, '_secret_')
            ? explode('_secret_', $paymentIntentId, 2)[0]
            : $paymentIntentId;
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

        $orders = $this->paymentOrders($payment);
        if ($orders->isEmpty()) {
            return;
        }

        if ($status === 'succeeded') {
            foreach ($orders as $order) {
                $order->update([
                    'payment_method' => 'stripe',
                    'transaction_id' => $payment->stripe_payment_intent_id,
                    'payment_pending' => false,
                    'payment_received' => true,
                    'payment_confirmed_at' => $order->payment_confirmed_at ?? $now,
                ]);
            }

            return;
        }

        if (in_array($status, ['failed', 'canceled'], true)) {
            foreach ($orders as $order) {
                $order->update([
                    'payment_method' => 'stripe',
                    'transaction_id' => $payment->stripe_payment_intent_id,
                    'payment_pending' => false,
                    'payment_received' => false,
                    'payment_note' => 'Stripe payment was not completed.',
                ]);
            }

            return;
        }

        foreach ($orders as $order) {
            $order->update([
                'payment_method' => 'stripe',
                'transaction_id' => $payment->stripe_payment_intent_id,
                'payment_pending' => true,
                'payment_received' => false,
            ]);
        }
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
