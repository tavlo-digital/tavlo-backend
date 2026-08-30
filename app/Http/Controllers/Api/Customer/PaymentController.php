<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\StripeWebhookLog;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\CheckoutHoldService;
use App\Services\CustomerCommandBus;
use App\Services\EditableOrderMergeService;
use App\Services\KitchenOrderReleaseService;
use App\Services\NotificationService;
use App\Services\OrderSessionService;
use App\Services\PaymentGuardService;
use App\Services\ShareOrderService;
use App\Services\StripePaymentService;
use App\Services\TableStatePatchService;
use App\Services\TaxCalculationService;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class PaymentController extends Controller
{
    private const TERMINAL_PAYMENT_STATUSES = ['succeeded', 'canceled', 'failed', 'payment_failed'];

    public function __construct(
        private readonly StripePaymentService $stripe,
        private readonly TableStatePatchService $statePatches,
        private readonly CustomerCommandBus $commands,
        private readonly OrderSessionService $orderSessions,
        private readonly KitchenOrderReleaseService $kitchenReleases,
        private readonly CheckoutHoldService $checkoutHolds,
        private readonly EditableOrderMergeService $editableOrderMerges,
    ) {}

    private function pendingCommandResponse(TableScanSession $session): ?JsonResponse
    {
        if (! $this->commands->enabled() || $this->commands->waitForSession((int) $session->id)) {
            return null;
        }

        return response()->json([
            'message' => 'Your latest cart changes are still being saved. Please retry shortly.',
            'code' => 'CUSTOMER_COMMANDS_PENDING',
            'retry_after_ms' => 250,
        ], 409);
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
        $isOffPremise = $this->orderSessions->isOffPremise($this->orderSessions->mode($request));

        return response()->json([
            'method' => [
                'on-site' => $isOffPremise
                    ? (bool) (($settings?->accept_on_site ?? true)
                        && ($settings?->accept_pickup_cash ?? true))
                    : (bool) ($settings?->accept_on_site ?? true),
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
        $targetOrderId = (string) $data['order_id'];

        $payerSession = $this->activeSession((int) $payer->id, $request);
        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }
        if ($pending = $this->pendingCommandResponse($payerSession)) {
            return $pending;
        }

        $tableSessionIds = $this->orderSessions->groupSessionIds($payerSession);

        $result = DB::transaction(function () use ($tableSessionIds, $payer, $payerSession, $targetOrderId) {
            $orders = Order::whereIn('table_scan_session_id', $tableSessionIds)
                ->whereNotIn('status', $this->eligibleExcludedStatuses($payerSession))
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
                    'order_id' => ['The selected order is not an eligible unpaid order at this table.'],
                ]);
            }

            if ($orders->contains(fn (Order $o) => $o->parent_order_id !== null)) {
                throw ValidationException::withMessages([
                    'order_id' => ['Personal share orders cannot be covered.'],
                ]);
            }

            if ($orders->contains(fn (Order $o) => $o->table_scan_session_id === $payerSession->id)) {
                throw ValidationException::withMessages([
                    'order_id' => ['You cannot pay for your own order.'],
                ]);
            }

            if ($orders->contains(fn (Order $order) => $order->paid_by !== null
                && (int) $order->paid_by !== (int) $payer->id)) {
                abort(409, 'One or more orders are already assigned to another payer.');
            }

            // A checkout someone else already opened for these orders (e.g.
            // the owner paying themselves) locks them until that intent is
            // completed or canceled. The payer's own intents don't block the
            // idempotent re-claim.
            $lockedByOther = PaymentGuardService::activePaymentsCovering($orders->pluck('id'))
                ->contains(fn (OrderPayment $payment) => (int) $payment->customer_id !== (int) $payer->id);
            if ($lockedByOther) {
                abort(409, 'This order is locked while a payment is in progress.');
            }

            // Same rule one step earlier: somebody standing on the payment step
            // holds these orders even before a payment exists, and covering one
            // would change the total they are looking at.
            if ($this->checkoutHolds->heldByOthers($orders->pluck('id'), (int) $payer->id)->isNotEmpty()) {
                abort(409, 'This order is locked while another guest is checking out.');
            }

            $shareChanges = ShareOrderService::removePayerSharesFrom($orders, $payerSession);

            Order::whereIn('id', $orders->pluck('id'))
                ->whereNull('paid_by')
                ->update(['paid_by' => $payer->id]);

            // The owner's personal opt-in shares move to their side order so
            // the payer covers only the owner's own items.
            $affectedOrderIds = $orders->pluck('id')
                ->merge($shareChanges['affected_order_ids']);
            foreach ($orders as $order) {
                $side = ShareOrderService::moveOptInShares($order);
                if ($side) {
                    $affectedOrderIds->push($side->id);
                }

                $existingSideId = Order::where('parent_order_id', $order->id)
                    ->where('payment_received', false)
                    ->value('id');
                if ($existingSideId) {
                    $affectedOrderIds->push((int) $existingSideId);
                }
            }

            return [
                'orders' => $orders->map(function (Order $order) use ($payer) {
                    $order->paid_by = $payer->id;

                    return $order;
                }),
                'affected_order_ids' => $affectedOrderIds->unique()->values(),
                'affected_item_ids' => $shareChanges['affected_item_ids'],
                'removed_order_ids' => $shareChanges['removed_order_ids'],
            ];
        });
        $orders = $result['orders'];

        $targetSession = TableScanSession::with('customer:id,first_name,last_name')
            ->find($orders->first()->table_scan_session_id);
        $payerIdentity = $this->customerIdentity($payer);
        $targetName = $targetSession?->customer
            ? $this->customerIdentity($targetSession->customer)['name']
            : 'Waiter';

        $snapshots = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('id', $result['affected_order_ids'])
            ->get()
            ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
            ->values()->all();
        $statePatch = $this->statePatches->build(
            'payment.assigned',
            $result['affected_order_ids'],
            $result['affected_item_ids'],
            $result['removed_order_ids'],
        );
        $this->orderSessions->notifyCustomers(
            $payerSession,
            'order_updated',
            "{$payerIdentity['name']} is paying for {$targetName}'s orders.",
            [
                'template' => 'payment.assigned',
                'customer_id' => $payer->id,
                'payer_id' => $payer->id,
                'payer_name' => $payerIdentity['name'],
                'customer_name' => $targetName,
                'order_ids' => $orders->pluck('id')->values()->all(),
                'order_snapshots' => $snapshots,
                'removed_order_ids' => $result['removed_order_ids']->all(),
                'state_patch' => $statePatch,
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
            'state_patch' => $statePatch,
        ]);
    }

    /**
     * DELETE /api/customer/payments/pay-for/{orderId}
     */
    public function releasePayFor(Request $request, string $orderId): JsonResponse
    {
        $payer = $request->user();
        $payerSession = $this->activeSession((int) $payer->id, $request);

        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }
        if ($pending = $this->pendingCommandResponse($payerSession)) {
            return $pending;
        }

        $tableSessionIds = $this->orderSessions->groupSessionIds($payerSession);

        $result = DB::transaction(function () use ($tableSessionIds, $payer, $orderId) {
            $query = Order::whereIn('table_scan_session_id', $tableSessionIds)
                ->where('paid_by', $payer->id)
                ->where('payment_received', false);

            $query->where(function (Builder $match) use ($orderId) {
                $match->where('order_public_id', $orderId);

                if (ctype_digit($orderId)) {
                    $match->orWhere('id', (int) $orderId);
                }
            });

            $orders = $query->lockForUpdate()->get();

            if ($orders->isEmpty()) {
                return null;
            }

            $activePayments = PaymentGuardService::activePaymentsCovering($orders->pluck('id'));

            // A payment someone else started, or one of the payer's own that is
            // already processing a charge, still blocks the release. The payer's
            // own abandoned intents (e.g. they opened the payment step and went
            // back) are canceled so unchecking is always possible.
            $blocked = $activePayments->contains(fn (OrderPayment $payment) => (int) $payment->customer_id !== (int) $payer->id
                || $payment->status === 'processing');

            if ($blocked) {
                abort(409, 'Orders with an active payment cannot be released.');
            }

            // The owner is on the payment step. Handing their order back now
            // would put it into the total they are already looking at, so the
            // coverage stands until they are done.
            $ownerCheckingOut = $orders->first(
                fn (Order $order) => (int) $order->customer_id !== (int) $payer->id
                    && $this->checkoutHolds->customerIsCheckingOut((int) $order->customer_id, $tableSessionIds)
            );

            if ($ownerCheckingOut) {
                abort(409, 'This order is locked while its owner is checking out.');
            }

            $pendingResetOrderIds = collect();

            foreach ($activePayments as $payment) {
                $pendingResetOrderIds = $pendingResetOrderIds
                    ->merge($this->cancelAbandonedPayment($payment));
            }

            Order::whereIn('id', $orders->pluck('id'))
                ->update(['paid_by' => null]);

            // Shares the owner opted into while covered live on a side order;
            // with the coverage gone they fold back into the main order.
            $removedOrderIds = collect();
            foreach ($orders as $order) {
                $mergedSideId = ShareOrderService::mergeBack($order);
                if ($mergedSideId) {
                    $removedOrderIds->push($mergedSideId);
                }
            }

            $merged = $this->mergeEditableSessions($orders->pluck('table_scan_session_id'));

            return [
                'released' => $orders,
                'affected_order_ids' => $orders->pluck('id')
                    ->merge($pendingResetOrderIds)
                    ->merge($merged['order_ids'])
                    ->diff($merged['removed_order_ids'])
                    ->unique()
                    ->values(),
                'removed_order_ids' => $removedOrderIds
                    ->merge($merged['removed_order_ids'])
                    ->unique()
                    ->values(),
                'removed_item_ids' => $merged['removed_item_ids'],
            ];
        });

        $statePatch = $this->statePatches->build(
            'payment.assignment_released',
            $result ? $result['affected_order_ids'] : [],
            removedOrderIds: $result ? $result['removed_order_ids'] : [],
            removedItemIds: $result ? $result['removed_item_ids'] : [],
        );

        if ($result && $result['released']->isNotEmpty()) {
            $released = $result['released'];
            $targetSession = TableScanSession::with('customer:id,first_name,last_name')
                ->find($released->first()->table_scan_session_id);
            $payerIdentity = $this->customerIdentity($payer);
            $targetName = $targetSession?->customer
                ? $this->customerIdentity($targetSession->customer)['name']
                : 'Waiter';

            $releasedSnapshots = Order::with('paidBy:id,first_name,last_name')
                ->whereIn('id', $result['affected_order_ids'])
                ->get()
                ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                ->values()->all();
            $this->orderSessions->notifyCustomers(
                $payerSession,
                'order_updated',
                "{$payerIdentity['name']} is no longer paying for {$targetName}'s orders.",
                [
                    'template' => 'payment.assignment_released',
                    'customer_id' => $payer->id,
                    'payer_id' => $payer->id,
                    'payer_name' => $payerIdentity['name'],
                    'customer_name' => $targetName,
                    'released_orders_count' => $released->count(),
                    'order_snapshots' => $releasedSnapshots,
                    'removed_order_ids' => $result['removed_order_ids']->all(),
                    'state_patch' => $statePatch,
                ],
                false,
            );
        }

        return response()->json([
            'message' => 'Payment assignment released.',
            'released_orders_count' => $result ? $result['released']->count() : 0,
            'state_patch' => $statePatch,
        ]);
    }

    /**
     * GET /api/customer/payments/intent
     *
     * Read-only probe: does the customer have an active (non-terminal) Stripe
     * intent at their current table? Used to resume the checkout after a page
     * reload. Never creates or cancels anything.
     */
    /**
     * POST /api/customer/payments/checkout-hold
     *
     * Claims the orders this customer is about to settle, from the moment they
     * open the payment step. Until this existed the table stayed editable
     * behind them: another guest could cover the same order or split one of
     * its items, and the total on screen quietly stopped matching what they
     * were about to be charged. A cash checkout had no lock at all, since the
     * only thing that locked anything was a Stripe intent.
     *
     * The hold is released by going back (see releaseCheckoutHold), superseded
     * when a real payment takes over, and expires on its own if the customer
     * abandons the checkout.
     */
    public function holdCheckout(Request $request): JsonResponse
    {
        $customer = $request->user();
        $payerSession = $this->activeSession((int) $customer->id, $request);

        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $result = DB::transaction(function () use ($customer, $payerSession) {
            $orders = $this->payableSessionOrders($payerSession, (int) $customer->id);

            if ($orders->isEmpty()) {
                return ['error' => ['message' => 'You have no unpaid orders to pay for.', 'status' => 422]];
            }

            $lockedOrders = Order::whereIn('id', $orders->pluck('id'))->lockForUpdate()->get();

            $heldElsewhere = $lockedOrders->first(
                fn (Order $order) => $this->checkoutHolds->isHeldByAnyoneElse($order, (int) $customer->id)
            );

            if ($heldElsewhere) {
                $holder = $heldElsewhere->checkout_hold_by
                    ? Customer::find($heldElsewhere->checkout_hold_by)
                    : null;
                $name = $holder
                    ? (trim(($holder->first_name ?? '').' '.($holder->last_name ?? '')) ?: 'Another guest')
                    : 'Another guest';

                return ['error' => [
                    'message' => "{$name} is checking out these items right now. Please try again in a moment.",
                    'status' => 409,
                ]];
            }

            $blocking = PaymentGuardService::activePaymentsCovering($lockedOrders->pluck('id'))
                ->first(fn (OrderPayment $payment) => (int) $payment->customer_id !== (int) $customer->id);

            if ($blocking) {
                return ['error' => [
                    'message' => 'A payment is already in progress for these items.',
                    'status' => 409,
                ]];
            }

            return ['held' => $this->checkoutHolds->hold((int) $customer->id, $lockedOrders)];
        });

        if (isset($result['error'])) {
            return response()->json(
                ['message' => $result['error']['message']],
                $result['error']['status'],
            );
        }

        $this->broadcastHoldChange($payerSession, $customer, $result['held'], true);

        return response()->json([
            'held' => $result['held'],
            'expires_in_minutes' => CheckoutHoldService::TTL_MINUTES,
        ]);
    }

    /**
     * DELETE /api/customer/payments/checkout-hold
     *
     * The way back out of the payment step: cancels this customer's Stripe
     * intent if one was created, drops their hold, and tells the table both
     * are gone so the items become selectable again.
     */
    public function releaseCheckoutHold(Request $request): JsonResponse
    {
        $customer = $request->user();
        $payerSession = $this->activeSession((int) $customer->id, $request);

        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $tableSessionIds = $this->orderSessions->groupSessionIds($payerSession);

        $result = DB::transaction(function () use ($customer, $tableSessionIds) {
            // Every payment this customer opened from the checkout, not just the
            // Stripe ones. A cash request carries no intent id, so filtering on
            // one left it alive and holding payment_pending after the customer
            // had walked back out of the step that created it — an order locked
            // against a collection nobody was coming to make. cancelAbandoned-
            // Payment already skips the Stripe call when there is no intent.
            $payments = OrderPayment::where('customer_id', $customer->id)
                ->whereIn('table_scan_session_id', $tableSessionIds)
                ->whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
                ->lockForUpdate()
                ->get();

            if ($payments->contains(fn (OrderPayment $payment) => $payment->status === 'processing')) {
                abort(409, 'A payment is currently processing and cannot be canceled.');
            }

            $affected = collect();
            foreach ($payments as $payment) {
                $affected = $affected->merge($this->cancelAbandonedPayment($payment));
            }

            $held = Order::whereIn('table_scan_session_id', $tableSessionIds)
                ->where('checkout_hold_by', $customer->id)
                ->lockForUpdate()
                ->get();

            $releasedOrderIds = $affected
                ->merge($this->checkoutHolds->release((int) $customer->id, $held))
                ->unique()
                ->values();

            $merged = $this->mergeEditableSessions(
                $held->pluck('table_scan_session_id')
                    ->merge(Order::whereIn('id', $releasedOrderIds)->pluck('table_scan_session_id')),
            );

            return [
                'order_ids' => $releasedOrderIds
                    ->merge($merged['order_ids'])
                    ->diff($merged['removed_order_ids'])
                    ->unique()
                    ->values(),
                'removed_order_ids' => $merged['removed_order_ids'],
                'removed_item_ids' => $merged['removed_item_ids'],
            ];
        });

        $this->broadcastHoldChange(
            $payerSession,
            $customer,
            $result['order_ids']->all(),
            false,
            $result['removed_order_ids'],
            $result['removed_item_ids'],
        );

        return response()->json([
            'released' => $result['order_ids']->all(),
            'state_patch' => $this->statePatches->build(
                'payment.canceled',
                $result['order_ids'],
                removedOrderIds: $result['removed_order_ids'],
                removedItemIds: $result['removed_item_ids'],
            ),
        ]);
    }

    /**
     * Tell the rest of the table that a checkout claim was taken or dropped, so
     * their screens lock and unlock without waiting for a refresh.
     *
     * @param  array<int, int>  $orderIds
     */
    private function broadcastHoldChange(
        TableScanSession $payerSession,
        $customer,
        array $orderIds,
        bool $held,
        iterable $removedOrderIds = [],
        iterable $removedItemIds = [],
    ): void {
        $removedOrderIds = collect($removedOrderIds);
        $removedItemIds = collect($removedItemIds);

        if ($orderIds === [] && $removedOrderIds->isEmpty()) {
            return;
        }

        $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
        $snapshots = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('id', $orderIds)
            ->get()
            ->map(fn (Order $order) => NotificationService::orderSnapshot($order))
            ->values()
            ->all();

        $this->orderSessions->notifyCustomers(
            $payerSession,
            'payment_updated',
            $held
                ? "{$customerName} started checking out."
                : "{$customerName} left the checkout.",
            [
                'template' => $held ? 'payment.checkout_started' : 'payment.canceled',
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'order_snapshots' => $snapshots,
                'state_patch' => $this->statePatches->build(
                    $held ? 'payment.pending' : 'payment.canceled',
                    $orderIds,
                    removedOrderIds: $removedOrderIds,
                    removedItemIds: $removedItemIds,
                ),
            ],
        );
    }

    public function activeIntent(Request $request): JsonResponse
    {
        $customer = $request->user();
        $payerSession = $this->activeSession((int) $customer->id, $request);

        if (! $payerSession) {
            return response()->json(['active' => false]);
        }

        $tableSessionIds = $this->orderSessions->groupSessionIds($payerSession);

        $payment = OrderPayment::where('customer_id', $customer->id)
            ->whereIn('table_scan_session_id', $tableSessionIds)
            ->whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
            ->whereNotNull('stripe_payment_intent_id')
            ->latest()
            ->first();

        if (! $payment) {
            return response()->json(['active' => false]);
        }

        $intent = $this->stripe->retrievePaymentIntent($payment->stripe_payment_intent_id);
        $this->syncPaymentIntentStatus($payment, $intent);

        if (in_array($payment->refresh()->status, self::TERMINAL_PAYMENT_STATUSES, true)) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'paymentIntentId' => $intent['id'],
            'clientSecret' => $intent['client_secret'] ?? null,
            'status' => $intent['status'] ?? null,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
        ]);
    }

    /**
     * DELETE /api/customer/payments/intent
     *
     * Cancels the customer's own active Stripe intent at the current table
     * (the checkout "back" action), resets payment_pending on every covered
     * order, and broadcasts the unlock to the table. A payment already
     * processing a charge cannot be canceled.
     */
    public function cancelIntent(Request $request): JsonResponse
    {
        $customer = $request->user();
        $payerSession = $this->activeSession((int) $customer->id, $request);

        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $tableSessionIds = TableScanSession::where('vendor_id', $payerSession->vendor_id)
            ->where('restaurant_table_id', $payerSession->restaurant_table_id)
            ->where('status', 'active')
            ->pluck('id');

        $result = DB::transaction(function () use ($customer, $tableSessionIds) {
            $payments = OrderPayment::where('customer_id', $customer->id)
                ->whereIn('table_scan_session_id', $tableSessionIds)
                ->whereNotIn('status', self::TERMINAL_PAYMENT_STATUSES)
                ->whereNotNull('stripe_payment_intent_id')
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                return null;
            }

            if ($payments->contains(fn (OrderPayment $payment) => $payment->status === 'processing')) {
                abort(409, 'A payment is currently processing and cannot be canceled.');
            }

            $affected = collect();
            foreach ($payments as $payment) {
                $affected = $affected->merge($this->cancelAbandonedPayment($payment));
            }

            $affected = $affected->unique()->values();
            $merged = $this->mergeEditableSessions(
                Order::whereIn('id', $affected)->pluck('table_scan_session_id'),
            );

            return [
                'order_ids' => $affected
                    ->merge($merged['order_ids'])
                    ->diff($merged['removed_order_ids'])
                    ->unique()
                    ->values(),
                'removed_order_ids' => $merged['removed_order_ids'],
                'removed_item_ids' => $merged['removed_item_ids'],
            ];
        });

        $statePatch = $this->statePatches->build(
            'payment.canceled',
            $result ? $result['order_ids'] : [],
            removedOrderIds: $result ? $result['removed_order_ids'] : [],
            removedItemIds: $result ? $result['removed_item_ids'] : [],
        );

        if ($result !== null && ($result['order_ids']->isNotEmpty() || $result['removed_order_ids']->isNotEmpty())) {
            $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
            $snapshots = Order::with('paidBy:id,first_name,last_name')
                ->whereIn('id', $result['order_ids'])
                ->get()
                ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                ->values()->all();
            $this->orderSessions->notifyCustomers(
                $payerSession,
                'payment_updated',
                "{$customerName} canceled the payment.",
                [
                    'template' => 'payment.canceled',
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'order_snapshots' => $snapshots,
                    'state_patch' => $statePatch,
                ],
            );
        }

        return response()->json([
            'canceled' => $result !== null,
            'state_patch' => $statePatch,
        ]);
    }

    /**
     * POST /api/customer/payments/create-intent
     */
    public function createIntent(Request $request): JsonResponse
    {
        $customer = $request->user();

        $payerSession = $this->activeSession((int) $customer->id, $request);
        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }
        if ($pending = $this->pendingCommandResponse($payerSession)) {
            return $pending;
        }

        $orders = $this->payableSessionOrders($payerSession, (int) $customer->id);

        if ($orders->isEmpty()) {
            $assignedElsewhere = Order::where('table_scan_session_id', $payerSession->id)
                ->whereNotIn('status', $this->eligibleExcludedStatuses($payerSession))
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
        $orderIdsArray = $orderIds->map(fn ($id) => (int) $id)->values()->all();

        foreach ($orders->pluck('table_scan_session_id')->filter()->unique() as $sessionId) {
            $unboundCount = CartItem::where('table_scan_session_id', $sessionId)
                ->whereNull('order_id')
                ->count();
            if ($unboundCount > 0 && ! $this->orderSessions->isOffPremise($payerSession)) {
                return response()->json([
                    'message' => 'You have items in your cart that have not been submitted. Please confirm your full order before paying.',
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
            'order_ids' => json_encode($orderIdsArray),
        ];

        if ($order->table_scan_session_id) {
            $metadata['table_session_id'] = (string) $order->table_scan_session_id;
        }

        try {
            $intent = DB::transaction(function () use ($order, $orderIds, $orderIdsArray, $amounts, $customer, $settings, $amount, $currency, $metadata) {
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

                    if (($existingIntent['status'] ?? null) === 'processing') {
                        return $existingIntent;
                    }

                    if (($existingIntent['status'] ?? null) !== 'canceled') {
                        $coveredIds = $this->paymentOrders($existingPayment)
                            ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
                        $expectedIds = $orderIds->map(fn ($id) => (int) $id)->sort()->values()->all();

                        if ($coveredIds === $expectedIds) {
                            return $existingIntent;
                        }

                        // The payable set changed since this intent was created
                        // (orders were covered or released) — cancel it and
                        // start a fresh intent for the current set.
                        try {
                            $this->stripe->cancelPaymentIntent($existingPayment->stripe_payment_intent_id);
                        } catch (StripeInvalidRequestException $e) {
                            $refreshed = $this->stripe->retrievePaymentIntent($existingPayment->stripe_payment_intent_id);
                            if (($refreshed['status'] ?? null) === 'succeeded') {
                                $this->syncPaymentIntentStatus($existingPayment, $refreshed);

                                return $refreshed;
                            }
                        }

                        $existingPayment->update([
                            'status' => 'canceled',
                            'failed_at' => $existingPayment->failed_at ?? now(),
                        ]);
                    } else {
                        $this->syncPaymentIntentStatus($existingPayment, $existingIntent);
                    }
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

                $this->bindOffPremiseDraftItems($lockedOrders);

                $intent = $this->stripe->createPaymentIntent(
                    $this->stripeAmountMinor($amount, $currency),
                    $currency,
                    $settings->stripe_account_id,
                    $metadata
                );

                $payment = OrderPayment::create([
                    'order_id' => $order->id,
                    'order_ids' => $orderIdsArray,
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

                    PaymentGuardService::freezeDraftItems($lockedOrder);
                }

                return $intent;
            });
        } catch (StripeInvalidRequestException $e) {
            \Log::error("Stripe payment intent failed for vendor {$order->vendor_id}: {$e->getMessage()}");

            return response()->json([
                'message' => 'Online payments are not available for this restaurant yet. Please pay on-site.',
            ], 422);
        }

        $statePatch = $this->statePatches->build('payment.initiated', $orderIds);

        if ($order->table_scan_session_id && $order->tableScanSession) {
            $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
            $snapshots = Order::with('paidBy:id,first_name,last_name')
                ->whereIn('id', $orderIds)->get()
                ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                ->values()->all();
            $this->orderSessions->notifyCustomers(
                $order->tableScanSession,
                'payment_updated',
                "{$customerName} initiated a payment.",
                [
                    'template' => 'payment.initiated',
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'order_id' => $order->id,
                    'order_snapshots' => $snapshots,
                    'state_patch' => $statePatch,
                ],
            );
        }

        return response()->json([
            'clientSecret' => $intent['client_secret'],
            'paymentIntentId' => $intent['id'],
            'state_patch' => $statePatch,
        ]);
    }

    /**
     * POST /api/customer/payments/request-cash
     */
    public function requestCash(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'tip_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $customer = $request->user();
        $payerSession = $this->activeSession((int) $customer->id, $request);
        if (! $payerSession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }
        if ($pending = $this->pendingCommandResponse($payerSession)) {
            return $pending;
        }

        $payerSession->loadMissing('vendor.vendorSetting');
        $settings = $payerSession->vendor?->vendorSetting;
        $acceptsCash = $this->orderSessions->isOffPremise($payerSession)
            ? (bool) (($settings?->accept_on_site ?? true)
                && ($settings?->accept_pickup_cash ?? true))
            : (bool) ($settings?->accept_on_site ?? true);

        if (! $acceptsCash) {
            return response()->json([
                'message' => $this->orderSessions->isOffPremise($payerSession)
                    ? 'Cash payment is not available for pickup orders at this restaurant.'
                    : 'On-site payment is not available at this restaurant.',
            ], 422);
        }

        // Match createIntent(): the authenticated payer and their active table
        // visit are authoritative. This includes the payer's own eligible
        // orders plus tablemate orders explicitly assigned to them.
        $orders = $this->payableSessionOrders($payerSession, (int) $customer->id);

        if ($orders->isEmpty()) {
            $assignedElsewhere = Order::where('table_scan_session_id', $payerSession->id)
                ->whereNotIn('status', $this->eligibleExcludedStatuses($payerSession))
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
        $orderIdsArray = $orderIds->map(fn ($id) => (int) $id)->values()->all();

        foreach ($orders->pluck('table_scan_session_id')->filter()->unique() as $sessionId) {
            $unboundCount = CartItem::where('table_scan_session_id', $sessionId)
                ->whereNull('order_id')
                ->count();
            if ($unboundCount > 0 && ! $this->orderSessions->isOffPremise($payerSession)) {
                return response()->json([
                    'message' => 'You have items in your cart that have not been submitted. Please confirm your full order before paying.',
                    'unbound_item_count' => $unboundCount,
                ], 422);
            }
        }

        $amounts = $orders->mapWithKeys(fn (Order $coveredOrder) => [
            $coveredOrder->id => $this->finalOrderAmount($coveredOrder),
        ]);
        $baseAmount = round((float) $amounts->sum(), 2);
        $tipAmount = round((float) ($data['tip_amount'] ?? 0), 2);

        $tipOrder = $orders->first(
            fn (Order $coveredOrder) => $this->orderOwnerCustomerId($coveredOrder) === (int) $customer->id
        );

        if ($tipAmount > 0 && ! $tipOrder) {
            return response()->json([
                'message' => 'A tip cannot be added when paying only for another customer\'s orders.',
            ], 422);
        }

        $amount = round($baseAmount + $tipAmount, 2);

        if ($amount <= 0 || $amounts->contains(fn (float $orderAmount) => $orderAmount <= 0)) {
            return response()->json(['message' => 'Order amount must be greater than zero.'], 422);
        }

        $currency = $order->vendor?->currency ?? $order->currency ?? 'EUR';

        DB::transaction(function () use ($order, $orderIds, $orderIdsArray, $amounts, $customer, $amount, $currency, $data, $tipAmount, $tipOrder) {
            $lockedOrders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();

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

            $this->bindOffPremiseDraftItems($lockedOrders);

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'order_ids' => $orderIdsArray,
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
                    'order_ids' => json_encode($orderIdsArray),
                ],
            ]);

            $payment->orders()->attach($amounts->mapWithKeys(fn ($orderAmount, $id) => [
                $id => ['amount' => $orderAmount],
            ])->all());

            foreach ($lockedOrders as $lockedOrder) {
                $lockedOrder->update([
                    'amount' => $amounts[$lockedOrder->id],
                    'service_fee' => $lockedOrder->service_fee ?? 0,
                    'tip_amount' => $tipOrder && $lockedOrder->is($tipOrder) ? $tipAmount : 0,
                    'currency' => strtoupper($currency),
                    'payment_method' => 'cash',
                    'payment_pending' => true,
                    'payment_received' => false,
                ]);

                PaymentGuardService::freezeDraftItems($lockedOrder);
            }
        });

        $statePatch = $this->statePatches->build('payment.cash_requested', $orderIds);

        $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
        if ($order->tableScanSession) {
            $snapshots = Order::with('paidBy:id,first_name,last_name')
                ->whereIn('id', $orderIds)->get()
                ->map(fn (Order $o) => NotificationService::orderSnapshot($o))
                ->values()->all();
            $this->orderSessions->notifyCustomers(
                $order->tableScanSession,
                'payment_updated',
                "{$customerName} requested cash payment.",
                [
                    'template' => 'payment.cash_requested',
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'order_id' => $order->id,
                    'notes' => $data['notes'] ?? null,
                    'order_snapshots' => $snapshots,
                    'state_patch' => $statePatch,
                ],
            );
        }

        return response()->json([
            'message' => $this->orderSessions->isOffPremise($payerSession)
                ? 'Cash payment requested. Please pay when collecting the order.'
                : 'Cash payment requested. A waiter will come to your table.',
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'state_patch' => $statePatch,
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

                PaymentGuardService::freezeDraftItems($coveredOrder);

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

        $statePatch = $this->statePatches->build('payment.updated', $coveredOrders->pluck('id'));

        if ($order->table_scan_session_id && $order->tableScanSession) {
            $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
            $snapshots = $coveredOrders->map(fn (Order $o) => NotificationService::orderSnapshot($o->fresh()->load('paidBy')))
                ->values()->all();
            $this->orderSessions->notifyCustomers(
                $order->tableScanSession,
                'payment_updated',
                "{$customerName} updated the payment.",
                [
                    'template' => 'payment.updated',
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'order_snapshots' => $snapshots,
                    'state_patch' => $statePatch,
                ],
            );
        }

        return response()->json([
            'clientSecret' => $updatedIntent['client_secret'],
            'paymentIntentId' => $updatedIntent['id'],
            'state_patch' => $statePatch,
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

        $wasSucceeded = $payment->status === 'succeeded';
        $this->syncPaymentIntentStatus($payment, $intent);

        $completedNow = ! $wasSucceeded && ($intent['status'] ?? null) === 'succeeded';
        $statePatch = $this->statePatches->build(
            $completedNow ? 'payment.completed' : 'payment.verified',
            $this->paymentOrders($payment)->pluck('id'),
        );

        // The webhook may lag behind the client's redirect — when verification
        // is what first observes the success, push the table update from here.
        if ($completedNow) {
            $this->notifyPaymentCompleted($payment, $statePatch);
        }

        return response()->json([
            'status' => $intent['status'] ?? $payment->fresh()->status,
            'orderStatus' => $this->orderStatus($intent['status'] ?? $payment->status),
            'state_patch' => $statePatch,
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

        $wasSucceeded = $payment->status === 'succeeded';
        $this->syncPaymentIntentStatus($payment, $intent, $type);

        // Skip the broadcast when verify() already observed the success —
        // the table customers were notified at that point.
        if ($type === 'payment_intent.succeeded' && ! $wasSucceeded) {
            $this->notifyPaymentCompleted($payment);
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

    private function activeSession(int $customerId, ?Request $request = null): ?TableScanSession
    {
        if ($request) {
            return $this->orderSessions->activeForCustomer($customerId, $request);
        }

        return TableScanSession::query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->latest('scanned_at')
            ->latest('id')
            ->first();
    }

    /**
     * @param  iterable<int|string|null>  $sessionIds
     * @return array{order_ids: Collection<int, int>, removed_order_ids: Collection<int, int>, removed_item_ids: Collection<int, int>}
     */
    private function mergeEditableSessions(iterable $sessionIds): array
    {
        $result = [
            'order_ids' => collect(),
            'removed_order_ids' => collect(),
            'removed_item_ids' => collect(),
        ];

        foreach (collect($sessionIds)->filter()->map(fn ($id) => (int) $id)->unique() as $sessionId) {
            $merged = $this->editableOrderMerges->mergeForSession($sessionId);
            foreach (array_keys($result) as $key) {
                $result[$key] = $result[$key]->merge($merged[$key])->unique()->values();
            }
        }

        return $result;
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
        $activeSessionIds = $this->orderSessions->groupSessionIds($payerSession);

        return Order::with([
            'vendor.vendorSetting',
            'vendor.countryRecord',
            'tableScanSession',
            'paidBy:id,first_name,last_name',
        ])
            ->whereIn('table_scan_session_id', $activeSessionIds)
            ->whereNotIn('status', $this->eligibleExcludedStatuses($payerSession))
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

    /** @return array<int, string> */
    private function eligibleExcludedStatuses(TableScanSession $session): array
    {
        return $this->orderSessions->isOffPremise($session)
            ? [Order::STATUS_CANCELLED]
            : [Order::STATUS_DRAFT, Order::STATUS_CANCELLED];
    }

    private function bindOffPremiseDraftItems(EloquentCollection $orders): void
    {
        foreach ($orders as $order) {
            if ($order->status !== Order::STATUS_DRAFT) {
                continue;
            }

            $session = $order->tableScanSession;
            if (! $session || ! $this->orderSessions->isOffPremise($session)) {
                continue;
            }

            PaymentGuardService::freezeDraftItems($order);
        }
    }

    /**
     * Cancel the customer's own abandoned intent, mark the payment canceled,
     * and reset payment_pending on the unpaid orders it covered. Returns the
     * covered order ids. Aborts with 409 when the intent turns out to be
     * completed (or otherwise uncancelable) on Stripe's side.
     */
    private function cancelAbandonedPayment(OrderPayment $payment): Collection
    {
        if ($payment->stripe_payment_intent_id) {
            try {
                $this->stripe->cancelPaymentIntent($payment->stripe_payment_intent_id);
            } catch (StripeInvalidRequestException $e) {
                $intent = $this->stripe->retrievePaymentIntent($payment->stripe_payment_intent_id);
                if (($intent['status'] ?? null) === 'succeeded') {
                    $this->syncPaymentIntentStatus($payment, $intent);
                    abort(409, 'The payment has already completed.');
                }
                if (($intent['status'] ?? null) !== 'canceled') {
                    abort(409, 'Orders with an active payment cannot be released.');
                }
            }
        }

        $payment->update([
            'status' => 'canceled',
            'failed_at' => $payment->failed_at ?? now(),
        ]);

        $coveredOrderIds = $this->paymentOrders($payment)->pluck('id');

        if ($coveredOrderIds->isNotEmpty()) {
            Order::whereIn('id', $coveredOrderIds)
                ->where('payment_received', false)
                ->update([
                    'payment_pending' => false,
                    // The checkout that took the hold is over either way.
                    'checkout_hold_by' => null,
                    'checkout_hold_at' => null,
                ]);

            // The caller releases every order in the checkout first, then
            // consolidates all editable siblings atomically. Thawing one order
            // here would race that merge and could hand its rows to the wrong
            // draft while another order in the same checkout is still locked.
        }

        return $coveredOrderIds;
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
            : new EloquentCollection;
    }

    private function notifyPaymentCompleted(OrderPayment $payment, ?array $statePatch = null): void
    {
        if (! $payment->table_scan_session_id) {
            return;
        }

        $session = $payment->order?->tableScanSession
            ?? $this->paymentOrders($payment)->first()?->tableScanSession;

        if (! $session) {
            return;
        }

        $snapshots = $this->paymentOrders($payment)
            ->map(fn (Order $o) => NotificationService::orderSnapshot($o->fresh()->load('paidBy')))
            ->values()->all();
        $statePatch ??= $this->statePatches->build(
            'payment.completed',
            $this->paymentOrders($payment)->pluck('id'),
        );

        $this->orderSessions->notifyCustomers(
            $session,
            'payment_updated',
            'A payment has been completed on this table.',
            [
                'template' => 'payment.completed',
                'customer_id' => $payment->customer_id,
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'order_snapshots' => $snapshots,
                'state_patch' => $statePatch,
            ],
        );
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
        $ownedQuery = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category')
            ->where(function (Builder $query) use ($order) {
                $query->where('order_id', $order->id);

                if (PaymentGuardService::orderClaimsUnboundItems($order)
                    && $order->tableScanSession
                    && $this->orderSessions->isOffPremise($order->tableScanSession)) {
                    $query->orWhere(function (Builder $open) use ($order) {
                        $open->where('table_scan_session_id', $order->table_scan_session_id)
                            ->whereNull('order_id');
                    });
                }
            });

        $owned = $ownedQuery->get();

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
            'payment_method_details' => $intent['payment_method_details'] ?? $payment->payment_method_details,
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
                $wasPaymentReceived = (bool) $order->payment_received;
                $updates = [
                    'payment_method' => 'stripe',
                    'transaction_id' => $payment->stripe_payment_intent_id,
                    'payment_pending' => false,
                    'checkout_hold_by' => null,
                    'checkout_hold_at' => null,
                    'payment_received' => true,
                    'payment_confirmed_at' => $order->payment_confirmed_at ?? $now,
                ];

                if ($order->tableScanSession
                    && $this->orderSessions->isOffPremise($order->tableScanSession)
                    && $order->status === Order::STATUS_DRAFT) {
                    $updates['status'] = Order::STATUS_CONFIRMED;
                    $updates['confirmed_at'] = $order->confirmed_at ?? $now;
                }

                $order->update($updates);

                if ($order->tableScanSession
                    && $this->orderSessions->isOffPremise($order->tableScanSession)) {
                    CartItem::query()
                        ->where('order_id', $order->id)
                        ->whereNull('received_at')
                        ->update(['received_at' => $now]);

                    if (! $wasPaymentReceived) {
                        $this->kitchenReleases->notifyPaidOffPremiseOrder(
                            $order->fresh(['tableScanSession.restaurantTable']),
                            'customer',
                            (int) $payment->customer_id,
                        );
                    }
                }
            }

            return;
        }

        if (in_array($status, ['failed', 'canceled'], true)) {
            foreach ($orders as $order) {
                $order->update([
                    'payment_method' => 'stripe',
                    'transaction_id' => $payment->stripe_payment_intent_id,
                    'payment_pending' => false,
                    'checkout_hold_by' => null,
                    'checkout_hold_at' => null,
                    'payment_received' => false,
                    'payment_note' => 'Stripe payment was not completed.',
                ]);
            }

            // A decline unlocks the order exactly as pressing Back does, so it
            // has to fold the temporary order back too. Only the explicit exits
            // used to merge, which left a card failure — the one exit the
            // customer never chose — showing the table two order cards forever.
            $this->mergeEditableSessions($orders->pluck('table_scan_session_id'));

            return;
        }

        foreach ($orders as $order) {
            $order->update([
                'payment_method' => 'stripe',
                'transaction_id' => $payment->stripe_payment_intent_id,
                'payment_pending' => true,
                'payment_received' => false,
            ]);

            PaymentGuardService::freezeDraftItems($order);
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
