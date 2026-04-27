<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderHistoryController extends Controller
{
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
            ->whereHas(
                'tableScanSession',
                fn ($s) => $s->where('customer_id', $customer->id)
            )
            ->with([
                'vendor:id,vendor_public_id,restaurant_name,slug',
            ])
            ->firstOrFail();

        return response()->json($order);
    }
}
