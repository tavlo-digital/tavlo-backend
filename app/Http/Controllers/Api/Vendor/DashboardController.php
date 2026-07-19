<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/dashboard
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $timezone = $vendor->resolveTimezone();
        $todayLocal = Carbon::now($timezone)->startOfDay();
        $todayStart = $todayLocal->copy()->utc();
        $todayEnd = $todayLocal->copy()->endOfDay()->utc();
        $yesterdayStart = $todayLocal->copy()->subDay()->utc();
        $yesterdayEnd = $todayLocal->copy()->subDay()->endOfDay()->utc();

        // Revenue belongs to the day payment was confirmed, not the day an
        // order draft was created. Older paid rows without a confirmation
        // timestamp fall back to created_at for backwards compatibility.
        $paidToday = $this->paidInRangeQuery($vendor, $todayStart, $todayEnd);
        $paidYesterday = $this->paidInRangeQuery($vendor, $yesterdayStart, $yesterdayEnd);
        $revenueToday = (float) (clone $paidToday)->sum('amount');
        $revenueYesterday = (float) (clone $paidYesterday)->sum('amount');
        $tipsToday = round((float) (clone $paidToday)->sum('tip_amount'), 2);
        $tipsYesterday = round((float) (clone $paidYesterday)->sum('tip_amount'), 2);
        $paidOrdersToday = (clone $paidToday)->count();
        $paidOrdersYesterday = (clone $paidYesterday)->count();

        // Count placed orders only. Drafts were never submitted, and
        // cancelled orders should not inflate service KPIs.
        $ordersToday = $this->placedInRangeQuery($vendor, $todayStart, $todayEnd)->count();
        $ordersYesterday = $this->placedInRangeQuery($vendor, $yesterdayStart, $yesterdayEnd)->count();

        $avgToday = $paidOrdersToday ? round($revenueToday / $paidOrdersToday, 2) : 0;
        $avgYesterday = $paidOrdersYesterday ? round($revenueYesterday / $paidOrdersYesterday, 2) : 0;

        // A daily KPI must not silently fall back to an all-time average.
        $ratingToday = $vendor->reviews()->whereBetween('created_at', [$todayStart, $todayEnd])->avg('rating');
        $ratingYesterday = $vendor->reviews()->whereBetween('created_at', [$yesterdayStart, $yesterdayEnd])->avg('rating');

        // ---- Active orders ---
        $activeOrders = $vendor->orders()
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->count();

        $kitchenLoad = match (true) {
            $activeOrders >= 15 => 'high',
            $activeOrders >= 5 => 'medium',
            default => 'low',
        };

        // Current dine-in orders are linked through table_scan_sessions; the
        // legacy orders.table_number column is often null. Count distinct
        // restaurant tables so multiple participants/orders do not inflate it.
        $occupiedTables = TableScanSession::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->whereNotNull('restaurant_table_id')
            ->whereHas('orders', fn ($query) => $query->whereIn('status', Order::ACTIVE_STATUSES))
            ->distinct()
            ->count('restaurant_table_id');
        $totalTables = $vendor->restaurantTables()->where('is_active', true)->count();

        // An unpaid payable order is outstanding whether or not the guest has
        // already opened a Stripe intent or requested cash payment.
        $unpaidPayable = $vendor->orders()
            ->where('payment_received', false)
            ->whereIn('status', array_merge([Order::STATUS_IN_PROGRESS], Order::COMPLETED_STATUSES));
        $ordersWaitingToPay = (clone $unpaidPayable)->count();

        $tablesWaitingToPay = TableScanSession::query()
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->whereNotNull('restaurant_table_id')
            ->whereHas('orders', fn ($query) => $query
                ->where('payment_received', false)
                ->whereIn('status', array_merge([Order::STATUS_IN_PROGRESS], Order::COMPLETED_STATUSES)))
            ->distinct()
            ->count('restaurant_table_id');

        // ---- Alerts: unpaid orders older than 10 minutes ---
        $unpaidOld = $vendor->orders()
            ->where('payment_received', false)
            ->whereIn('status', array_merge([Order::STATUS_IN_PROGRESS], Order::COMPLETED_STATUSES))
            ->where('created_at', '<=', now()->subMinutes(10))
            ->count();

        $alerts = [];
        if ($unpaidOld > 0) {
            $alerts[] = [
                'id' => 'unpaid-old',
                'severity' => 'warning',
                'message' => "{$unpaidOld} ".($unpaidOld === 1 ? 'order has' : 'orders have').' been unpaid for over 10 minutes',
                'navigateTo' => 'orders',
            ];
        }

        $showInventoryAlerts = (bool) data_get(
            $vendor->inventorySettings?->settings,
            'alerts.dashboardAlerts',
            true
        );

        // ---- Check critical inventory ---
        $criticalInventory = $vendor->inventoryItems()
            ->where('quantity', '<=', 0)
            ->where('track_stock', true)
            ->count();
        if ($showInventoryAlerts && $criticalInventory > 0) {
            $alerts[] = [
                'id' => 'critical-inventory',
                'severity' => 'danger',
                'message' => "{$criticalInventory} ".($criticalInventory === 1 ? 'item is' : 'items are').' out of stock',
                'navigateTo' => 'inventory',
            ];
        }

        // ---- Low-stock inventory ---
        $lowInventory = $vendor->inventoryItems()
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'min_stock')
            ->where('track_stock', true)
            ->count();
        if ($showInventoryAlerts && $lowInventory > 0) {
            $alerts[] = [
                'id' => 'low-inventory',
                'severity' => 'warning',
                'message' => "{$lowInventory} ".($lowInventory === 1 ? 'item is' : 'items are').' running low on stock',
                'navigateTo' => 'inventory',
            ];
        }

        // ---- Top items from actual submitted cart items ---
        // ordered_count is legacy data and is not maintained by current order
        // flows. Querying cart_items keeps this section genuinely dynamic and
        // combines historical menu versions through product_uid.
        $topItems = DB::table('cart_items')
            ->join('orders', 'orders.id', '=', 'cart_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'cart_items.menu_item_id')
            ->where('orders.vendor_id', $vendor->id)
            ->whereNotIn('orders.status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
            ->select('menu_items.product_uid')
            ->selectRaw('COUNT(DISTINCT cart_items.order_id) as order_count')
            ->selectRaw('SUM(cart_items.quantity) as quantity_sold')
            ->groupBy('menu_items.product_uid')
            ->orderByDesc('quantity_sold')
            ->orderByDesc('order_count')
            ->limit(5)
            ->get()
            ->map(function ($row) use ($vendor) {
                $current = MenuItem::where('vendor_id', $vendor->id)
                    ->where('product_uid', $row->product_uid)
                    ->first()
                    ?? MenuItem::withTrashed()->where('vendor_id', $vendor->id)
                        ->where('product_uid', $row->product_uid)
                        ->latest()
                        ->first();

                return [
                    'id' => (string) ($current?->id ?? 0),
                    'name' => $current?->name ?? 'Unknown',
                    'price' => (float) ($current?->price ?? 0),
                    'orderedCount' => (int) $row->order_count,
                    'quantitySold' => (int) $row->quantity_sold,
                ];
            });

        // ---- Recent orders ---
        $recentOrders = $vendor->orders()
            ->with([
                'customer:customers.id,first_name,last_name,phone,customer_public_id',
                'tableScanSession.restaurantTable:id,number,name',
            ])
            ->where('status', '!=', Order::STATUS_DRAFT)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => [
                'id' => (string) $order->id,
                'orderNumber' => $order->order_number ?? "#{$order->id}",
                'orderType' => $order->order_type ?? 'dine-in',
                'tableNumber' => $order->tableScanSession?->restaurantTable?->number ?? $order->table_number,
                'status' => $order->status,
                'amount' => (float) $order->amount,
                'currency' => $order->currency,
                'paymentPending' => (bool) $order->payment_pending,
                'paymentReceived' => (bool) $order->payment_received,
                'createdAt' => $order->created_at->toISOString(),
                'customer' => $order->customer ? [
                    'name' => trim($order->customer->first_name.' '.$order->customer->last_name),
                    'phone' => $order->customer->phone,
                ] : null,
            ]);

        // ---- Revenue at risk (all currently outstanding payable orders) ---
        $revenueAtRisk = (float) (clone $unpaidPayable)->sum('amount');

        $currency = $vendor->currency ?? 'EUR';

        return response()->json([
            'liveStatus' => [
                'occupiedTables' => $occupiedTables,
                'totalTables' => $totalTables,
                'activeOrders' => $activeOrders,
                'tablesWaitingToPay' => $tablesWaitingToPay,
                'ordersWaitingToPay' => $ordersWaitingToPay,
                'kitchenLoad' => $kitchenLoad,
            ],
            'todayKPIs' => [
                'ordersToday' => $ordersToday,
                'ordersYesterday' => $ordersYesterday,
                'paidOrdersToday' => $paidOrdersToday,
                'paidOrdersYesterday' => $paidOrdersYesterday,
                'revenueToday' => round($revenueToday, 2),
                'revenueYesterday' => round($revenueYesterday, 2),
                'tipsToday' => $tipsToday,
                'tipsYesterday' => $tipsYesterday,
                'avgOrderValue' => $avgToday,
                'avgOrderValueYesterday' => $avgYesterday,
                'customerRating' => round($ratingToday ?? 0, 1),
                'customerRatingYesterday' => round($ratingYesterday ?? 0, 1),
                'currency' => $currency,
            ],
            'alerts' => $alerts,
            'recentOrders' => $recentOrders,
            'topItems' => $topItems,
            'revenueAtRisk' => [
                'total' => round($revenueAtRisk, 2),
                'currency' => $currency,
            ],
            'timezone' => $timezone,
            'generatedAt' => now()->toISOString(),
        ]);
    }

    // ----------------------------------------------------------------

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if ($user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }

    private function paidInRangeQuery(Vendor $vendor, Carbon $start, Carbon $end)
    {
        return $vendor->orders()
            ->where('payment_received', true)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('payment_confirmed_at', [$start, $end])
                    ->orWhere(function ($legacy) use ($start, $end) {
                        $legacy->whereNull('payment_confirmed_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });
    }

    private function placedInRangeQuery(Vendor $vendor, Carbon $start, Carbon $end)
    {
        return $vendor->orders()
            ->whereNotIn('status', [Order::STATUS_DRAFT, Order::STATUS_CANCELLED])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('confirmed_at', [$start, $end])
                    ->orWhere(function ($legacy) use ($start, $end) {
                        $legacy->whereNull('confirmed_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            });
    }
}
