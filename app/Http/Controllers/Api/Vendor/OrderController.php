<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/orders
     */
    public function index(string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $orders = $vendor->orders()
            ->with('customer:id,name,email,phone,customer_public_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Order $order) => $this->formatOrder($order));

        return response()->json($orders);
    }

    /**
     * PATCH /api/orders/{orderId}
     */
    public function update(Request $request, string $orderId): JsonResponse
    {
        $order = Order::where('order_public_id', $orderId)
            ->orWhere('id', $orderId)
            ->firstOrFail();

        $this->authorizeVendor($request, $order->vendor);

        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,confirmed,preparing,ready,delivered,picked_up,cancelled'],
            'paymentPending' => ['sometimes', 'boolean'],
            'paymentReceived' => ['sometimes', 'boolean'],
            'paymentNote' => ['nullable', 'string', 'max:500'],
        ]);

        $mapped = [];
        if (isset($data['status'])) $mapped['status'] = $data['status'];
        if (isset($data['paymentPending'])) $mapped['payment_pending'] = $data['paymentPending'];
        if (isset($data['paymentReceived'])) {
            $mapped['payment_received'] = $data['paymentReceived'];
            if ($data['paymentReceived']) {
                $mapped['payment_confirmed_at'] = now();
            }
        }
        if (array_key_exists('paymentNote', $data)) $mapped['payment_note'] = $data['paymentNote'];

        $order->update($mapped);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/ready
     */
    public function markReady(Request $request, string $orderId): JsonResponse
    {
        $order = Order::where('order_public_id', $orderId)
            ->orWhere('id', $orderId)
            ->firstOrFail();

        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'status' => 'ready',
            'ready_at' => now(),
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    /**
     * PATCH /api/orders/{orderId}/picked-up
     */
    public function markPickedUp(Request $request, string $orderId): JsonResponse
    {
        $order = Order::where('order_public_id', $orderId)
            ->orWhere('id', $orderId)
            ->firstOrFail();

        $this->authorizeVendor($request, $order->vendor);

        $order->update([
            'status' => 'picked_up',
            'picked_up_at' => now(),
        ]);

        return response()->json($this->formatOrder($order->fresh()->load('customer')));
    }

    // ----------------------------------------------------------------

    private function formatOrder(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'orderPublicId' => $order->order_public_id,
            'customer' => $order->customer ? [
                'id' => (string) $order->customer->id,
                'name' => $order->customer->name,
                'email' => $order->customer->email,
                'phone' => $order->customer->phone,
            ] : null,
            'status' => $order->status,
            'itemsCount' => $order->items_count,
            'items' => $order->items ?? [],
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'paymentMethod' => $order->payment_method,
            'paymentPending' => $order->payment_pending,
            'paymentReceived' => $order->payment_received,
            'paymentConfirmedAt' => $order->payment_confirmed_at?->toISOString(),
            'paymentNote' => $order->payment_note,
            'readyAt' => $order->ready_at?->toISOString(),
            'pickedUpAt' => $order->picked_up_at?->toISOString(),
            'createdAt' => $order->created_at->toISOString(),
            'updatedAt' => $order->updated_at->toISOString(),
        ];
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
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
