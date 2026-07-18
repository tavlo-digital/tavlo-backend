<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Api\Vendor\Concerns\QueuesStaffCommands;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\MenuCustomizationService;
use App\Services\NotificationService;
use App\Services\StaffCommandBus;
use App\Services\TableStatePatchService;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffOrderController extends Controller
{
    use QueuesStaffCommands;

    public function __construct(
        private readonly MenuCustomizationService $customizations,
        private readonly StaffCommandBus $staffCommands,
        private readonly TableStatePatchService $tableStatePatches,
    ) {}

    public function store(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['sometimes', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.paid_addons' => ['sometimes', 'array'],
            'items.*.paid_addons.*.id' => ['sometimes', 'integer', 'min:1'],
            'items.*.paid_addons.*.name' => ['sometimes', 'string', 'max:255'],
            'items.*.paid_addons.*.price' => ['sometimes', 'numeric', 'min:0'],
            'items.*.free_addons' => ['sometimes', 'array'],
            'items.*.removed_items' => ['sometimes', 'array'],
            'items.*.selected_modifiers' => ['sometimes', 'array'],
        ]);

        if ($queued = $this->queuedStaffCommand(
            $request,
            $this->staffCommands,
            'table.staff_order',
            [
                'vendor_id' => $vendorId,
                'table_id' => $tableId,
                ...$data,
            ],
            [$this->staffCommandTableResource($request, $tableId)],
        )) {
            return $queued;
        }

        $vendor = Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
        $this->authorizeVendor($request, $vendor);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

        $menuItemIds = collect($data['items'])->pluck('menu_item_id')->unique();
        $menuItems = MenuItem::where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('available', true)
            ->whereIn('id', $menuItemIds)
            ->with(['modifierGroups' => fn ($q) => $q->where('is_active', true)
                ->orderByPivot('sort_order')
                ->with(['options' => fn ($o) => $o->where('is_active', true)->orderBy('sort_order')])])
            ->get()
            ->keyBy('id');

        $missing = $menuItemIds->diff($menuItems->keys());
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Some menu items are not available: '.$missing->implode(', ')],
            ]);
        }

        $actor = $request->user();

        $result = DB::transaction(function () use ($vendor, $table, $data, $menuItems, $actor) {
            // The waiter is their own "person" at the table: a dedicated session
            // row with customer_id = null, never a customer's session. Sibling
            // sessions share the table's PIN, like customers joining via PIN.
            $session = TableScanSession::where('vendor_id', $vendor->id)
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'active')
                ->whereNull('customer_id')
                ->first();

            if (! $session) {
                $tablePin = TableScanSession::where('vendor_id', $vendor->id)
                    ->where('restaurant_table_id', $table->id)
                    ->where('status', 'active')
                    ->value('pin');

                $session = TableScanSession::create([
                    'vendor_id' => $vendor->id,
                    'restaurant_table_id' => $table->id,
                    'customer_id' => null,
                    'pin' => $tablePin ?? TableScanSession::generateUniquePin(),
                    'status' => 'active',
                    'scanned_at' => now(),
                ]);
            }

            $vendorCountry = $vendor->country ?? 'AT';
            $currency = $vendor->currency ?? 'EUR';

            // Mirror of the customer flow's currentUnpaidSubmittedOrder(): keep
            // appending to the open waiter order until it is paid, then start fresh.
            $order = Order::where('table_scan_session_id', $session->id)
                ->whereNull('customer_id')
                ->where('payment_received', false)
                ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            $orderCreated = ! $order;

            if ($orderCreated) {
                $order = Order::create([
                    'order_public_id' => 'ord-'.Str::random(12),
                    'customer_id' => null,
                    'vendor_id' => $vendor->id,
                    'table_scan_session_id' => $session->id,
                    'status' => 'confirmed',
                    'confirmed_at' => now(),
                    'amount' => 0,
                    'currency' => $currency,
                    // No predefined payment method — like customer orders, so a
                    // guest can still pay it (pay-for/Stripe) or the waiter collects.
                    'payment_method' => null,
                    'payment_pending' => false,
                    'payment_received' => false,
                    'order_type' => 'dine-in',
                    'placed_by' => 'waiter',
                    'placed_by_team_member_id' => $actor instanceof TeamMember ? $actor->id : null,
                ]);
            }

            $itemCount = 0;
            foreach ($data['items'] as $itemData) {
                $menuItem = $menuItems->get($itemData['menu_item_id']);
                $customizations = $this->normalizeCustomizations($menuItem, $itemData);

                CartItem::create([
                    'table_scan_session_id' => $session->id,
                    'menu_item_id' => $itemData['menu_item_id'],
                    'order_id' => $order->id,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'notes' => $itemData['notes'] ?? null,
                    'paid_addons' => $customizations['paid_addons'],
                    'free_addons' => $customizations['free_addons'],
                    'removed_items' => $customizations['removed_items'],
                    'selected_modifiers' => $customizations['selected_modifiers'],
                    'received_at' => now(),
                ]);
                $itemCount++;
            }

            // Reprice over every item on the order (existing + new) with the same
            // formula as the customer order confirm (CartController::createOrderConfirmed).
            $orderItems = CartItem::with('menuItem:id,name,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons')
                ->where('order_id', $order->id)
                ->get();

            $itemsTotal = 0.0;
            foreach ($orderItems as $ci) {
                $itemsTotal += TaxCalculationService::cartItemLineTotalGross($ci, $vendorCountry);
            }
            $itemsTotal = round($itemsTotal, 2);

            $serviceFeeRate = (float) ($vendor->vendorSetting?->service_fee_rate ?? 0);
            $serviceFee = round($itemsTotal * ($serviceFeeRate / 100), 2);

            $order->update([
                'amount' => round($itemsTotal + $serviceFee, 2),
                'service_fee' => $serviceFee,
                'payment_pending' => false,
            ]);

            return [
                'order' => $order,
                'session' => $session,
                'itemCount' => $itemCount,
                'orderCreated' => $orderCreated,
            ];
        });

        $tableLabel = $table->name ?? "#{$table->number}";
        $operation = $result['orderCreated'] ? 'order.staff_created' : 'order.staff_updated';
        $statePatch = $this->tableStatePatches->build($operation, [$result['order']->id]);
        $participant = [
            'session_id' => (int) $result['session']->id,
            'customer_id' => null,
            'name' => 'Waiter',
            'scanned_at' => $result['session']->scanned_at?->toISOString(),
            'status' => (string) $result['session']->status,
        ];

        NotificationService::notifyOperations(
            $vendor->id,
            'new_order',
            "Staff order placed for Table {$tableLabel} ({$result['itemCount']} items).",
            [NotificationService::KITCHEN, NotificationService::WAITER],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => 'staff.order_confirmed',
                'order_id' => $result['order']->id,
                'table_id' => $table->id,
                'table_label' => $tableLabel,
                'severity' => 'info',
                'sound' => 'new_order',
                'source_actor_type' => $actor instanceof TeamMember ? 'team_member' : 'vendor',
                'source_actor_id' => $actor?->id,
                'order' => NotificationService::operationalOrderSnapshot($result['order']),
            ],
        );

        // A waiter order belongs to a dedicated customer-less table participant.
        // Push that participant together with an authoritative state patch so
        // customers can insert or update the order locally without a GET/refetch.
        NotificationService::notifyTableCustomers(
            $table->id,
            'order_updated',
            $result['orderCreated']
                ? "A waiter added an order to Table {$tableLabel}."
                : "A waiter updated an order at Table {$tableLabel}.",
            [
                'resources' => ['orders', 'table_history'],
                'template' => $operation,
                'order_id' => (int) $result['order']->id,
                'table_id' => (int) $table->id,
                'customer_id' => null,
                'participant' => $participant,
                'state_patch' => $statePatch,
            ],
            false,
        );

        $orderSnapshot = NotificationService::operationalOrderSnapshot($result['order']);

        return response()->json([
            'message' => 'Order placed successfully.',
            'order_id' => $result['order']->order_public_id,
            'amount' => $result['order']->amount,
            'session_id' => $result['session']->id,
            'order' => $orderSnapshot,
            'participant' => $participant,
            'state_patch' => $statePatch,
        ], 201);
    }

    private function staffCommandTableResource(Request $request, string $tableId): string
    {
        $actor = $request->user();
        $vendorId = $actor instanceof TeamMember ? $actor->vendor_id : $actor->id;

        return "vendor:{$vendorId}:table:{$tableId}";
    }

    private function normalizeCustomizations(MenuItem $menuItem, array $data): array
    {
        return [
            'paid_addons' => array_key_exists('paid_addons', $data)
                ? $this->customizations->normalizePaidAddons($menuItem, $data['paid_addons'] ?? [])
                : [],
            'free_addons' => array_key_exists('free_addons', $data)
                ? $this->customizations->normalizeNamedSelections('free_addons', $menuItem->free_addons ?? [], $data['free_addons'] ?? [])
                : [],
            'removed_items' => array_key_exists('removed_items', $data)
                ? $this->customizations->normalizeNamedSelections('removed_items', $menuItem->removable_items ?? [], $data['removed_items'] ?? [])
                : [],
            'selected_modifiers' => $this->normalizeSelectedModifiers(
                $menuItem,
                $data['selected_modifiers'] ?? []
            ),
        ];
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();

        if ($user instanceof Vendor && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }

        if ($user instanceof TeamMember && $user->vendor_id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Mirrors the customer CartController normalizer: same strict validation
     * (required / min / max / unknown groups and options) and the same stored
     * shape, so TaxCalculationService prices staff modifiers identically.
     */
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
}
