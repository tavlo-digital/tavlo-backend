<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\MenuCustomizationService;
use App\Services\NotificationService;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffOrderController extends Controller
{
    public function __construct(
        private readonly MenuCustomizationService $customizations,
    ) {}

    public function store(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
        $this->authorizeVendor($request, $vendor);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

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
                'items' => ['Some menu items are not available: ' . $missing->implode(', ')],
            ]);
        }

        $actor = $request->user();

        $result = DB::transaction(function () use ($vendor, $table, $data, $menuItems, $actor) {
            $session = TableScanSession::where('vendor_id', $vendor->id)
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'active')
                ->first();

            if (! $session) {
                $session = TableScanSession::create([
                    'vendor_id' => $vendor->id,
                    'restaurant_table_id' => $table->id,
                    'customer_id' => null,
                    'pin' => TableScanSession::generateUniquePin(),
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

            if (! $order) {
                $order = Order::create([
                    'order_public_id' => 'ord-' . Str::random(12),
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
                    'payment_pending' => true,
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
                'payment_pending' => true,
            ]);

            return ['order' => $order, 'session' => $session, 'itemCount' => $itemCount];
        });

        $tableLabel = $table->name ?? "#{$table->number}";

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
            ],
        );

        return response()->json([
            'message' => 'Order placed successfully.',
            'order_id' => $result['order']->order_public_id,
            'amount' => $result['order']->amount,
            'session_id' => $result['session']->id,
        ], 201);
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
