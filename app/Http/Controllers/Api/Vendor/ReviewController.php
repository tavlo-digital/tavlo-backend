<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/complaints?filter=all|pending|resolved
     */
    public function complaints(string $vendorId, Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $query = $vendor->reviews()->with('customer:id,name,email');

        $filter = $request->query('filter', 'all');
        if ($filter === 'pending') {
            $query->whereNull('vendor_reply');
        } elseif ($filter === 'resolved') {
            $query->whereNotNull('vendor_reply');
        }

        $reviews = $query->orderByDesc('created_at')
            ->get()
            ->map(fn (Review $r) => [
                'id' => (string) $r->id,
                'reviewPublicId' => $r->review_public_id,
                'customer' => $r->customer ? [
                    'id' => (string) $r->customer->id,
                    'name' => $r->customer->name,
                    'email' => $r->customer->email,
                ] : null,
                'rating' => $r->rating,
                'text' => $r->text,
                'vendorReply' => $r->vendor_reply,
                'vendorRepliedAt' => $r->vendor_replied_at?->toISOString(),
                'flagged' => $r->flagged,
                'flagReason' => $r->flag_reason,
                'createdAt' => $r->created_at->toISOString(),
            ]);

        return response()->json($reviews);
    }

    /**
     * POST /api/vendor/{vendorId}/reviews/{reviewId}/reply
     */
    public function reply(Request $request, string $vendorId, int $reviewId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $review = $vendor->reviews()->findOrFail($reviewId);

        $data = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->update([
            'vendor_reply' => $data['reply'],
            'vendor_replied_at' => now(),
        ]);

        return response()->json([
            'id' => (string) $review->id,
            'vendorReply' => $review->vendor_reply,
            'vendorRepliedAt' => $review->vendor_replied_at->toISOString(),
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/top-customers?period=6m
     */
    public function topCustomers(string $vendorId, Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);

        $period = $request->query('period', '6m');
        $months = match ($period) {
            '1m' => 1,
            '3m' => 3,
            '12m', '1y' => 12,
            default => 6,
        };

        $since = now()->subMonths($months);

        $topCustomers = DB::table('orders')
            ->select([
                'customers.id',
                'customers.name',
                'customers.email',
                DB::raw('COUNT(orders.id) as order_count'),
                DB::raw('SUM(orders.amount) as total_spent'),
                DB::raw('MAX(orders.created_at) as last_order_at'),
            ])
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->where('orders.vendor_id', $vendor->id)
            ->where('orders.created_at', '>=', $since)
            ->whereIn('orders.status', ['delivered', 'picked_up', 'completed'])
            ->groupBy('customers.id', 'customers.name', 'customers.email')
            ->orderByDesc('total_spent')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'orderCount' => (int) $c->order_count,
                'totalSpent' => (float) $c->total_spent,
                'lastOrderAt' => $c->last_order_at,
            ]);

        return response()->json($topCustomers);
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
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
