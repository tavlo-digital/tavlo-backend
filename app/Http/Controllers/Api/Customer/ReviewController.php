<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\Vendor;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(private readonly VendorDateTimeService $dateTimes) {}

    /**
     * Get all reviews by the customer.
     */
    public function index(Request $request): JsonResponse
    {
        $reviews = Review::where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));
        $reviews->getCollection()->transform(
            fn (Review $review) => $this->formatReview($review)
        );

        return response()->json($reviews);
    }

    /**
     * Create a new review for a vendor the customer has ordered from.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_public_id' => ['required', 'string', 'exists:vendors,vendor_public_id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
        ]);

        $vendor = Vendor::where('vendor_public_id', $validated['vendor_public_id'])->firstOrFail();

        $order = Order::where('vendor_id', $vendor->id)
            ->where('customer_id', $request->user()->id)
            ->where('payment_received', true)
            ->where('status', '!=', 'draft')
            ->latest('created_at')
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'vendor_public_id' => ['You must have a paid order with this restaurant before reviewing it.'],
            ]);
        }

        $existing = Review::where('vendor_id', $vendor->id)
            ->where('customer_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this restaurant.'], 422);
        }

        $review = Review::create([
            'review_public_id' => 'rev_'.Str::random(16),
            'customer_id' => $request->user()->id,
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'rating' => $validated['rating'],
            'text' => $validated['review'] ?? null,
        ]);

        return response()->json([
            'message' => 'Review submitted.',
            'review' => $this->formatReview($review->load([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
            ])),
        ], 201);
    }

    /**
     * Update an existing review.
     */
    public function update(Request $request, string $reviewPublicId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'text' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::where('review_public_id', $reviewPublicId)
            ->where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
            ])
            ->firstOrFail();

        $review->update($validated);

        return response()->json([
            'message' => 'Review updated.',
            'review' => $this->formatReview($review),
        ]);
    }

    /**
     * Delete a review.
     */
    public function destroy(Request $request, string $reviewPublicId): JsonResponse
    {
        $review = Review::where('review_public_id', $reviewPublicId)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        $review->delete();

        return response()->json(['message' => 'Review deleted.']);
    }

    private function formatReview(Review $review): array
    {
        $payload = $review->toArray();
        $payload['created_at'] = $this->dateTimes->formatDateTime($review->created_at, $review->vendor);
        $payload['updated_at'] = $this->dateTimes->formatDateTime($review->updated_at, $review->vendor);
        $payload['vendor_replied_at'] = $this->dateTimes->formatDateTime(
            $review->vendor_replied_at,
            $review->vendor,
        );

        return $payload;
    }
}
