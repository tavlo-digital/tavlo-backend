<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderHistoryController extends Controller
{
    public function __construct(private readonly MediaService $media)
    {
    }

    /**
     * Get the customer's full account order history grouped by restaurant.
     */
    public function history(Request $request): JsonResponse
    {
        $customer = $request->user();
        $perPage = min(max($request->integer('per_page', 10), 1), 50);
        $page = max($request->integer('page', 1), 1);

        $base = Order::query()
            ->where(fn (Builder $query) => $this->scopeCustomerOrders($query, $customer->id))
            ->where('status', '!=', 'draft');

        $orders = (clone $base)
            ->with([
                'vendor.vendorSetting:id,vendor_id,logo_url,currency',
                'tableScanSession:id,customer_id',
            ])
            ->orderByDesc('created_at')
            ->get();

        $history = $orders
            ->groupBy('vendor_id')
            ->map(function (Collection $vendorOrders) use ($page, $perPage) {
                /** @var Order $first */
                $first = $vendorOrders->first();
                $vendor = $first->vendor;
                $settings = $vendor?->vendorSetting;
                $pagedOrders = $vendorOrders->forPage($page, $perPage)->values();
                $total = $vendorOrders->count();

                return [
                    'restaurant_public_id' => $vendor?->vendor_public_id,
                    'restaurant_name' => $vendor?->restaurant_name,
                    'restaurant_logo_url' => $this->media->url($settings?->logo_url),
                    'currency' => $settings?->currency ?? $first->currency,
                    'orders_count' => $total,
                    'total_spent' => round((float) $vendorOrders->sum(fn (Order $order) => (float) $order->amount), 2),
                    'last_ordered_at' => $vendorOrders->max('created_at')?->toIso8601String(),
                    'orders' => $pagedOrders->map(fn (Order $order) => $this->formatHistoryOrder($order))->all(),
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'last_page' => max((int) ceil($total / $perPage), 1),
                        'has_more' => $page < max((int) ceil($total / $perPage), 1),
                    ],
                ];
            })
            ->sortByDesc('last_ordered_at')
            ->values();

        return response()->json([
            'history' => $history,
            'summary' => [
                'restaurants_count' => $history->count(),
                'orders_count' => (clone $base)->count(),
                'total_spent' => round((float) (clone $base)->sum('amount'), 2),
            ],
        ]);
    }

    /**
     * Get restaurants the customer has ordered from (grouped).
     */
    public function restaurants(Request $request): JsonResponse
    {
        $customer = $request->user();

        $byCustomer = fn ($q) => $q->whereHas(
            'tableScanSession',
            fn ($s) => $s->where('customer_id', $customer->id)
        );

        $restaurants = Vendor::whereHas('orders', $byCustomer)
            ->withCount(['orders' => $byCustomer])
            ->withSum(['orders' => $byCustomer], 'amount')
            ->withMax(['orders' => $byCustomer], 'created_at')
            ->get([
                'id', 'vendor_public_id', 'restaurant_name', 'slug',
            ]);

        return response()->json($restaurants);
    }

    /**
     * Get orders for a specific restaurant.
     */
    public function vendorOrders(Request $request, string $vendorPublicId): JsonResponse
    {
        $customer = $request->user();
        $vendor = Vendor::where('vendor_public_id', $vendorPublicId)->firstOrFail();

        $base = fn () => Order::whereHas(
            'tableScanSession',
            fn ($s) => $s->where('customer_id', $customer->id)
        )->where('vendor_id', $vendor->id);

        $orders = $base()
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20), [
                'id', 'order_public_id', 'order_type', 'table_number',
                'amount', 'currency', 'status', 'created_at',
            ]);

        $summary = [
            'total_orders' => $base()->count(),
            'total_spent'  => $base()->sum('amount'),
        ];

        return response()->json([
            'restaurant' => [
                'id'   => $vendor->vendor_public_id,
                'name' => $vendor->restaurant_name,
            ],
            'summary' => $summary,
            'orders'  => $orders,
        ]);
    }

    /**
     * Show a single order detail.
     */
    public function show(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('order_public_id', $orderPublicId)
            ->where(fn (Builder $query) => $this->scopeCustomerOrders($query, $customer->id))
            ->with([
                'vendor.vendorSetting:id,vendor_id,logo_url,currency',
                'tableScanSession:id,customer_id',
            ])
            ->firstOrFail();

        return response()->json($this->formatOrderDetail($order));
    }

    /**
     * Track one authenticated participant's order.
     */
    public function tracking(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('order_public_id', $orderPublicId)
            ->where(fn (Builder $query) => $this->scopeCustomerOrders($query, $customer->id))
            ->with([
                'vendor.vendorSetting:id,vendor_id,currency,estimated_prep_time',
                'tableScanSession:id,customer_id',
            ])
            ->firstOrFail();

        return response()->json($this->formatTrackingOrder($order));
    }

    private function scopeCustomerOrders(Builder $query, int $customerId): void
    {
        $query
            ->where('customer_id', $customerId)
            ->orWhereHas(
                'tableScanSession',
                fn (Builder $session) => $session->where('customer_id', $customerId)
            );
    }

    private function formatHistoryOrder(Order $order): array
    {
        $items = $this->linkedCartItems($order);
        $subtotal = max(0, (float) $order->amount - (float) ($order->vat_amount ?? 0) - (float) ($order->service_fee ?? 0));

        return [
            'order_id' => $order->order_number ?? (string) $order->id,
            'order_public_id' => $order->order_public_id,
            'created_at' => $order->created_at?->toIso8601String(),
            'order_type' => $order->order_type,
            'payment_status' => $this->paymentStatus($order),
            'payment_method' => $order->payment_method,
            'items_count' => (int) $items->sum('quantity'),
            'subtotal' => round($subtotal, 2),
            'vat' => round((float) ($order->vat_amount ?? 0), 2),
            'service_fee' => round((float) ($order->service_fee ?? 0), 2),
            'total_amount' => round((float) $order->amount, 2),
            'items' => $items->map(fn (CartItem $item) => $this->formatHistoryItem($item))->values()->all(),
        ];
    }

    private function formatHistoryItem(CartItem $item): array
    {
        $menuItem = $item->menuItem;
        $unitPrice = (float) ($menuItem?->price ?? 0);
        $lineTotal = round($unitPrice * $item->quantity, 2);

        return [
            'id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'name' => $menuItem?->name,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'image_url' => $this->media->url($menuItem?->image_url),
            'notes' => $item->notes,
        ];
    }

    private function formatOrderDetail(Order $order): array
    {
        $vendor = $order->vendor;
        $settings = $vendor?->vendorSetting;
        $items = $this->linkedCartItems($order);
        $subtotal = max(0, (float) $order->amount - (float) ($order->vat_amount ?? 0) - (float) ($order->service_fee ?? 0));

        return [
            'order_id' => $order->order_number ?? (string) $order->id,
            'order_public_id' => $order->order_public_id,
            'restaurant' => [
                'restaurant_public_id' => $vendor?->vendor_public_id,
                'restaurant_name' => $vendor?->restaurant_name,
                'logo_url' => $this->media->url($settings?->logo_url),
            ],
            'created_at' => $order->created_at?->toIso8601String(),
            'status' => $order->status,
            'order_type' => $order->order_type,
            'payment_status' => $this->paymentStatus($order),
            'payment_method' => $order->payment_method,
            'items' => $items->map(fn (CartItem $item) => $this->formatOrderDetailItem($item))->values()->all(),
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'vat' => round((float) ($order->vat_amount ?? 0), 2),
                'service_fee' => round((float) ($order->service_fee ?? 0), 2),
                'total' => round((float) $order->amount, 2),
                'currency' => $settings?->currency ?? $order->currency,
            ],
        ];
    }

    private function formatOrderDetailItem(CartItem $item): array
    {
        $historyItem = $this->formatHistoryItem($item);
        unset($historyItem['id']);

        return $historyItem;
    }

    private function formatTrackingOrder(Order $order): array
    {
        $ownedItems = $this->ownedCartItems($order);
        $sharedIntoItems = $this->sharedIntoCartItems($order);
        $ownedSharedItems = $ownedItems
            ->filter(fn (CartItem $item) => ! empty($item->order_ids))
            ->values();
        $sharedItems = $ownedSharedItems->merge($sharedIntoItems)->values();
        $ordersById = $this->sharingOrdersById($sharedItems);
        $sessionCustomerNames = $this->sessionCustomerNames($ordersById);

        return [
            'id' => $order->id,
            'order_public_id' => $order->order_public_id,
            'order_number' => $order->order_number ? (string) $order->order_number : null,
            'status' => $order->status,
            'estimated_delivery_time' => $this->estimatedDeliveryTime($order),
            'total_amount' => round((float) $order->amount, 2),
            'currency' => $order->vendor?->vendorSetting?->currency ?? $order->currency,
            'order_type' => $order->order_type,
            'payment_method' => $order->payment_method,
            'payment_pending' => (bool) $order->payment_pending,
            'payment_received' => (bool) $order->payment_received,
            'items' => $ownedItems
                ->map(fn (CartItem $item) => $this->formatTrackingItem($item))
                ->values()
                ->all(),
            'shared_items' => $sharedItems
                ->map(fn (CartItem $item) => $this->formatTrackingSharedItem($item, $ordersById, $sessionCustomerNames))
                ->values()
                ->all(),
        ];
    }

    private function formatTrackingItem(CartItem $item): array
    {
        $menuItem = $item->menuItem;
        $unitPrice = (float) ($menuItem?->price ?? 0);
        $lineTotal = round($unitPrice * $item->quantity, 2);

        return [
            'cart_item_id' => $item->id,
            'menu_item_id' => $item->menu_item_id,
            'name' => $menuItem?->name,
            'image_url' => $this->media->url($menuItem?->image_url),
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'status' => $this->cartItemStatus($item),
            'notes' => $item->notes,
        ];
    }

    private function formatTrackingSharedItem(CartItem $item, Collection $ordersById, Collection $sessionCustomerNames): array
    {
        $payload = $this->formatTrackingItem($item);
        $orderIds = array_values(array_map('intval', is_array($item->order_ids) ? $item->order_ids : []));
        $sharedBetween = 1 + count($orderIds);

        $payload['shared_between'] = $sharedBetween;
        $payload['shared_with'] = array_values(array_filter(array_map(function (int $orderId) use ($ordersById, $sessionCustomerNames) {
            $order = $ordersById->get($orderId);

            if (! $order) {
                return null;
            }

            $customer = $order->tableScanSession?->customer ?? $order->customer;
            $customerName = $sessionCustomerNames->get($order->table_scan_session_id);

            return [
                'order_id' => $order->id,
                'customer_id' => $customer?->id,
                'customer_name' => $customerName,
            ];
        }, $orderIds)));
        $payload['my_share'] = round($payload['line_total'] / $sharedBetween, 2);

        return $payload;
    }

    private function ownedCartItems(Order $order): Collection
    {
        if (! $order->table_scan_session_id) {
            return collect();
        }

        return CartItem::with('menuItem:id,name,price,image_url')
            ->where('table_scan_session_id', $order->table_scan_session_id)
            ->orderBy('id')
            ->get();
    }

    private function sharedIntoCartItems(Order $order): Collection
    {
        if (! $order->table_scan_session_id) {
            return collect();
        }

        return CartItem::with('menuItem:id,name,price,image_url')
            ->whereJsonContains('order_ids', $order->id)
            ->where('table_scan_session_id', '!=', $order->table_scan_session_id)
            ->orderBy('id')
            ->get();
    }

    private function sharingOrdersById(Collection $items): Collection
    {
        $orderIds = $items
            ->flatMap(fn (CartItem $item) => is_array($item->order_ids) ? $item->order_ids : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return Order::with([
            'customer:id,first_name,last_name',
            'tableScanSession.customer:id,first_name,last_name',
        ])
            ->whereIn('id', $orderIds->all())
            ->get()
            ->keyBy('id');
    }

    private function sessionCustomerNames(Collection $ordersById): Collection
    {
        return $ordersById->mapWithKeys(function (Order $order) {
            $customer = $order->tableScanSession?->customer ?? $order->customer;

            return [
                $order->table_scan_session_id => $customer
                    ? trim($customer->first_name . ' ' . $customer->last_name)
                    : 'Guest',
            ];
        });
    }

    private function estimatedDeliveryTime(Order $order): ?string
    {
        if (! $order->created_at) {
            return null;
        }

        $prepMinutes = (int) ($order->vendor?->vendorSetting?->estimated_prep_time ?? 20);

        return $order->created_at->copy()->addMinutes($prepMinutes)->toIso8601String();
    }

    private function linkedCartItems(Order $order): Collection
    {
        return CartItem::with('menuItem:id,name,price,image_url')
            ->where(function (Builder $query) use ($order) {
                if ($order->table_scan_session_id) {
                    $query->where('table_scan_session_id', $order->table_scan_session_id);
                }

                $query->orWhereJsonContains('order_ids', $order->id);
            })
            ->orderBy('id')
            ->get();
    }

    private function paymentStatus(Order $order): string
    {
        if ($order->payment_received) {
            return 'paid';
        }

        if ($order->payment_pending) {
            return 'pending';
        }

        return 'unpaid';
    }

    private function cartItemStatus(CartItem $item): ?string
    {
        if ($item->served_at) {
            return 'Served';
        }

        if ($item->ready_at) {
            return 'Ready';
        }

        if ($item->preparing_start_at) {
            return 'Preparing';
        }

        return null;
    }
}
