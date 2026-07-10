<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
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

        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        // ---- Orders for today / yesterday ---
        $todayOrders     = $vendor->orders()->whereDate('created_at', $today)->get();
        $yesterdayOrders = $vendor->orders()->whereDate('created_at', $yesterday)->get();

        $paidToday     = $todayOrders->where('payment_received', true);
        $paidYesterday = $yesterdayOrders->where('payment_received', true);

        $revenueToday     = $paidToday->sum('amount');
        $revenueYesterday = $paidYesterday->sum('amount');

        $tipsToday     = round((float) $paidToday->sum('tip_amount'), 2);
        $tipsYesterday = round((float) $paidYesterday->sum('tip_amount'), 2);

        $ordersToday     = $todayOrders->count();
        $ordersYesterday = $yesterdayOrders->count();

        $avgToday     = $ordersToday     ? round($revenueToday / $ordersToday, 2)     : 0;
        $avgYesterday = $ordersYesterday ? round($revenueYesterday / $ordersYesterday, 2) : 0;

        // ---- Rating ---
        $ratingData     = $vendor->reviews()->selectRaw('AVG(rating) as avg, COUNT(*) as total')->first();
        $ratingToday    = $vendor->reviews()->whereDate('created_at', $today)->avg('rating');
        $ratingYesterday = $vendor->reviews()->whereDate('created_at', $yesterday)->avg('rating');

        // ---- Active orders ---
        $activeOrders = $vendor->orders()
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->count();

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
                'id'         => 'unpaid-old',
                'severity'   => 'warning',
                'message'    => "{$unpaidOld} " . ($unpaidOld === 1 ? 'table has' : 'tables have') . " unpaid orders outstanding for over 10 minutes",
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
                'id'         => 'critical-inventory',
                'severity'   => 'danger',
                'message'    => "{$criticalInventory} " . ($criticalInventory === 1 ? 'item is' : 'items are') . " out of stock",
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
                'id'         => 'low-inventory',
                'severity'   => 'warning',
                'message'    => "{$lowInventory} " . ($lowInventory === 1 ? 'item is' : 'items are') . " running low on stock",
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
                    'id'           => (string) ($current?->id ?? 0),
                    'name'         => $current?->name ?? 'Unknown',
                    'price'        => (float) ($current?->price ?? 0),
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
                'id'           => (string) $order->id,
                'orderNumber'  => $order->order_number ?? "#{$order->id}",
                'orderType'    => $order->order_type ?? 'dine-in',
                'tableNumber'  => $order->table_number,
                'status'       => $order->status,
                'amount'       => (float) $order->amount,
                'currency'     => $order->currency,
                'paymentPending' => (bool) $order->payment_pending,
                'createdAt'    => $order->created_at->toISOString(),
                'customer'     => $order->customer ? [
                    'name'  => trim($order->customer->first_name . ' ' . $order->customer->last_name),
                    'phone' => $order->customer->phone,
                ] : null,
            ]);

        // ---- Revenue at risk (unpaid ready/delivered orders) ---
        $revenueAtRisk = $vendor->orders()
            ->where('payment_pending', true)
            ->where('payment_received', false)
            ->whereIn('status', array_merge(['in_progress'], Order::COMPLETED_STATUSES))
            ->sum('amount');

        // ---- Open tables count ---
        $totalTables = $vendor->restaurantTables()->where('is_active', true)->count();

        return response()->json([
            'liveStatus' => [
                'openTables'          => $totalTables,
                'activeOrders'        => $activeOrders,
                'tablesWaitingToPay'  => $tablesWaitingToPay,
            ],
            'todayKPIs' => [
                'ordersToday'          => $ordersToday,
                'ordersYesterday'      => $ordersYesterday,
                'revenueToday'         => round($revenueToday, 2),
                'revenueYesterday'     => round($revenueYesterday, 2),
                'tipsToday'            => $tipsToday,
                'tipsYesterday'        => $tipsYesterday,
                'avgOrderValue'        => $avgToday,
                'avgOrderValueYesterday' => $avgYesterday,
                'customerRating'       => round($ratingToday ?? $ratingData->avg ?? 0, 1),
                'customerRatingYesterday' => round($ratingYesterday ?? 0, 1),
            ],
            'alerts'        => $alerts,
            'recentOrders'  => $recentOrders,
            'topItems'      => $topItems,
            'revenueAtRisk' => [
                'total'        => round($revenueAtRisk, 2),
                'unpaidTables' => $tablesWaitingToPay,
                'currency'     => $vendor->currency ?? 'EUR',
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
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
