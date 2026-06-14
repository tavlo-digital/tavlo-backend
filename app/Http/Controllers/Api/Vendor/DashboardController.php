<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
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

        $today     = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ---- Revenue / order KPIs — DB aggregates only, no full row hydration ----
        $revenueToday     = (float) $vendor->orders()->whereDate('created_at', $today)->where('payment_received', true)->sum('amount');
        $revenueYesterday = (float) $vendor->orders()->whereDate('created_at', $yesterday)->where('payment_received', true)->sum('amount');
        $ordersToday      = $vendor->orders()->whereDate('created_at', $today)->count();
        $ordersYesterday  = $vendor->orders()->whereDate('created_at', $yesterday)->count();

        $avgToday     = $ordersToday     ? round($revenueToday / $ordersToday, 2)     : 0;
        $avgYesterday = $ordersYesterday ? round($revenueYesterday / $ordersYesterday, 2) : 0;

        // ---- Rating — return 0 when no reviews (no all-time avg fallback) ----
        $ratingToday     = $vendor->reviews()->whereDate('created_at', $today)->avg('rating');
        $ratingYesterday = $vendor->reviews()->whereDate('created_at', $yesterday)->avg('rating');

        // ---- Active orders & derived kitchen load ----
        $activeOrders = $vendor->orders()
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready'])
            ->count();

        $kitchenLoad = match (true) {
            $activeOrders >= 15 => 'high',
            $activeOrders >= 5  => 'medium',
            default             => 'low',
        };

        // ---- Occupied tables: distinct table_numbers with active dine-in orders ----
        $occupiedTables = $vendor->orders()
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready'])
            ->whereNotNull('table_number')
            ->distinct('table_number')
            ->count('table_number');

        $totalTables = $vendor->restaurantTables()->where('is_active', true)->count();

        // ---- Tables waiting to pay ----
        $tablesWaitingToPay = $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->whereIn('status', ['ready', 'delivered', 'picked_up'])
            ->count();

        // ---- Alerts ----
        $alerts = [];

        $unpaidOld = $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->where('created_at', '<=', now()->subMinutes(10))
            ->count();
        if ($unpaidOld > 0) {
            $alerts[] = [
                'id'         => 'unpaid-old',
                'severity'   => 'warning',
                'message'    => "{$unpaidOld} " . ($unpaidOld === 1 ? 'table has' : 'tables have') . " unpaid orders outstanding for over 10 minutes",
                'navigateTo' => 'orders',
            ];
        }

        $criticalInventory = $vendor->inventoryItems()->where('quantity', '<=', 0)->where('track_stock', true)->count();
        if ($criticalInventory > 0) {
            $alerts[] = [
                'id'         => 'critical-inventory',
                'severity'   => 'danger',
                'message'    => "{$criticalInventory} " . ($criticalInventory === 1 ? 'item is' : 'items are') . " out of stock",
                'navigateTo' => 'inventory',
            ];
        }

        $lowInventory = $vendor->inventoryItems()
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'min_stock')
            ->where('track_stock', true)
            ->count();
        if ($lowInventory > 0) {
            $alerts[] = [
                'id'         => 'low-inventory',
                'severity'   => 'warning',
                'message'    => "{$lowInventory} " . ($lowInventory === 1 ? 'item is' : 'items are') . " running low on stock",
                'navigateTo' => 'inventory',
            ];
        }

        // ---- Top items (all-time ordered_count) ----
        $topItems = $vendor->menuItems()
            ->where('ordered_count', '>', 0)
            ->orderByDesc('ordered_count')
            ->limit(5)
            ->get(['id', 'name', 'price', 'ordered_count'])
            ->map(fn ($item) => [
                'id'           => (string) $item->id,
                'name'         => $item->name,
                'price'        => (float) $item->price,
                'orderedCount' => (int) $item->ordered_count,
            ]);

        // ---- Recent orders ----
        $recentOrders = $vendor->orders()
            ->with('customer:customers.id,first_name,last_name,phone,customer_public_id')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($order) => [
                'id'             => (string) $order->id,
                'orderNumber'    => $order->order_number ?? "#{$order->id}",
                'orderType'      => $order->order_type ?? 'dine-in',
                'tableNumber'    => $order->table_number,
                'status'         => $order->status,
                'amount'         => (float) $order->amount,
                'currency'       => $order->currency,
                'paymentPending' => (bool) $order->payment_pending,
                'createdAt'      => $order->created_at->toISOString(),
                'customer'       => $order->customer ? [
                    'name'  => trim($order->customer->first_name . ' ' . $order->customer->last_name),
                    'phone' => $order->customer->phone,
                ] : null,
            ]);

        // ---- Revenue at risk (unpaid ready/delivered orders) ----
        $revenueAtRisk = (float) $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->whereIn('status', ['ready', 'delivered', 'picked_up', 'served'])
            ->sum('amount');

        $currency = $vendor->currency ?? 'EUR';

        return response()->json([
            'liveStatus' => [
                'occupiedTables'     => $occupiedTables,
                'totalTables'        => $totalTables,
                'activeOrders'       => $activeOrders,
                'tablesWaitingToPay' => $tablesWaitingToPay,
                'kitchenLoad'        => $kitchenLoad,
            ],
            'todayKPIs' => [
                'ordersToday'             => $ordersToday,
                'ordersYesterday'         => $ordersYesterday,
                'revenueToday'            => round($revenueToday, 2),
                'revenueYesterday'        => round($revenueYesterday, 2),
                'avgOrderValue'           => $avgToday,
                'avgOrderValueYesterday'  => $avgYesterday,
                'customerRating'          => round($ratingToday ?? 0, 1),
                'customerRatingYesterday' => round($ratingYesterday ?? 0, 1),
                'currency'                => $currency,
            ],
            'alerts'       => $alerts,
            'recentOrders' => $recentOrders,
            'topItems'     => $topItems,
            'revenueAtRisk' => [
                'total'    => round($revenueAtRisk, 2),
                'currency' => $currency,
            ],
        ]);
    }

    // ----------------------------------------------------------------

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
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
