<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnalyticsController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/analytics?period=daily|weekly|monthly
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $period = $request->query('period', 'weekly');

        [$start, $end, $labels] = match ($period) {
            'daily' => $this->dailyRange(),
            'monthly' => $this->monthlyRange(),
            default => $this->weeklyRange(),
        };

        // ---- Revenue chart ---
        $revenueData = $this->aggregateRevenue($vendor, $start, $end, $period);
        $ordersData = $this->aggregateOrderCount($vendor, $start, $end, $period);

        // ---- Summary stats ---
        $allOrders = $vendor->orders()
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $totalRevenue = $allOrders->where('payment_received', true)->sum('amount');
        $totalOrders = $allOrders->count();
        $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        $reviewsInPeriod = $vendor->reviews()->whereBetween('created_at', [$start, $end]);
        $avgRating = round($reviewsInPeriod->avg('rating') ?? 0, 1);
        $reviewCount = $reviewsInPeriod->count();

        // ---- Top items (aggregated across versions) ---
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
                    'revenue' => round(($current?->price ?? 0) * $row->total_ordered, 2),
                    'orders' => (int) $row->total_ordered,
                ];
            });

        // ---- Category breakdown (includes trashed versions) ---
        $categoryBreakdown = $vendor->menuCategories()
            ->with(['masterCategory', 'menuItems' => fn ($q) => $q->withTrashed()->where('ordered_count', '>', 0)])
            ->get()
            ->map(fn ($cat) => [
                'name' => $cat->display_name,
                'orders' => $cat->menuItems->sum('ordered_count'),
            ])
            ->filter(fn ($c) => $c['orders'] > 0)
            ->values();

        // ---- Payment method breakdown ---
        $paymentBreakdown = $allOrders
            ->where('payment_received', true)
            ->groupBy('payment_method')
            ->map(fn ($group, $method) => [
                'method' => $method ?? 'unknown',
                'revenue' => round($group->sum('amount'), 2),
                'count' => $group->count(),
            ])
            ->values();

        return response()->json([
            'period' => $period,
            'dateRange' => [
                'from' => $start->toISOString(),
                'to' => $end->toISOString(),
            ],
            'summary' => [
                'totalRevenue' => round($totalRevenue, 2),
                'totalOrders' => $totalOrders,
                'avgOrderValue' => $avgOrderValue,
                'avgRating' => $avgRating,
                'reviewCount' => $reviewCount,
            ],
            'revenueChart' => [
                'labels' => $labels,
                'data' => $revenueData,
            ],
            'ordersChart' => [
                'labels' => $labels,
                'data' => $ordersData,
            ],
            'topItems' => $topItems,
            'categoryBreakdown' => $categoryBreakdown,
            'paymentBreakdown' => $paymentBreakdown,
        ]);
    }

    // ----------------------------------------------------------------

    private function dailyRange(): array
    {
        $start = Carbon::today()->subDays(6)->startOfDay();
        $end = Carbon::today()->endOfDay();
        $labels = [];
        for ($d = clone $start; $d <= $end; $d->addDay()) {
            $labels[] = $d->format('D');        // Mon, Tue …
        }

        return [$start, $end, $labels];
    }

    private function weeklyRange(): array
    {
        $start = Carbon::today()->subWeeks(11)->startOfWeek();
        $end = Carbon::today()->endOfWeek();
        $labels = [];
        for ($d = clone $start; $d <= $end; $d->addWeek()) {
            $labels[] = 'W'.$d->weekOfYear;
        }

        return [$start, $end, $labels];
    }

    private function monthlyRange(): array
    {
        $start = Carbon::today()->subMonths(11)->startOfMonth();
        $end = Carbon::today()->endOfMonth();
        $labels = [];
        for ($d = clone $start; $d <= $end; $d->addMonth()) {
            $labels[] = $d->format('M');
        }

        return [$start, $end, $labels];
    }

    private function aggregateRevenue(Vendor $vendor, Carbon $start, Carbon $end, string $period): array
    {
        $orders = $vendor->orders()
            ->where('payment_received', true)
            ->whereBetween('created_at', [$start, $end])
            ->get(['amount', 'created_at']);

        return $this->bucket($orders, $start, $end, $period, fn ($group) => round($group->sum('amount'), 2));
    }

    private function aggregateOrderCount(Vendor $vendor, Carbon $start, Carbon $end, string $period): array
    {
        $orders = $vendor->orders()
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at']);

        return $this->bucket($orders, $start, $end, $period, fn ($group) => $group->count());
    }

    private function bucket($collection, Carbon $start, Carbon $end, string $period, callable $fn): array
    {
        $format = match ($period) {
            'daily' => 'Y-m-d',
            'monthly' => 'Y-m',
            default => 'Y-W',
        };

        $grouped = $collection->groupBy(fn ($o) => Carbon::parse($o->created_at)->format($format));

        $result = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $key = $cursor->format($format);
            $result[] = isset($grouped[$key]) ? $fn($grouped[$key]) : 0;
            match ($period) {
                'daily' => $cursor->addDay(),
                'monthly' => $cursor->addMonth(),
                default => $cursor->addWeek(),
            };
        }

        return $result;
    }

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
