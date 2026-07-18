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

class DashboardController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/dashboard
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // Keep dashboard aggregates in the database; hydrating every order for
        // two whole days made this endpoint grow with restaurant traffic.
        $revenueToday = (float) $vendor->orders()
            ->whereDate('created_at', $today)
            ->where('payment_received', true)
            ->sum('amount');
        $revenueYesterday = (float) $vendor->orders()
            ->whereDate('created_at', $yesterday)
            ->where('payment_received', true)
            ->sum('amount');
        $tipsToday = round((float) $vendor->orders()
            ->whereDate('created_at', $today)
            ->where('payment_received', true)
            ->sum('tip_amount'), 2);
        $tipsYesterday = round((float) $vendor->orders()
            ->whereDate('created_at', $yesterday)
            ->where('payment_received', true)
            ->sum('tip_amount'), 2);
        $ordersToday = $vendor->orders()->whereDate('created_at', $today)->count();
        $ordersYesterday = $vendor->orders()->whereDate('created_at', $yesterday)->count();

        $avgToday = $ordersToday ? round($revenueToday / $ordersToday, 2) : 0;
        $avgYesterday = $ordersYesterday ? round($revenueYesterday / $ordersYesterday, 2) : 0;

        // A daily KPI must not silently fall back to an all-time average.
        $ratingToday = $vendor->reviews()->whereDate('created_at', $today)->avg('rating');
        $ratingYesterday = $vendor->reviews()->whereDate('created_at', $yesterday)->avg('rating');

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

        // ---- Tables waiting to pay ---
        $tablesWaitingToPay = $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->whereIn('status', array_merge(['in_progress'], Order::COMPLETED_STATUSES))
            ->count();

        // ---- Alerts: unpaid orders older than 10 minutes ---
        $unpaidOld = $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->where('created_at', '<=', now()->subMinutes(10))
            ->count();

        $alerts = [];
        if ($unpaidOld > 0) {
            $alerts[] = [
                'id' => 'unpaid-old',
                'severity' => 'warning',
                'message' => "{$unpaidOld} ".($unpaidOld === 1 ? 'table has' : 'tables have').' unpaid orders outstanding for over 10 minutes',
                'navigateTo' => 'orders',
            ];
        }

        // ---- Check critical inventory ---
        $criticalInventory = $vendor->inventoryItems()
            ->where('quantity', '<=', 0)
            ->where('track_stock', true)
            ->count();
        if ($criticalInventory > 0) {
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
        if ($lowInventory > 0) {
            $alerts[] = [
                'id' => 'low-inventory',
                'severity' => 'warning',
                'message' => "{$lowInventory} ".($lowInventory === 1 ? 'item is' : 'items are').' running low on stock',
                'navigateTo' => 'inventory',
            ];
        }

        // ---- Top items by ordered_count, aggregated across versions ---
        $topItems = MenuItem::withTrashed()
            ->where('vendor_id', $vendor->id)
            ->where('ordered_count', '>', 0)
            ->select('product_uid')
            ->selectRaw('SUM(ordered_count) as total_ordered')
            ->groupBy('product_uid')
            ->orderByDesc('total_ordered')
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
                    'orderedCount' => (int) $row->total_ordered,
                ];
            });

        // ---- Recent orders ---
        $recentOrders = $vendor->orders()
            ->with('customer:customers.id,first_name,last_name,phone,customer_public_id')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => [
                'id' => (string) $order->id,
                'orderNumber' => $order->order_number ?? "#{$order->id}",
                'orderType' => $order->order_type ?? 'dine-in',
                'tableNumber' => $order->table_number,
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

        // ---- Revenue at risk (unpaid ready/delivered orders) ---
        $revenueAtRisk = (float) $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->whereIn('status', array_merge(['in_progress'], Order::COMPLETED_STATUSES))
            ->sum('amount');

        $currency = $vendor->currency ?? 'EUR';

        return response()->json([
            'liveStatus' => [
                'occupiedTables' => $occupiedTables,
                'totalTables' => $totalTables,
                'activeOrders' => $activeOrders,
                'tablesWaitingToPay' => $tablesWaitingToPay,
                'kitchenLoad' => $kitchenLoad,
            ],
            'todayKPIs' => [
                'ordersToday' => $ordersToday,
                'ordersYesterday' => $ordersYesterday,
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
}
