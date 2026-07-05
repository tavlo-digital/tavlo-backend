<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
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
        $vendor = Vendor::where('vendor_public_id', $vendorId)->firstOrFail();
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

        $result = DB::transaction(function () use ($vendor, $table, $data, $menuItems) {
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

            $cartItems = [];
            foreach ($data['items'] as $itemData) {
                $menuItem = $menuItems->get($itemData['menu_item_id']);
                $customizations = $this->normalizeCustomizations($menuItem, $itemData);

                $cartItems[] = CartItem::create([
                    'table_scan_session_id' => $session->id,
                    'menu_item_id' => $itemData['menu_item_id'],
                    'order_id' => null,
                    'quantity' => $itemData['quantity'] ?? 1,
                    'notes' => $itemData['notes'] ?? null,
                    'paid_addons' => $customizations['paid_addons'],
                    'free_addons' => $customizations['free_addons'],
                    'removed_items' => $customizations['removed_items'],
                    'selected_modifiers' => $customizations['selected_modifiers'],
                    'received_at' => now(),
                ]);
            }

            $total = 0.0;
            foreach ($cartItems as $ci) {
                $ci->load('menuItem:id,name,price,has_discount,discounted_price,vat_rate,tax_category,paid_addons');
                $total += TaxCalculationService::cartItemLineTotalGross($ci, $vendorCountry);
            }
            $total = round($total, 2);

            $order = Order::create([
                'order_public_id' => 'ord-' . Str::random(12),
                'customer_id' => null,
                'vendor_id' => $vendor->id,
                'table_scan_session_id' => $session->id,
                'status' => 'confirmed',
                'confirmed_at' => now(),
                'amount' => $total,
                'currency' => $currency,
                'payment_method' => 'cash',
                'payment_pending' => true,
                'payment_received' => false,
                'order_type' => 'dine-in',
            ]);

            CartItem::whereIn('id', collect($cartItems)->pluck('id'))
                ->update(['order_id' => $order->id]);

            return ['order' => $order, 'session' => $session, 'itemCount' => count($cartItems)];
        });

        $tableLabel = $table->name ?? "#{$table->number}";
        $actor = $request->user();

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
                'source_actor_type' => $actor instanceof \App\Models\TeamMember ? 'team_member' : 'vendor',
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

            if ($rawOptionIds->isEmpty()) {
                continue;
            }

            $validOptionIds = $group->options->pluck('id');
            $validIds = $rawOptionIds->intersect($validOptionIds)->values();

            if ($validIds->isEmpty()) {
                continue;
            }

            $maxSelection = max(1, (int) $group->max_selection);
            if ($group->type === 'single') {
                $validIds = $validIds->take(1);
            } else {
                $validIds = $validIds->take($maxSelection);
            }

            $normalized[] = [
                'modifier_group_id' => $groupId,
                'option_ids' => $validIds->all(),
            ];
        }

        return $normalized;
    }
}
