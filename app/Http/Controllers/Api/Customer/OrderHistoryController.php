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

        $restaurants = Vendor::whereHas('orders', fn ($q) => $q->where('customer_id', $customer->id))
            ->withCount(['orders' => fn ($q) => $q->where('customer_id', $customer->id)])
            ->withSum(
                ['orders' => fn ($q) => $q->where('customer_id', $customer->id)],
                'amount'
            )
            ->withMax(
                ['orders' => fn ($q) => $q->where('customer_id', $customer->id)],
                'created_at'
            )
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

        $orders = Order::where('customer_id', $customer->id)
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20), [
                'id', 'order_public_id', 'order_type', 'table_number',
                'amount', 'currency', 'status', 'created_at',
            ]);

        $summary = [
            'total_orders' => Order::where('customer_id', $customer->id)->where('vendor_id', $vendor->id)->count(),
            'total_spent'  => Order::where('customer_id', $customer->id)->where('vendor_id', $vendor->id)->sum('amount'),
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
        $order = Order::where('order_public_id', $orderPublicId)
            ->where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name,slug',
            ])
            ->firstOrFail();

        return response()->json($order);
    }
}
