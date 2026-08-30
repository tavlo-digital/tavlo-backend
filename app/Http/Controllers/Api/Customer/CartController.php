<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\CustomerCommandBus;
use App\Services\LocaleService;
use App\Services\MenuCustomizationService;
use App\Services\NotificationService;
use App\Services\OrderAmountRecalculationService;
use App\Services\OrderSessionService;
use App\Services\OrderSharingService;
use App\Services\PaymentGuardService;
use App\Services\ShareOrderService;
use App\Services\TableStatePatchService;
use App\Services\TaxCalculationService;
use App\Services\VendorDateTimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly LocaleService $locales,
        private readonly MenuCustomizationService $customizations,
        private readonly TableStatePatchService $statePatches,
        private readonly OrderSharingService $orderSharing,
        private readonly CustomerCommandBus $commands,
        private readonly OrderSessionService $orderSessions,
        private readonly OrderAmountRecalculationService $orderAmounts,
    ) {}

    private function queuedCommandResponse(
        Request $request,
        TableScanSession $session,
        string $operation,
        array $payload,
    ): ?JsonResponse {
        // Only queue when the async system is enabled AND a worker is actually
        // draining the queue. If no worker is alive, fall through to synchronous
        // processing so the write is not silently lost in an undrained queue.
        // The sync flag is checked before workerAlive() so requests already
        // running synchronously (e.g. the queue worker re-invoking this action)
        // skip the redundant Redis heartbeat lookup.
        if (! $this->commands->enabled()
            || $request->attributes->get('customer_command_sync')
            || ! $this->commands->workerAlive()) {
            return null;
        }

        try {
            $command = $this->commands->dispatch(
                $request->user(),
                $session,
                $operation,
                $payload,
                $request->header('Accept-Language'),
                $this->orderSessions->mode($request),
            );
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }

        return response()->json([
            'message' => 'Change accepted.',
            ...$command,
        ], 202);
    }

    public function commandStatus(Request $request, string $commandId): JsonResponse
    {
        $status = $this->commands->status($commandId);
        if (! $status) {
            return response()->json(['message' => 'Command not found or expired.'], 404);
        }
        if ((int) ($status['customer_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json(['message' => 'Command not found or expired.'], 404);
        }

        unset($status['customer_id']);

        return response()->json($status);
    }

    /**
     * Resolve the authenticated customer's active session.
     * Returns null if none exists.
     */
    private function activeSession(Request $request): ?TableScanSession
    {
        return $this->orderSessions->activeForCustomer((int) $request->user()->id, $request);
    }

    /**
     * Resolve the sharing context in one query. The regular relation-based
     * helper would otherwise require separate vendor and settings queries.
     */
    private function activeSharingSession(Request $request): ?TableScanSession
    {
        $query = TableScanSession::query()
            ->select('table_scan_sessions.*')
            ->addSelect([
                'sharing_vendor_country' => Vendor::query()
                    ->select('country')
                    ->whereColumn('vendors.id', 'table_scan_sessions.vendor_id')
                    ->limit(1),
                'sharing_service_fee_rate' => DB::table('vendor_settings')
                    ->select('service_fee_rate')
                    ->whereColumn('vendor_settings.vendor_id', 'table_scan_sessions.vendor_id')
                    ->limit(1),
            ])
            ->where('customer_id', $request->user()->id)
            ->where('status', 'active');

        return $this->orderSessions->applyMode($query, $request)
            ->latest('scanned_at')
            ->latest('id')
            ->first();
    }

    /**
     * Ids of all active sessions at the same table as $session.
     *
     * @return array<int, int>
     */
    private function tableSessionIds(TableScanSession $session): array
    {
        return $this->orderSessions->groupSessionIds($session);
    }

    private function customerName($customer): string
    {
        return trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
    }

    /**
     * GET /api/customer/cart
     *
     * Returns the full table cart split into per-person personal items.
     */
    public function index(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);

        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $payload = $this->buildCartPayload($mySession, $request);

        return response()->json($this->cartPayloadForSession($payload, $mySession));
    }

    /**
     * Build the table cart payload shared by GET /cart and realtime cart
     * pushes. People entries carry no is_me flag — each consumer derives it
     * from its own session or customer id.
     */
    private function buildCartPayload(TableScanSession $mySession, Request $request): array
    {
        $sessionIds = $this->tableSessionIds($mySession);

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            // Eager-load item name translations so menuItemName() resolves from
            // memory instead of firing one query per cart item (N+1).
            'cartItems.menuItem.itemTranslations:id,menu_item_id,language,name',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate,supported_languages',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        // A draft is not a submitted order, so its items are still cart items.
        // They carry an order_id once the draft locks, and the customer must
        // keep seeing what they added even while somebody else pays for it.
        $draftOrderIds = Order::whereIn('table_scan_session_id', $sessionIds)
            ->where('status', 'draft')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $mySession = $sessions->firstWhere('id', $mySession->id) ?? $mySession;
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;
        $locale = $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';

        $translateTaxGroups = fn (array $groups) => array_map(fn (array $g) => array_merge($g, [
            'label' => $this->locales->translatedTaxCategoryName($g['tax_category'], $vendorCountry, $locale),
        ]), $groups);

        $people = $sessions->map(function (TableScanSession $s) use ($draftOrderIds, $vendorCountry, $serviceFeeRate, $vendor, $locale, $translateTaxGroups) {
            // Ownership decides visibility: a line leaves its owner's cart only
            // once the owner's own order is submitted. Another guest taking a
            // share of it never hides it — the owner still owes their half and
            // has to be able to see and confirm it. That share can even land on
            // a side order, which ShareOrderService creates as `confirmed`, so
            // keying visibility off shared_order_ids emptied the owner's cart
            // the moment somebody split one of their items. Shared lines come
            // back locked, not invisible.
            $personItems = $s->cartItems
                ->filter(fn (CartItem $item) => $item->order_id === null
                    || in_array((int) $item->order_id, $draftOrderIds, true))
                ->values();

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, $serviceFeeRate);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Waiter',
                'personal_items' => $personItems
                    ->map(fn (CartItem $item) => $this->itemPayload($item, $vendorCountry, $vendor, $locale))
                    ->all(),
                'tax_groups' => $translateTaxGroups($personTaxGroups),
                'totals' => $personTotals,
            ];
        });

        $table = $mySession->restaurantTable;

        return [
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name' => $vendor->restaurant_name ?? null,
            ] : null,
            'people' => $people->values()->all(),
        ];
    }

    /**
     * Realtime metadata containing the fresh cart payload so customer clients
     * can apply it directly without another network request.
     */
    private function realtimeCartMetadata(TableScanSession $mySession, Request $request): array
    {
        $cart = $this->buildCartPayload($mySession, $request);

        return [
            'cart' => $cart,
            'payload_version' => now()->getTimestampMs(),
        ];
    }

    private function cartPayloadForSession(array $payload, TableScanSession $mySession): array
    {
        $payload['people'] = collect($payload['people'])
            ->map(fn (array $person) => [...$person, 'is_me' => $person['session_id'] === $mySession->id])
            ->values()
            ->all();

        return $payload;
    }

    /**
     * POST /api/customer/cart/items
     *
     * Add an item to the authenticated customer's cart.
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'client_item_id' => ['sometimes', 'nullable', 'uuid'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_addons' => ['sometimes', 'array'],
            'paid_addons.*.id' => ['sometimes', 'integer', 'min:1'],
            'paid_addons.*.name' => ['sometimes', 'string', 'max:255'],
            'paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'free_addons' => ['sometimes', 'array'],
            'removed_items' => ['sometimes', 'array'],
            'removable_items' => ['sometimes', 'array'],
            'selected_modifiers' => ['sometimes', 'array'],
            'selected_modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.id' => ['sometimes', 'integer'],
            'selected_modifiers.*.option_ids' => ['sometimes', 'array'],
            'selected_modifiers.*.option_ids.*' => ['integer'],
            'selected_modifiers.*.options' => ['sometimes', 'array'],
            'modifiers' => ['sometimes', 'array'],
            'modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'modifiers.*.group_id' => ['sometimes', 'integer'],
            'modifiers.*.id' => ['sometimes', 'integer'],
            'modifiers.*.option_ids' => ['sometimes', 'array'],
            'modifiers.*.option_ids.*' => ['integer'],
            'modifiers.*.options' => ['sometimes', 'array'],
        ])->validate();

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        if ($queued = $this->queuedCommandResponse($request, $mySession, 'cart.add', $data)) {
            return $queued;
        }

        $menuItem = MenuItem::where('id', $data['menu_item_id'])
            ->where('vendor_id', $mySession->vendor_id)
            ->where('is_active', true)
            ->where('available', true)
            ->with(['modifierGroups' => fn ($q) => $q->where('is_active', true)
                ->orderByPivot('sort_order')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])])
            ->first();

        if (! $menuItem) {
            throw ValidationException::withMessages([
                'menu_item_id' => ['The selected menu item is not available for this table.'],
            ]);
        }

        $customizations = $this->normalizeCustomizations($menuItem, $data);

        // Find-or-create-or-increment must be serialized: two near-simultaneous
        // add-to-cart requests for the same session/item could otherwise both
        // read an empty cart and each insert a duplicate row, or clobber each
        // other's quantity. Locking the session row funnels concurrent adds for
        // this session through one at a time; the candidate cart rows are also
        // locked so the increment reads a committed quantity.
        $item = DB::transaction(function () use ($mySession, $data, $customizations) {
            TableScanSession::whereKey($mySession->id)->lockForUpdate()->first();

            // The client's id names this exact line, so an add that already
            // landed returns it untouched instead of merging again. Delivery is
            // at-least-once — a retried request, a replayed queue job or a
            // double tap must not quietly buy the guest a second helping.
            if (! empty($data['client_item_id'])) {
                $alreadyAdded = CartItem::where('table_scan_session_id', $mySession->id)
                    ->where('client_item_id', $data['client_item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($alreadyAdded) {
                    return $alreadyAdded;
                }
            }

            // Step-1 coverage and item sharing stay live and editable. Only an
            // order frozen at checkout is excluded, so adding the same product
            // increments the existing row (and updates every participant's
            // live share) until somebody reaches payment step 2.
            $editableOrder = $this->currentOpenOrder(
                (int) $mySession->customer_id,
                (int) $mySession->id,
            );
            $existing = CartItem::where('table_scan_session_id', $mySession->id)
                ->where('menu_item_id', $data['menu_item_id'])
                ->whereNull('received_at')
                ->lockForUpdate()
                ->get()
                ->first(fn (CartItem $cartItem) => ($cartItem->order_id === null
                        || ($editableOrder && (int) $cartItem->order_id === (int) $editableOrder->id))
                    && $this->lockedOrderForCartItem($cartItem) === null
                    && $this->cartCustomizationsMatch($cartItem, $customizations));

            if ($existing) {
                $existing->update([
                    'quantity' => $existing->quantity + ($data['quantity'] ?? 1),
                ]);

                return $existing;
            }

            return CartItem::create([
                'table_scan_session_id' => $mySession->id,
                'client_item_id' => $data['client_item_id'] ?? null,
                'menu_item_id' => $data['menu_item_id'],
                'order_id' => null,
                'quantity' => $data['quantity'] ?? 1,
                'notes' => $data['notes'] ?? null,
                'paid_addons' => $customizations['paid_addons'],
                'free_addons' => $customizations['free_addons'],
                'removed_items' => $customizations['removed_items'],
                'selected_modifiers' => $customizations['selected_modifiers'],
            ]);
        });

        $item->load([
            'menuItem:id,vendor_id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            'menuItem.itemTranslations:id,menu_item_id,language,name',
        ]);
        $repriced = $this->recalculateCartItemOrders($item, $mySession);

        $customerName = $this->customerName($request->user());
        $vendor = $mySession->vendor;
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $itemName = $item->menuItem && $vendor
            ? $this->customizations->menuItemName($item->menuItem, $vendor, $locale)
            : ($item->menuItem?->name ?? 'an item');
        $realtimeCart = $this->realtimeCartMetadata($mySession, $request);
        $this->orderSessions->notifyCustomers(
            $mySession,
            'cart_updated',
            "{$customerName} added {$itemName} to the cart.",
            [
                'template' => 'cart.item_added',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'menu_item_id' => $item->menu_item_id,
                'item_name' => $itemName,
                'command_id' => $request->attributes->get('customer_command_id'),
                'command_status' => $request->attributes->get('customer_command_id') ? 'completed' : null,
                // A mate covering or sharing this cart is looking at a total the
                // add just moved. The cart payload carries no order amounts, so
                // without this patch their number only refreshes on a refetch.
                'state_patch' => $this->statePatches->build(
                    'cart.item_added',
                    $repriced['order_ids'],
                    [(int) $item->id],
                    $repriced['removed_order_ids'],
                ),
                ...$realtimeCart,
            ],
        );

        return response()->json([
            ...$this->itemPayload($item, $mySession->vendor?->country, $vendor, $locale),
            'cart' => $this->cartPayloadForSession($realtimeCart['cart'], $mySession),
        ], 201);
    }

    /**
     * PATCH /api/customer/cart/items/{id}
     *
     * Update quantity or notes of a cart item owned by the current session.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_addons' => ['sometimes', 'array'],
            'paid_addons.*.id' => ['sometimes', 'integer', 'min:1'],
            'paid_addons.*.name' => ['sometimes', 'string', 'max:255'],
            'paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'free_addons' => ['sometimes', 'array'],
            'removed_items' => ['sometimes', 'array'],
            'removable_items' => ['sometimes', 'array'],
            'selected_modifiers' => ['sometimes', 'array'],
            'selected_modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.group_id' => ['sometimes', 'integer'],
            'selected_modifiers.*.id' => ['sometimes', 'integer'],
            'selected_modifiers.*.option_ids' => ['sometimes', 'array'],
            'selected_modifiers.*.option_ids.*' => ['integer'],
            'selected_modifiers.*.options' => ['sometimes', 'array'],
            'modifiers' => ['sometimes', 'array'],
            'modifiers.*.modifier_group_id' => ['sometimes', 'integer'],
            'modifiers.*.group_id' => ['sometimes', 'integer'],
            'modifiers.*.id' => ['sometimes', 'integer'],
            'modifiers.*.option_ids' => ['sometimes', 'array'],
            'modifiers.*.option_ids.*' => ['integer'],
            'modifiers.*.options' => ['sometimes', 'array'],
            'client_item_id' => ['sometimes', 'nullable', 'uuid'],
        ])->validate();

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        if ($queued = $this->queuedCommandResponse($request, $mySession, 'cart.update', [
            ...$data,
            'cart_item_id' => $id,
        ])) {
            return $queued;
        }

        $item = CartItem::where('table_scan_session_id', $mySession->id)
            ->where(function ($query) use ($id, $data) {
                $query->where('id', $id);
                if (! empty($data['client_item_id'])) {
                    $query->orWhere('client_item_id', $data['client_item_id']);
                }
            })
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        if ($item->received_at) {
            return response()->json([
                'message' => 'This item has already been submitted and cannot be modified. Please add a new item instead.',
            ], 409);
        }

        if ($lockedOrder = $this->lockedOrderForCartItem($item)) {
            return response()->json([
                'message' => 'This item is locked while its order is being paid.',
            ], 409);
        }

        $updates = array_filter(
            array_intersect_key($data, array_flip(['quantity', 'notes'])),
            fn ($v) => $v !== null
        );
        if (
            array_key_exists('paid_addons', $data)
            || array_key_exists('free_addons', $data)
            || array_key_exists('removed_items', $data)
            || array_key_exists('removable_items', $data)
            || array_key_exists('selected_modifiers', $data)
            || array_key_exists('modifiers', $data)
        ) {
            $item->load(['menuItem' => fn ($q) => $q->select('id', 'paid_addons', 'free_addons', 'removable_items')
                ->with(['modifierGroups' => fn ($g) => $g->where('is_active', true)
                    ->orderByPivot('sort_order')
                    ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])])]);
            $updates = array_merge($updates, $this->normalizeCustomizations($item->menuItem, $data, $item));
        }

        $item->update($updates);
        $repriced = $this->recalculateCartItemOrders($item, $mySession);
        $item->load([
            'menuItem:id,vendor_id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            'menuItem.itemTranslations:id,menu_item_id,language,name',
        ]);

        $customerName = $this->customerName($request->user());
        $vendor = $mySession->vendor;
        $locale = $vendor ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor) : 'en';
        $itemName = $item->menuItem && $vendor
            ? $this->customizations->menuItemName($item->menuItem, $vendor, $locale)
            : ($item->menuItem?->name ?? 'an item');
        $realtimeCart = $this->realtimeCartMetadata($mySession, $request);
        $this->orderSessions->notifyCustomers(
            $mySession,
            'cart_updated',
            "{$customerName} updated {$itemName} in the cart.",
            [
                'template' => 'cart.item_updated',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'menu_item_id' => $item->menu_item_id,
                'item_name' => $itemName,
                'command_id' => $request->attributes->get('customer_command_id'),
                'command_status' => $request->attributes->get('customer_command_id') ? 'completed' : null,
                'state_patch' => $this->statePatches->build(
                    'cart.item_updated',
                    $repriced['order_ids'],
                    [(int) $item->id],
                    $repriced['removed_order_ids'],
                ),
                ...$realtimeCart,
            ],
        );

        return response()->json([
            ...$this->itemPayload($item, $mySession->vendor?->country, $vendor, $locale),
            'cart' => $this->cartPayloadForSession($realtimeCart['cart'], $mySession),
        ]);
    }

    /**
     * DELETE /api/customer/cart/items/{id}
     *
     * Remove a cart item owned by the current session.
     */
    public function removeItem(Request $request, int $id): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'client_item_id' => ['sometimes', 'nullable', 'uuid'],
        ])->validate();

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        if ($queued = $this->queuedCommandResponse($request, $mySession, 'cart.remove', [
            'cart_item_id' => $id,
            'client_item_id' => $data['client_item_id'] ?? null,
        ])) {
            return $queued;
        }

        $item = CartItem::where('table_scan_session_id', $mySession->id)
            ->where(function ($query) use ($id, $data) {
                $query->where('id', $id);
                if (! empty($data['client_item_id'])) {
                    $query->orWhere('client_item_id', $data['client_item_id']);
                }
            })
            ->first();

        if (! $item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        if ($item->received_at) {
            return response()->json([
                'message' => 'This item has already been submitted and cannot be removed.',
            ], 409);
        }

        if ($lockedOrder = $this->lockedOrderForCartItem($item)) {
            return response()->json([
                'message' => 'This item is locked while its order is being paid.',
            ], 409);
        }

        $affectedOrderIds = $this->cartItemOrderIds($item);
        $itemName = $item->menuItem?->name ?? 'an item';
        $menuItemId = $item->menu_item_id;
        $removedItemId = (int) $item->id;
        $item->delete();
        $repriced = $this->recalculateOrderIds($affectedOrderIds, $mySession, true);

        $customerName = $this->customerName($request->user());
        $realtimeCart = $this->realtimeCartMetadata($mySession, $request);
        $this->orderSessions->notifyCustomers(
            $mySession,
            'cart_updated',
            "{$customerName} removed {$itemName} from the cart.",
            [
                'template' => 'cart.item_removed',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'menu_item_id' => $menuItemId,
                'item_name' => $itemName,
                'command_id' => $request->attributes->get('customer_command_id'),
                'command_status' => $request->attributes->get('customer_command_id') ? 'completed' : null,
                // Deleting a shared line drops it from every sharer's total, so
                // the patch has to carry the removal as well as the new amounts.
                'state_patch' => $this->statePatches->build(
                    'cart.item_removed',
                    $repriced['order_ids'],
                    [],
                    $repriced['removed_order_ids'],
                    [$removedItemId],
                ),
                ...$realtimeCart,
            ],
        );

        return response()->json([
            'cart' => $this->cartPayloadForSession($realtimeCart['cart'], $mySession),
        ]);
    }

    /**
     * GET /api/customer/table/order/start
     *
     * Returns a payment summary for the customer's current table:
     * - the authenticated customer's own line (name, item count, total)
     * - every active session at the same table (name, items, total)
     * - a flat list of every item on the table with an `is_mine` flag
     * - the table-wide grand total
     */
    public function orderStart(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        $sessionIds = $this->tableSessionIds($mySession);

        $sessions = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
            'cartItems.menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations',
            // Eager-load item name translations so menuItemName() resolves from
            // memory instead of firing one query per cart item (N+1).
            'cartItems.menuItem.itemTranslations:id,menu_item_id,language,name',
            'vendor:id,vendor_public_id,restaurant_name,country',
            'vendor.vendorSetting:id,vendor_id,service_fee_rate,supported_languages',
        ])
            ->whereIn('id', $sessionIds)
            ->get();

        $mySession = $sessions->firstWhere('id', $mySession->id) ?? $mySession;
        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;
        $locale = $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';
        $tableTotal = 0.0;
        $tableItemCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $vendorCountry, $serviceFeeRate, $locale, &$tableTotal, &$tableItemCount) {
            $personalTotal = 0.0;
            $personalCount = 0;
            $isMe = $s->id === $mySession->id;
            $itemTaxCategoryFn = fn (CartItem $item) => $item->menuItem?->tax_category ?? 'food';

            $personCartItems = $s->cartItems->filter(fn (CartItem $item) => $item->order_id === null);

            $items = $personCartItems
                ->map(function (CartItem $item) use ($isMe, $vendorCountry, $itemTaxCategoryFn, &$personalTotal, &$personalCount, &$tableTotal, &$tableItemCount) {
                    $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
                    $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
                    $itc = $itemTaxCategoryFn($item);

                    $personalTotal += $lineTotal;
                    $personalCount += $item->quantity;
                    $tableTotal += $lineTotal;
                    $tableItemCount += $item->quantity;

                    return [
                        'cart_item_id' => $item->id,
                        'menu_item_id' => $item->menu_item_id,
                        'name' => $item->menuItem && $vendor
                            ? $this->customizations->menuItemName($item->menuItem, $vendor, $locale)
                            : $item->menuItem?->name,
                        'image_url' => $item->menuItem?->image_url,
                        'quantity' => $item->quantity,
                        'unit_price' => $unitPrice,
                        'paid_addons' => $this->formatCartPaidAddons($item, $itc, $vendorCountry, $vendor, $locale),
                        'free_addons' => $this->formatCartNamedSelections($item, 'free_addons', $vendor, $locale),
                        'removed_items' => $this->formatCartNamedSelections($item, 'removed_items', $vendor, $locale),
                        'selected_modifiers' => $this->formatCartSelectedModifiers($item, $itc, $vendorCountry, $vendor, $locale),
                        'total_price' => $lineTotal,
                        'is_mine' => $isMe,
                    ];
                })->values();

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personCartItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, $serviceFeeRate);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'is_me' => $isMe,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Waiter',
                'item_count' => $personalCount,
                'total_price' => round($personalTotal, 2),
                'items' => $items,
                'tax_groups' => array_map(fn (array $g) => array_merge($g, [
                    'label' => $this->locales->translatedTaxCategoryName($g['tax_category'], $vendorCountry, $locale),
                ]), $personTaxGroups),
                'totals' => $personTotals,
            ];
        })->values();

        $table = $mySession->restaurantTable;

        return response()->json([
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'people' => $people,
            'summary' => [
                'item_count' => $tableItemCount,
                'total_price' => round($tableTotal, 2),
            ],
        ]);
    }

    /**
     * POST /api/customer/table/order/draft
     *
     * Create a draft order from the customer's own cart items. An optional
     * order-level note may be supplied.
     * Amount is computed live from owned cart_items at draft time. The order's
     * final amount is recalculated on confirm to include any shared-into items.
     */
    public function createOrderDraft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $noteAttributes = array_key_exists('note', $data)
            ? ['note' => $data['note']]
            : [];

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        if ($this->commands->enabled() && ! $this->commands->waitForSession((int) $mySession->id)) {
            return response()->json([
                'message' => 'Your latest cart changes are still being saved. Please retry shortly.',
                'code' => 'CUSTOMER_COMMANDS_PENDING',
                'retry_after_ms' => 250,
            ], 409);
        }

        $existingSubmittedOrder = $this->currentUnpaidSubmittedOrder($request->user()->id, $mySession->id);

        if ($existingSubmittedOrder) {
            if ($noteAttributes !== []) {
                $existingSubmittedOrder->update($noteAttributes);
            }

            return response()->json($this->buildTableHistoryResponse($mySession, $request));
        }

        $existingOrder = $this->currentOpenOrder($request->user()->id, $mySession->id);

        $myCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where('table_scan_session_id', $mySession->id)
            ->whereNull('order_id')
            ->get();

        $vendorCountry = $this->vendorCountry($mySession);
        $myTotal = 0.0;
        foreach ($myCartItems as $item) {
            $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $myTotal += $lineTotal / $shareCount;
        }
        $myTotal = round($myTotal, 2);

        $customerName = $this->customerName($request->user());

        if ($existingOrder) {
            $existingOrder->update([
                'amount' => $myTotal,
                ...$noteAttributes,
            ]);

            $history = $this->buildTableHistoryResponse($mySession, $request);

            $personSnapshot = collect($history['people'])
                ->first(fn (array $p) => $p['session_id'] === $mySession->id);

            $this->orderSessions->notifyCustomers(
                $mySession,
                'order_updated',
                "{$customerName} updated their order draft.",
                [
                    'template' => 'order.draft_updated',
                    'customer_id' => $request->user()->id,
                    'customer_name' => $customerName,
                    'order_id' => $existingOrder->id,
                    'order_snapshots' => [NotificationService::orderSnapshot($existingOrder->fresh()->load('paidBy'))],
                    'person_snapshot' => $personSnapshot,
                ],
            );

            return response()->json($history);
        }

        // Nothing to draft. This happens to a guest whose order somebody else
        // has covered: covering closes that order to new items and binds the
        // ones it had, so the next draft would be created with no items and no
        // amount — and then stand on the payment step as a second, empty order
        // card under the same name, which reads as a duplicate.
        if ($myCartItems->isEmpty()) {
            return response()->json($this->buildTableHistoryResponse($mySession, $request));
        }

        DB::transaction(function () use ($request, $mySession, $myTotal, $noteAttributes) {
            $currency = $mySession->vendor?->currency ?? 'EUR';

            Order::create([
                'order_public_id' => 'ord-'.Str::random(12),
                'customer_id' => $request->user()->id,
                'vendor_id' => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status' => 'draft',
                'draft_at' => now(),
                'amount' => $myTotal,
                'currency' => $currency,
                'payment_pending' => false,
                'payment_received' => false,
                ...$noteAttributes,
                'order_type' => $this->orderSessions->isOffPremise($mySession)
                    ? $mySession->type
                    : 'dine-in',
            ]);
        });

        $freshDraft = Order::where('table_scan_session_id', $mySession->id)
            ->where('customer_id', $request->user()->id)
            ->where('status', 'draft')
            ->latest()
            ->first();

        $history = $this->buildTableHistoryResponse($mySession, $request);

        $personSnapshot = collect($history['people'])
            ->first(fn (array $p) => $p['session_id'] === $mySession->id);

        $this->orderSessions->notifyCustomers(
            $mySession,
            'order_updated',
            "{$customerName} created an order draft.",
            [
                'template' => 'order.draft_created',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'order_snapshots' => $freshDraft
                    ? [NotificationService::orderSnapshot($freshDraft)]
                    : [],
                'person_snapshot' => $personSnapshot,
            ],
        );

        return response()->json($history, 201);
    }

    /**
     * PUT /api/customer/table/order/update/{order_id}
     *
     * Share or unshare a cart_item for the caller's order.
     * - shared_item:   append caller's order_id to that cart_item's shared_order_ids
     * - unshared_item: remove  caller's order_id from that cart_item's shared_order_ids (no-op if not present)
     * At least one field must be provided.
     */
    public function updateOrder(Request $request, int $order_id): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'shared_item' => ['nullable', 'integer'],
            'unshared_item' => ['nullable', 'integer'],
        ])->validate();

        if (empty($data['shared_item']) && empty($data['unshared_item'])) {
            return response()->json(['message' => 'Provide shared_item or unshared_item.'], 422);
        }

        $mySession = $this->activeSharingSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        if ($queued = $this->queuedCommandResponse($request, $mySession, 'order.share', [
            ...$data,
            'order_id' => $order_id,
        ])) {
            return $queued;
        }

        $result = $this->orderSharing->update(
            $order_id,
            (int) $request->user()->id,
            $mySession,
            $this->tableSessionIds($mySession),
            ! empty($data['shared_item']) ? (int) $data['shared_item'] : null,
            ! empty($data['unshared_item']) ? (int) $data['unshared_item'] : null,
            (string) ($mySession->getAttribute('sharing_vendor_country') ?: 'AT'),
            (float) ($mySession->getAttribute('sharing_service_fee_rate') ?: 0),
        );

        $customerName = $this->customerName($request->user());
        $this->orderSessions->notifyCustomers(
            $mySession,
            'order_updated',
            "{$customerName} updated item sharing on the order.",
            [
                'template' => 'order.sharing_updated',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'order_id' => $result['order']->id,
                'order_snapshots' => $result['order_snapshots'],
                'removed_order_ids' => $result['removed_order_ids'],
                'state_patch' => $result['state_patch'],
                'command_id' => $request->attributes->get('customer_command_id'),
                'command_status' => $request->attributes->get('customer_command_id') ? 'completed' : null,
            ],
        );

        return response()->json([
            'message' => 'Item sharing updated.',
            'order_snapshots' => $result['order_snapshots'],
            'removed_order_ids' => $result['removed_order_ids'],
            'state_patch' => $result['state_patch'],
        ]);
    }

    /**
     * POST /api/customer/table/order/confirmed
     *
     * Confirm the customer's latest draft order. Recomputes the final amount
     * from cart_items: owned items split by (1 + count(shared_order_ids)), plus a
     * share of every cart_item whose shared_order_ids contains this order's id.
     */
    public function createOrderConfirmed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $noteProvided = array_key_exists('note', $data);
        $noteAttributes = $noteProvided ? ['note' => $data['note']] : [];

        $customerId = $request->user()->id;

        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        if ($this->commands->enabled() && ! $this->commands->waitForSession((int) $mySession->id)) {
            return response()->json([
                'message' => 'Your latest cart changes are still being saved. Please retry shortly.',
                'code' => 'CUSTOMER_COMMANDS_PENDING',
                'retry_after_ms' => 250,
            ], 409);
        }

        $draftOrder = $this->currentDraftOrder($customerId, $mySession->id);
        $submittedOrder = $this->currentUnpaidSubmittedOrder($customerId, $mySession->id);
        $order = $submittedOrder ?? $draftOrder;

        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }

        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendorCountry = $this->vendorCountry($mySession);

        $openItems = CartItem::where('table_scan_session_id', $mySession->id)
            ->whereNull('order_id')
            ->with('menuItem:id,name,is_active,available')
            ->get();

        if ($openItems->isEmpty()) {
            // Payment verification confirms off-premise orders and binds their
            // items atomically. Older clients can still call this endpoint once
            // more after verification; treat that replay as a successful no-op
            // instead of reporting a false "cart is empty" failure.
            $alreadyFinalizedPayment = $this->orderSessions->isOffPremise($mySession)
                && Order::query()
                    ->whereIn('table_scan_session_id', $this->tableSessionIds($mySession))
                    ->where('payment_received', true)
                    ->where(function (Builder $payer) use ($customerId) {
                        $payer->where('customer_id', $customerId)
                            ->orWhere('paid_by', $customerId);
                    })
                    ->whereNotIn('status', Order::TERMINAL_STATUSES)
                    ->exists();

            if ($alreadyFinalizedPayment) {
                return response()->json($this->buildTableHistoryResponse($mySession, $request));
            }

            return response()->json([
                'message' => 'Your cart is empty. Add items before confirming your order.',
            ], 422);
        }

        if (! $order) {
            $myTotal = 0.0;
            foreach ($openItems as $item) {
                $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
                $shareCount = 1 + count($item->shared_order_ids ?? []);
                $myTotal += $lineTotal / $shareCount;
            }
            $myTotal = round($myTotal, 2);
            $currency = $mySession->vendor?->currency ?? 'EUR';

            $order = Order::create([
                'order_public_id' => 'ord-'.Str::random(12),
                'customer_id' => $customerId,
                'vendor_id' => $mySession->vendor_id,
                'table_scan_session_id' => $mySession->id,
                'status' => 'draft',
                'draft_at' => now(),
                'amount' => $myTotal,
                'currency' => $currency,
                'payment_pending' => false,
                'payment_received' => false,
                ...$noteAttributes,
                'order_type' => $this->orderSessions->isOffPremise($mySession)
                    ? $mySession->type
                    : 'dine-in',
            ]);
            $draftOrder = $order;
        }

        $unavailableItems = $openItems->filter(
            fn (CartItem $item) => ! $item->menuItem
                || $item->menuItem->trashed()
                || ! $item->menuItem->is_active
                || ! $item->menuItem->available
        );

        if ($unavailableItems->isNotEmpty()) {
            return response()->json([
                'message' => 'Some items in your cart are no longer available. Please remove them before confirming.',
                'unavailable_items' => $unavailableItems->map(fn (CartItem $item) => [
                    'cart_item_id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'name' => $item->menuItem?->name ?? 'Unknown item',
                ])->values(),
            ], 422);
        }

        $order = DB::transaction(function () use ($order, $draftOrder, $mySession, $vendorCountry, $serviceFeeRate, $openItems, $noteProvided, $noteAttributes) {
            $targetOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $draftToMerge = $draftOrder && (int) $draftOrder->id !== (int) $targetOrder->id
                ? Order::whereKey($draftOrder->id)->lockForUpdate()->first()
                : null;

            CartItem::whereIn('id', $openItems->pluck('id'))
                ->update([
                    'order_id' => $targetOrder->id,
                    'received_at' => now(),
                ]);

            if ($draftToMerge) {
                $this->moveSharedOrderReferences($draftToMerge->id, $targetOrder->id);
                $draftToMerge->delete();
            }

            $itemsTotal = $this->computeOrderAmount($targetOrder, $mySession->id, vendorCountry: $vendorCountry);
            $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);
            $total = round($itemsTotal + $serviceFee, 2);

            $updates = [
                'amount' => $total,
                'service_fee' => $serviceFee,
            ];

            if ($noteProvided) {
                $updates = [...$updates, ...$noteAttributes];
            } elseif ($draftToMerge?->note !== null && $targetOrder->note === null) {
                $updates['note'] = $draftToMerge->note;
            }

            if ($targetOrder->status === Order::STATUS_DRAFT) {
                $updates['status'] = Order::STATUS_CONFIRMED;
                $updates['confirmed_at'] = now();
            } elseif ($targetOrder->status === Order::STATUS_CONFIRMED && ! $targetOrder->confirmed_at) {
                $updates['confirmed_at'] = now();
            }

            $targetOrder->update($updates);

            return $targetOrder->fresh();
        });

        $history = $this->buildTableHistoryResponse($mySession, $request);

        $personSnapshot = collect($history['people'])
            ->first(fn (array $p) => $p['session_id'] === $mySession->id);

        $customerName = $this->customerName($request->user());

        $this->orderSessions->notifyCustomers(
            $mySession,
            'order_updated',
            "{$customerName} confirmed their order.",
            [
                'template' => 'order.confirmed',
                'customer_id' => $request->user()->id,
                'customer_name' => $customerName,
                'order_id' => $order->id,
                'order_snapshots' => [NotificationService::orderSnapshot($order->load('paidBy'), true)],
                'person_snapshot' => $personSnapshot,
                ...$this->realtimeCartMetadata($mySession, $request),
            ],
            false,
        );

        NotificationService::notifyOperations(
            $mySession->vendor_id,
            'order_confirmed',
            "{$customerName} confirmed their order.",
            [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => 'staff.order_confirmed',
                'table_id' => $mySession->restaurant_table_id,
                'order_id' => $order->id,
                'severity' => 'urgent',
                'sound' => 'new_order',
                'source_actor_type' => 'customer',
                'source_actor_id' => $request->user()->id,
                'order' => NotificationService::operationalOrderSnapshot($order),
            ],
        );

        return response()->json($history);
    }

    /**
     * GET /api/customer/table/history
     *
     * Returns the unified table view (same shape as /draft, /update, /confirmed).
     */
    public function tableHistory(Request $request): JsonResponse
    {
        $mySession = $this->activeSession($request);
        if (! $mySession) {
            return response()->json(['message' => 'No active table session found.'], 422);
        }

        return response()->json($this->buildTableHistoryResponse($mySession, $request));
    }

    /**
     * Compute the bill-split amount an order owes:
     *   - For each cart_item owned by the order's session: line_total / (1 + count(shared_order_ids))
     *   - For each cart_item where this order's id is in shared_order_ids: same per-share amount
     */
    private function computeOrderAmount(Order $order, int $ownerSessionId, bool $includeOpenOwnedItems = false, string $vendorCountry = 'AT'): float
    {
        $owned = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->where(function ($query) use ($order, $ownerSessionId, $includeOpenOwnedItems) {
                $query->where('order_id', $order->id);

                if ($includeOpenOwnedItems) {
                    $query->orWhere(function ($open) use ($ownerSessionId) {
                        $open->where('table_scan_session_id', $ownerSessionId)
                            ->whereNull('order_id');
                    });
                }
            })
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereJsonContains('shared_order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $ownerSessionId)
            ->get();

        $total = 0.0;

        foreach ($owned as $item) {
            $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $total += $lineTotal / $shareCount;
        }

        foreach ($sharedInto as $item) {
            $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);
            $total += $lineTotal / $shareCount;
        }

        return round($total, 2);
    }

    /**
     * Build the unified table-view response: per-session people, with each
     * person's orders enriched with computed items (owned + shared-into).
     */
    private function buildTableHistoryResponse(TableScanSession $mySession, ?Request $request = null): array
    {
        if (! $mySession->relationLoaded('vendor')) {
            $mySession->load('vendor.vendorSetting');
        }

        $vendorCountry = $this->vendorCountry($mySession);
        $serviceFeeRate = $this->serviceFeeRate($mySession);
        $vendor = $mySession->vendor;
        $locale = $request && $vendor
            ? $this->locales->resolveCustomerLocaleFromHeader($request, $vendor)
            : 'en';

        $sessionsQuery = TableScanSession::with([
            'customer:id,first_name,last_name',
            'restaurantTable:id,number,name',
            'vendor:id,vendor_public_id,restaurant_name,country',
        ])->whereIn('id', $this->tableSessionIds($mySession));

        $sessions = $sessionsQuery->get();

        $sessionIds = $sessions->pluck('id')->all();

        $orders = Order::with('paidBy:id,first_name,last_name')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        $allCartItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,image_url,vat_rate,tax_category,paid_addons,free_addons,removable_items,translations')
            ->whereIn('table_scan_session_id', $sessionIds)
            ->get();

        $ordersById = $orders->flatten()->keyBy('id');
        $latestDraftIds = $orders->map(fn (Collection $sessionOrders) => $sessionOrders
            ->filter(fn (Order $order) => $order->status === Order::STATUS_DRAFT
                && ! $order->payment_received
                && ! PaymentGuardService::orderIsCartLocked($order)
                && $order->parent_order_id === null)
            ->max('id'));

        $sessionCustomerNames = $sessions->mapWithKeys(fn (TableScanSession $s) => [
            $s->id => $s->customer
                ? trim($s->customer->first_name.' '.$s->customer->last_name)
                : 'Waiter',
        ]);

        $tableTotal = 0.0;
        $tableOrderCount = 0;

        $people = $sessions->map(function (TableScanSession $s) use ($mySession, $orders, $allCartItems, $ordersById, $latestDraftIds, $sessionCustomerNames, $vendorCountry, $serviceFeeRate, $vendor, $locale, &$tableTotal, &$tableOrderCount) {
            $personOrders = $orders->get($s->id, collect());

            $tableOrderCount += $personOrders->count();

            $personCartItems = collect();
            $liveTotals = [];

            $orderPayloads = $personOrders->map(function (Order $order) use ($s, $allCartItems, $mySession, $ordersById, $latestDraftIds, $sessionCustomerNames, $vendorCountry, $serviceFeeRate, $vendor, $locale, &$personCartItems, &$liveTotals) {
                $claimsUnboundItems = PaymentGuardService::orderClaimsUnboundItems(
                    $order,
                    $latestDraftIds->get($s->id),
                );
                $ownedCartItems = $allCartItems->filter(function (CartItem $ci) use ($s, $order, $claimsUnboundItems) {
                    // An open draft implicitly owns its session's unassigned
                    // items. A locked one does not: its items were bound to it
                    // when it locked, and anything added since belongs to the
                    // next draft.
                    if ($claimsUnboundItems) {
                        return (int) $ci->table_scan_session_id === (int) $s->id
                            && ($ci->order_id === null || (int) $ci->order_id === (int) $order->id);
                    }

                    return (int) $ci->order_id === (int) $order->id;
                });

                $sharedIntoItems = $allCartItems->filter(function (CartItem $ci) use ($order, $ownedCartItems) {
                    if ($ownedCartItems->contains('id', $ci->id)) {
                        return false;
                    }
                    $ids = is_array($ci->shared_order_ids) ? $ci->shared_order_ids : [];

                    return in_array($order->id, array_map('intval', $ids), true);
                });

                $orderItems = $ownedCartItems->merge($sharedIntoItems);
                $personCartItems = $personCartItems->merge($orderItems);

                $itemRows = $orderItems
                    ->map(fn (CartItem $ci) => $this->cartItemPayload($ci, $order, $mySession, $ordersById, $sessionCustomerNames, $vendorCountry, $vendor, $locale))
                    ->values()
                    ->all();

                $liveTotals[(int) $order->id] = $this->liveOrderTotals(
                    $order,
                    $orderItems,
                    $vendorCountry,
                    $serviceFeeRate,
                    $claimsUnboundItems,
                );

                return $this->orderPayload($order, $itemRows, $vendor, $liveTotals[(int) $order->id]);
            })->values();

            $personTotal = round(array_sum(array_column($liveTotals, 'amount')), 2);
            $tableTotal += $personTotal;

            $personTaxGroups = TaxCalculationService::computeTaxGroups($personCartItems, $vendorCountry, true);
            $personTotals = TaxCalculationService::computeTotals($personTaxGroups, 0);

            $personServiceFee = round(array_sum(array_column($liveTotals, 'service_fee')), 2);
            $personTotals['service_fee'] = $personServiceFee;
            $personTotals['grand_total'] = round($personTotals['grand_total'] + $personServiceFee, 2);

            $totalTips = round((float) $personOrders->sum(fn (Order $o) => (float) ($o->tip_amount ?? 0)), 2);
            $personTotals['total_tips'] = $totalTips;
            $personTotals['grand_total'] = round($personTotals['grand_total'] + $totalTips, 2);

            return [
                'session_id' => $s->id,
                'customer_id' => $s->customer_id,
                'is_me' => $s->id === $mySession->id,
                'name' => $s->customer
                    ? trim($s->customer->first_name.' '.$s->customer->last_name)
                    : 'Waiter',
                'scanned_at' => $this->dateTimes->formatDateTime($s->scanned_at, $vendor),
                'status' => $s->status,
                'orders_count' => $personOrders->count(),
                'total_amount' => round($personTotal, 2),
                'orders' => $orderPayloads,
                'tax_groups' => array_map(fn (array $g) => array_merge($g, [
                    'label' => $this->locales->translatedTaxCategoryName($g['tax_category'], $vendorCountry, $locale),
                ]), $personTaxGroups),
                'totals' => $personTotals,
            ];
        })->values();

        $table = $mySession->restaurantTable;

        return [
            'table' => $table ? [
                'id' => $table->id,
                'number' => $table->number ?? null,
                'name' => $table->name ?? null,
            ] : null,
            'vendor' => $vendor ? [
                'vendor_public_id' => $vendor->vendor_public_id ?? null,
                'restaurant_name' => $vendor->restaurant_name ?? null,
            ] : null,
            'session' => [
                'id' => $mySession->id,
                'status' => $mySession->status,
                'type' => $mySession->type,
                'pin' => $mySession->pin !== '' ? $mySession->pin : null,
                'scanned_at' => $this->dateTimes->formatDateTime($mySession->scanned_at, $vendor),
                'scheduled_for' => $this->dateTimes->formatDateTime($mySession->scheduled_for, $vendor),
            ],
            'people' => $people,
            'summary' => [
                'orders_count' => $tableOrderCount,
                'total_amount' => round($tableTotal, 2),
            ],
        ];
    }

    /**
     * Build the per-item row used inside an order's `items` array.
     */
    private function cartItemPayload(
        CartItem $ci,
        Order $order,
        TableScanSession $mySession,
        $ordersById = null,
        $sessionCustomerNames = null,
        ?string $vendorCountry = null,
        ?Vendor $vendor = null,
        string $locale = 'en',
    ): array {
        $vendorCountry ??= 'AT';
        $menuItem = $ci->menuItem;
        $itemTaxCategory = $menuItem?->tax_category ?? 'food';
        $unitPrice = $this->cartItemUnitPrice($ci, $vendorCountry);
        $lineTotal = $this->cartItemLineTotal($ci, $vendorCountry);
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineTotal, $vatRate);
        $orderIds = array_values(array_map('intval', is_array($ci->shared_order_ids) ? $ci->shared_order_ids : []));
        $sharedBetween = 1 + count($orderIds);
        $myShare = round($lineTotal / $sharedBetween, 2);

        $sharedWith = array_values(array_filter(array_map(function (int $oid) use ($ordersById, $sessionCustomerNames) {
            if ($ordersById === null) {
                return ['order_id' => $oid, 'customer_id' => null, 'customer_name' => null];
            }
            $o = $ordersById->get($oid);
            if (! $o) {
                return null;
            }

            return [
                'order_id' => $o->id,
                'customer_id' => $o->customer_id,
                'customer_name' => $sessionCustomerNames?->get($o->table_scan_session_id, 'Guest') ?? 'Guest',
            ];
        }, $orderIds)));

        return [
            'cart_item_id' => $ci->id,
            'menu_item_id' => $ci->menu_item_id,
            'name' => $menuItem && $vendor
                ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                : $menuItem?->name,
            'image_url' => $menuItem?->image_url,
            'quantity' => $ci->quantity,
            'unit_price' => $unitPrice,
            'paid_addons' => $this->formatCartPaidAddons($ci, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatCartNamedSelections($ci, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatCartNamedSelections($ci, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatCartSelectedModifiers($ci, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'tax_category' => $itemTaxCategory,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'is_mine' => (int) $ci->table_scan_session_id === (int) $mySession->id,
            'shared_between' => $sharedBetween,
            'shared_with' => $sharedWith,
            'my_share' => $myShare,
            'status' => $ci->status(),
            'received_at' => $this->dateTimes->formatDateTime($ci->received_at, $vendor),
            'preparing_start_at' => $this->dateTimes->formatDateTime($ci->preparing_start_at, $vendor),
            'ready_at' => $this->dateTimes->formatDateTime($ci->ready_at, $vendor),
            'served_at' => $this->dateTimes->formatDateTime($ci->served_at, $vendor),
        ];
    }

    /**
     * What an order is worth right now, and the service fee inside it.
     *
     * A submitted or settled order keeps its stored amount: that is the figure
     * the customer was quoted and charged. An open draft does not — its items
     * are still being edited and shared, and its stored amount is only as
     * fresh as the last write that remembered to re-price it. Pricing it from
     * the very items this payload resolved for it means the number can never
     * contradict the list printed beside it.
     *
     * @param  Collection<int, CartItem>  $items
     * @return array{amount: float, service_fee: float}
     */
    private function liveOrderTotals(
        Order $order,
        Collection $items,
        string $vendorCountry,
        float $serviceFeeRate,
        bool $claimsUnboundItems,
    ): array {
        if (! $claimsUnboundItems) {
            return [
                'amount' => round((float) $order->amount, 2),
                'service_fee' => round((float) ($order->service_fee ?? 0), 2),
            ];
        }

        $itemsTotal = round($items->sum(function (CartItem $item) use ($vendorCountry) {
            $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
            $shareCount = 1 + count($item->shared_order_ids ?? []);

            return $lineTotal / $shareCount;
        }), 2);

        $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);

        return [
            'amount' => round($itemsTotal + $serviceFee, 2),
            'service_fee' => $serviceFee,
        ];
    }

    /**
     * Build the per-order dict (without its `items` array — that is computed by the caller).
     *
     * @param  array{amount: float, service_fee: float}|null  $liveTotals
     */
    private function orderPayload(Order $o, array $items, ?Vendor $vendor = null, ?array $liveTotals = null): array
    {
        return [
            'id' => $o->id,
            'order_public_id' => $o->order_public_id,
            'parent_order_id' => $o->parent_order_id,
            'customer_id' => $o->customer_id,
            'paid_by' => $o->paidBy ? [
                'id' => $o->paidBy->id,
                'name' => trim(($o->paidBy->first_name ?? '').' '.($o->paidBy->last_name ?? '')) ?: 'Guest',
            ] : null,
            'vendor_id' => $o->vendor_id,
            'table_scan_session_id' => $o->table_scan_session_id,
            'status' => $o->status,
            'amount' => (float) ($liveTotals['amount'] ?? $o->amount),
            'tip_amount' => (float) ($o->tip_amount ?? 0),
            'currency' => $o->currency,
            'order_number' => $o->order_number,
            'order_type' => $o->order_type,
            'table_number' => $o->table_number,
            'service_fee' => (float) ($liveTotals['service_fee'] ?? $o->service_fee ?? 0),
            'vat_amount' => (float) ($o->vat_amount ?? 0),
            'course' => $o->course,
            'payment_method' => $o->payment_method,
            'payment_pending' => (bool) $o->payment_pending,
            // Who is standing on the payment step with this order in their
            // total, so the rest of the table can see it is spoken for.
            'checkout_hold_by' => $o->checkout_hold_by ? (int) $o->checkout_hold_by : null,
            'payment_received' => (bool) $o->payment_received,
            'payment_confirmed_at' => $this->dateTimes->formatDateTime($o->payment_confirmed_at, $vendor),
            'payment_note' => $o->payment_note,
            'note' => $o->note,
            'transaction_id' => $o->transaction_id,
            'served_at' => $this->dateTimes->formatDateTime($o->served_at, $vendor),
            'cancelled_at' => $this->dateTimes->formatDateTime($o->cancelled_at, $vendor),
            'cancelled_reason' => $o->cancelled_reason,
            'waiter_confirmed' => (bool) $o->waiter_confirmed,
            'waiter_confirmed_at' => $this->dateTimes->formatDateTime($o->waiter_confirmed_at, $vendor),
            'created_at' => $this->dateTimes->formatDateTime($o->created_at, $vendor),
            'updated_at' => $this->dateTimes->formatDateTime($o->updated_at, $vendor),
            'items' => $items,
        ];
    }

    /**
     * The order a guest's next cart change belongs to.
     *
     * Side orders are excluded. One is created as `confirmed` under the same
     * customer and session to hold the personal shares of a guest somebody else
     * is covering, and it always outranks the main order on `latest('id')` — so
     * without this filter a covered guest's new items would be priced into, and
     * their draft amount written over, the order holding their share of a
     * mate's item. Every equivalent lookup in PaymentGuardService excludes them.
     */
    private function currentOpenOrder(int $customerId, int $sessionId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $sessionId)
            ->whereIn('status', ['draft', 'confirmed'])
            ->where('payment_received', false)
            ->where('payment_pending', false)
            ->whereNull('parent_order_id')
            ->latest('id')
            ->first();
    }

    /**
     * The locked order a cart item belongs to, if any — either because the item
     * sits in that order, or because it has been shared into it. Editing such
     * an item would change a total somebody is already paying.
     */
    private function lockedOrderForCartItem(CartItem $item): ?Order
    {
        $orderIds = collect([$item->order_id])
            ->merge(is_array($item->shared_order_ids) ? $item->shared_order_ids : [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($orderIds->isEmpty()) {
            return null;
        }

        return Order::whereIn('id', $orderIds)
            ->where(fn ($query) => $query
                ->where('payment_received', true)
                ->orWhere('payment_pending', true))
            ->first();
    }

    private function currentDraftOrder(int $customerId, int $sessionId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $sessionId)
            ->where('status', Order::STATUS_DRAFT)
            ->where('payment_received', false)
            ->where('payment_pending', false)
            ->whereNull('parent_order_id')
            ->latest('id')
            ->first();
    }

    private function currentUnpaidSubmittedOrder(int $customerId, int $sessionId): ?Order
    {
        return Order::where('customer_id', $customerId)
            ->where('table_scan_session_id', $sessionId)
            ->where('payment_received', false)
            ->where('payment_pending', false)
            ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
            ->whereNull('parent_order_id')
            ->latest('id')
            ->first();
    }

    private function moveSharedOrderReferences(int $fromOrderId, int $toOrderId): void
    {
        CartItem::whereJsonContains('shared_order_ids', $fromOrderId)
            ->get()
            ->each(function (CartItem $item) use ($fromOrderId, $toOrderId) {
                $ids = array_map('intval', is_array($item->shared_order_ids) ? $item->shared_order_ids : []);
                $ids = array_values(array_unique(array_merge(
                    array_filter($ids, fn (int $id) => $id !== $fromOrderId),
                    [$toOrderId]
                )));

                $item->update(['shared_order_ids' => $ids]);
            });
    }

    /**
     * Every order whose amount depends on $item: the one that owns it, plus
     * every order it has been shared into.
     *
     * A freshly added row is not bound to an order yet — it belongs to whichever
     * draft is open for its session — so the owning order is resolved through
     * PaymentGuardService rather than read off order_id, which would silently
     * drop the owner's own order out of the set that has to be re-priced.
     *
     * @return Collection<int, int>
     */
    private function cartItemOrderIds(CartItem $item): Collection
    {
        return collect([PaymentGuardService::owningOrderIdFor($item)])
            ->merge(is_array($item->shared_order_ids) ? $item->shared_order_ids : [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Re-price every order $item takes part in.
     *
     * Step-1 coverage and item sharing stay editable, so the guest who offered
     * to pay is watching a total that has to follow the owner's cart. Without
     * this their amount only caught up when the owner re-submitted the order.
     *
     * @return array{order_ids: Collection<int, int>, removed_order_ids: Collection<int, int>}
     */
    private function recalculateCartItemOrders(CartItem $item, TableScanSession $session): array
    {
        return $this->recalculateOrderIds($this->cartItemOrderIds($item), $session);
    }

    /**
     * Re-price $orderIds and report what changed, so the caller can broadcast it.
     *
     * Locked orders never reach here: a paid one is skipped by the recalculation
     * service, and a frozen one is unreachable because the cart guards reject
     * every edit to its items before this runs.
     *
     * $pruneEmptySideOrders drops a personal side order whose last shared item
     * has just been deleted, mirroring the unshare path in OrderSharingService —
     * without it an empty order card lingers on the payment step.
     *
     * @return array{order_ids: Collection<int, int>, removed_order_ids: Collection<int, int>}
     */
    private function recalculateOrderIds(
        iterable $orderIds,
        TableScanSession $session,
        bool $pruneEmptySideOrders = false,
    ): array {
        $ids = collect($orderIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return ['order_ids' => collect(), 'removed_order_ids' => collect()];
        }

        // Pricing needs the vendor's country and service fee. The cart routes
        // reach here before they touch $session->vendor, and an unloaded
        // relation would silently price the whole table as Austrian.
        if (! $session->relationLoaded('vendor')) {
            $session->load('vendor.vendorSetting');
        }

        $state = $this->orderAmounts->recalculate(
            $ids,
            $this->vendorCountry($session),
            $this->serviceFeeRate($session),
        );

        $removedOrderIds = collect();

        if ($pruneEmptySideOrders) {
            foreach ($state['orders'] as $order) {
                if ($order->parent_order_id && ShareOrderService::deleteIfEmpty($order)) {
                    $removedOrderIds->push((int) $order->id);
                }
            }
        }

        return [
            'order_ids' => $state['orders']
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->diff($removedOrderIds)
                ->values(),
            'removed_order_ids' => $removedOrderIds->unique()->values(),
        ];
    }

    private function normalizeCustomizations(MenuItem $menuItem, array $data, ?CartItem $existing = null): array
    {
        return [
            'paid_addons' => array_key_exists('paid_addons', $data)
                ? $this->normalizePaidAddons($menuItem, $data['paid_addons'] ?? [])
                : ($existing?->paid_addons ?? []),
            'free_addons' => array_key_exists('free_addons', $data)
                ? $this->normalizeStringSelections('free_addons', $menuItem->free_addons ?? [], $data['free_addons'] ?? [])
                : ($existing?->free_addons ?? []),
            'removed_items' => (array_key_exists('removed_items', $data) || array_key_exists('removable_items', $data))
                ? $this->normalizeStringSelections(
                    'removed_items',
                    $menuItem->removable_items ?? [],
                    $data['removed_items'] ?? $data['removable_items'] ?? []
                )
                : ($existing?->removed_items ?? []),
            'selected_modifiers' => $this->normalizeSelectedModifiers(
                $menuItem,
                $data['selected_modifiers'] ?? $data['modifiers'] ?? $existing?->selected_modifiers ?? []
            ),
        ];
    }

    private function normalizePaidAddons(MenuItem $menuItem, array $selected): array
    {
        return $this->customizations->normalizePaidAddons($menuItem, $selected);
    }

    private function normalizeStringSelections(string $field, array $configured, array $selected): array
    {
        return $this->customizations->normalizeNamedSelections($field, $configured, $selected);
    }

    private function normalizeSelectedModifiers(MenuItem $menuItem, array $selected): array
    {
        if (! $menuItem->relationLoaded('modifierGroups')) {
            $menuItem->load(['modifierGroups' => fn ($q) => $q->where('is_active', true)
                ->orderByPivot('sort_order')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])]);
        }

        $groups = $menuItem->modifierGroups->keyBy('id');
        $submitted = collect($selected)
            ->filter(fn ($entry) => is_array($entry))
            ->mapWithKeys(function (array $entry) {
                $groupId = (int) ($entry['modifier_group_id'] ?? $entry['group_id'] ?? $entry['id'] ?? 0);
                $optionIds = $entry['option_ids'] ?? $entry['options'] ?? [];

                if (! is_array($optionIds)) {
                    $optionIds = [$optionIds];
                }

                return $groupId > 0 ? [$groupId => $optionIds] : [];
            });

        $normalized = [];

        foreach ($groups as $groupId => $group) {
            $rawOptionIds = collect($submitted->get($groupId, []))
                ->map(fn ($id) => is_array($id) ? ($id['id'] ?? $id['option_id'] ?? null) : $id)
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values();

            $count = $rawOptionIds->count();
            $minRequired = max((int) $group->min_selection, $group->is_required ? 1 : 0);
            $maxSelection = max(1, (int) $group->max_selection);

            if ($count < $minRequired) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["Please choose at least {$minRequired} option(s) for {$group->name}."],
                ]);
            }

            if ($count === 0) {
                continue;
            }

            if ($group->type === 'single' && $count > 1) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["Only one option can be selected for {$group->name}."],
                ]);
            }

            if ($count > $maxSelection) {
                throw ValidationException::withMessages([
                    'selected_modifiers' => ["You can choose at most {$maxSelection} option(s) for {$group->name}."],
                ]);
            }

            $optionsById = $group->options->keyBy('id');
            $options = $rawOptionIds->map(function (int $optionId) use ($optionsById, $group) {
                $option = $optionsById->get($optionId);

                if (! $option) {
                    throw ValidationException::withMessages([
                        'selected_modifiers' => ["The selected option is not available for {$group->name}."],
                    ]);
                }

                return [
                    'id' => $option->id,
                    'price_adjustment' => round((float) $option->price_adjustment, 2),
                ];
            })->values()->all();

            $normalized[] = [
                'modifier_group_id' => $group->id,
                'type' => $group->type,
                'is_required' => (bool) $group->is_required,
                'min_selection' => (int) $group->min_selection,
                'max_selection' => (int) $group->max_selection,
                'tax_category' => $group->tax_category,
                'options' => $options,
            ];
        }

        $unknownGroupIds = $submitted->keys()
            ->map(fn ($id) => (int) $id)
            ->diff($groups->keys()->map(fn ($id) => (int) $id));

        if ($unknownGroupIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'selected_modifiers' => ['One or more selected modifier groups are not available for this menu item.'],
            ]);
        }

        return $normalized;
    }

    private function cartCustomizationsMatch(CartItem $item, array $customizations): bool
    {
        return json_encode($item->paid_addons ?? []) === json_encode($customizations['paid_addons'])
            && json_encode($item->free_addons ?? []) === json_encode($customizations['free_addons'])
            && json_encode($item->removed_items ?? []) === json_encode($customizations['removed_items'])
            && $this->modifiersMatchForDedup($item->selected_modifiers ?? [], $customizations['selected_modifiers']);
    }

    private function modifiersMatchForDedup(array $existing, array $incoming): bool
    {
        if (count($existing) !== count($incoming)) {
            return false;
        }

        foreach ($existing as $i => $group) {
            if (! isset($incoming[$i])) {
                return false;
            }
            $inGroup = $incoming[$i];

            if (($group['modifier_group_id'] ?? 0) !== ($inGroup['modifier_group_id'] ?? 0)) {
                return false;
            }

            $existingOptionIds = array_column($group['options'] ?? [], 'id');
            $incomingOptionIds = array_column($inGroup['options'] ?? [], 'id');

            sort($existingOptionIds);
            sort($incomingOptionIds);

            if ($existingOptionIds !== $incomingOptionIds) {
                return false;
            }
        }

        return true;
    }

    private function vendorCountry(TableScanSession $session): string
    {
        $vendor = $session->relationLoaded('vendor') ? $session->vendor : null;

        return $vendor?->country ?? 'AT';
    }

    private function serviceFeeRate(TableScanSession $session): float
    {
        $vendor = $session->relationLoaded('vendor') ? $session->vendor : null;
        $settings = $vendor?->relationLoaded('vendorSetting') ? $vendor->vendorSetting : $vendor?->vendorSetting;

        return (float) ($settings?->service_fee_rate ?? 0);
    }

    private function cartItemUnitPrice(CartItem $item, string $vendorCountry = 'AT'): float
    {
        return TaxCalculationService::cartItemUnitPriceGross($item, $vendorCountry);
    }

    private function cartItemLineTotal(CartItem $item, string $vendorCountry = 'AT'): float
    {
        return TaxCalculationService::cartItemLineTotalGross($item, $vendorCountry);
    }

    private function itemPayload(CartItem $item, ?string $vendorCountry = null, ?Vendor $vendor = null, string $locale = 'en'): array
    {
        $vendorCountry ??= 'AT';
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $vatRate = TaxCalculationService::itemVatRate($menuItem, $vendorCountry);
        $baseGross = TaxCalculationService::itemBaseGross($menuItem, $vendorCountry);
        $unitPrice = $this->cartItemUnitPrice($item, $vendorCountry);
        $lineTotal = $this->cartItemLineTotal($item, $vendorCountry);
        $vatAmount = TaxCalculationService::vatFromGross($lineTotal, $vatRate);
        $itemTaxCategory = $menuItem?->tax_category ?? 'food';
        // What this customer actually owes on the line. The cart's `totals`
        // already divide a split line by its sharers, so the line has to say
        // the same thing or the rows will not add up to the total shown.
        $sharedBetween = 1 + count(is_array($item->shared_order_ids) ? $item->shared_order_ids : []);

        return [
            'id' => $item->id,
            'client_item_id' => $item->client_item_id,
            'quantity' => $item->quantity,
            'notes' => $item->notes,
            'price' => $unitPrice,
            'paid_addons' => $this->formatCartPaidAddons($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'free_addons' => $this->formatCartNamedSelections($item, 'free_addons', $vendor, $locale),
            'removed_items' => $this->formatCartNamedSelections($item, 'removed_items', $vendor, $locale),
            'selected_modifiers' => $this->formatCartSelectedModifiers($item, $itemTaxCategory, $vendorCountry, $vendor, $locale),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'line_total' => $lineTotal,
            'shared_between' => $sharedBetween,
            'my_share' => round($lineTotal / $sharedBetween, 2),
            'menu_item' => $menuItem ? [
                'id' => $menuItem->id,
                'name' => $vendor
                    ? $this->customizations->menuItemName($menuItem, $vendor, $locale)
                    : $menuItem->name,
                'price' => $baseGross,
                'vat_rate' => $vatRate,
                'tax_category' => $menuItem->tax_category,
                'image_url' => $menuItem->image_url,
            ] : null,
        ];
    }

    private function formatCartPaidAddons(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;

        if (! $menuItem || ! $vendor) {
            return collect($item->paid_addons ?? [])->map(function ($addon) use ($itemTaxCategory, $vendorCountry) {
                $vatRate = TaxCalculationService::addonVatRate(is_array($addon) ? $addon : [], $itemTaxCategory, $vendorCountry);

                return [
                    'id' => is_array($addon) ? ($addon['id'] ?? null) : null,
                    'name' => is_array($addon) ? ($addon['name'] ?? '') : '',
                    'price' => TaxCalculationService::gross((float) (is_array($addon) ? ($addon['price'] ?? 0) : 0), $vatRate),
                    'vat_rate' => $vatRate,
                ];
            })->values()->all();
        }

        return $this->customizations->formatPaidAddons($menuItem, $item->paid_addons ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
    }

    private function formatCartNamedSelections(CartItem $item, string $field, ?Vendor $vendor, string $locale): array
    {
        $menuItem = $item->relationLoaded('menuItem') ? $item->menuItem : null;
        $selected = $field === 'free_addons'
            ? ($item->free_addons ?? [])
            : ($item->removed_items ?? []);

        if (! $menuItem || ! $vendor) {
            return collect($selected)->map(fn ($value) => is_array($value) ? (string) ($value['name'] ?? '') : (string) $value)
                ->filter()
                ->values()
                ->all();
        }

        $configured = $field === 'free_addons'
            ? ($menuItem->free_addons ?? [])
            : ($menuItem->removable_items ?? []);

        return $this->customizations->formatNamedSelections($configured, $selected, $vendor, $locale);
    }

    private function formatCartSelectedModifiers(CartItem $item, string $itemTaxCategory, string $vendorCountry, ?Vendor $vendor, string $locale): array
    {
        if ($vendor) {
            return $this->customizations->formatSelectedModifiers($item->selected_modifiers ?? [], $vendor, $locale, $itemTaxCategory, $vendorCountry);
        }

        return collect($item->selected_modifiers ?? [])->map(function ($group) use ($itemTaxCategory, $vendorCountry) {
            $groupTaxCategory = $group['tax_category'] ?? '';
            $vatRate = TaxCalculationService::modifierGroupVatRate($groupTaxCategory, $itemTaxCategory, $vendorCountry);

            $options = collect($group['options'] ?? [])->map(function ($option) use ($vatRate) {
                return [
                    'id' => $option['id'] ?? null,
                    'name' => $option['name'] ?? null,
                    'price_adjustment' => TaxCalculationService::gross((float) ($option['price_adjustment'] ?? 0), $vatRate),
                ];
            })->values()->all();

            return array_merge($group, [
                'vat_rate' => $vatRate,
                'options' => $options,
            ]);
        })->values()->all();
    }
}
