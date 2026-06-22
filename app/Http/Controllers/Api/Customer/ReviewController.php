<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    public function __construct(private readonly VendorDateTimeService $dateTimes) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = Review::where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
                'items.menuItem:id,name,image_url',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $reviews->getCollection()->transform(
            fn (Review $review) => $this->formatReview($review)
        );

        return response()->json($reviews);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.cart_item_id' => ['required', 'integer'],
            'items.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'items.*.review' => ['nullable', 'string', 'max:1000'],
        ]);

        $customer = $request->user();

        $order = Order::where('order_public_id', $validated['order_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $order) {
            throw ValidationException::withMessages([
                'order_id' => ['Order not found.'],
            ]);
        }

        if (! $order->payment_received) {
            throw ValidationException::withMessages([
                'order_id' => ['You can only review a paid order.'],
            ]);
        }

        $cartItems = CartItem::where('order_id', $order->id)->get();

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'order_id' => ['This order has no items.'],
            ]);
        }

        $unserved = $cartItems->filter(fn (CartItem $item) => $item->served_at === null);
        if ($unserved->isNotEmpty()) {
            throw ValidationException::withMessages([
                'order_id' => ['All items must be served before you can review this order.'],
            ]);
        }

        $existing = Review::where('order_id', $order->id)->first();
        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this order.'], 422);
        }

        $cartItemIds = $cartItems->pluck('id')->all();
        $submittedIds = collect($validated['items'])->pluck('cart_item_id')->all();
        $invalid = array_diff($submittedIds, $cartItemIds);
        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'items' => ['One or more item IDs do not belong to this order.'],
            ]);
        }

        $review = DB::transaction(function () use ($validated, $customer, $order, $cartItems) {
            $review = Review::create([
                'review_public_id' => 'rev_' . Str::random(16),
                'customer_id' => $customer->id,
                'vendor_id' => $order->vendor_id,
                'order_id' => $order->id,
                'rating' => $validated['rating'],
                'text' => $validated['review'] ?? null,
            ]);

            $cartItemMap = $cartItems->keyBy('id');
            $menuItemRatings = [];

            foreach ($validated['items'] as $itemData) {
                $cartItem = $cartItemMap->get($itemData['cart_item_id']);

                ReviewItem::create([
                    'review_id' => $review->id,
                    'cart_item_id' => $cartItem->id,
                    'menu_item_id' => $cartItem->menu_item_id,
                    'rating' => $itemData['rating'],
                    'text' => $itemData['review'] ?? null,
                ]);

                $menuItemRatings[$cartItem->menu_item_id][] = $itemData['rating'];
            }

            foreach ($menuItemRatings as $menuItemId => $ratings) {
                $menuItem = MenuItem::find($menuItemId);
                if (! $menuItem) {
                    continue;
                }
                $currentTotal = (float) $menuItem->rating * (int) $menuItem->review_count;
                $newCount = (int) $menuItem->review_count + count($ratings);
                $newAvg = ($currentTotal + array_sum($ratings)) / $newCount;

                $menuItem->update([
                    'rating' => round($newAvg, 2),
                    'review_count' => $newCount,
                ]);
            }

            return $review;
        });

        $review->load([
            'vendor:id,vendor_public_id,restaurant_name',
            'vendor.vendorSetting:id,vendor_id,date_format,time_format',
            'items.menuItem:id,name,image_url',
        ]);

        return response()->json([
            'message' => 'Review submitted.',
            'review' => $this->formatReview($review),
        ], 201);
    }

    public function update(Request $request, string $reviewPublicId): JsonResponse
    {
        $validated = $request->validate([
            'rating' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'text' => ['nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array'],
            'items.*.cart_item_id' => ['required_with:items', 'integer'],
            'items.*.rating' => ['required_with:items', 'integer', 'min:1', 'max:5'],
            'items.*.review' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = Review::where('review_public_id', $reviewPublicId)
            ->where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
                'items',
            ])
            ->firstOrFail();

        DB::transaction(function () use ($review, $validated) {
            $review->update([
                'rating' => $validated['rating'] ?? $review->rating,
                'text' => array_key_exists('text', $validated) ? $validated['text'] : $review->text,
            ]);

            if (isset($validated['items'])) {
                $cartItemIds = CartItem::where('order_id', $review->order_id)->pluck('id')->all();
                $submittedIds = collect($validated['items'])->pluck('cart_item_id')->all();
                $invalid = array_diff($submittedIds, $cartItemIds);
                if (! empty($invalid)) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more item IDs do not belong to this order.'],
                    ]);
                }

                foreach ($validated['items'] as $itemData) {
                    $cartItem = CartItem::find($itemData['cart_item_id']);
                    if (! $cartItem) {
                        continue;
                    }

                    ReviewItem::updateOrCreate(
                        ['review_id' => $review->id, 'cart_item_id' => $cartItem->id],
                        [
                            'menu_item_id' => $cartItem->menu_item_id,
                            'rating' => $itemData['rating'],
                            'text' => $itemData['review'] ?? null,
                        ],
                    );
                }

                $this->recalculateMenuItemRatings(
                    collect($validated['items'])->pluck('cart_item_id')
                        ->map(fn ($id) => CartItem::find($id)?->menu_item_id)
                        ->filter()
                        ->unique()
                        ->all()
                );
            }
        });

        $review->load('items.menuItem:id,name,image_url');

        return response()->json([
            'message' => 'Review updated.',
            'review' => $this->formatReview($review),
        ]);
    }

    public function destroy(Request $request, string $reviewPublicId): JsonResponse
    {
        $review = Review::where('review_public_id', $reviewPublicId)
            ->where('customer_id', $request->user()->id)
            ->with('items')
            ->firstOrFail();

        $menuItemIds = $review->items->pluck('menu_item_id')->unique()->all();

        $review->delete();

        $this->recalculateMenuItemRatings($menuItemIds);

        return response()->json(['message' => 'Review deleted.']);
    }

    private function recalculateMenuItemRatings(array $menuItemIds): void
    {
        foreach ($menuItemIds as $menuItemId) {
            $stats = ReviewItem::where('menu_item_id', $menuItemId)
                ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total')
                ->first();

            MenuItem::where('id', $menuItemId)->update([
                'rating' => round((float) $stats->avg_rating, 2),
                'review_count' => (int) $stats->total,
            ]);
        }
    }

    private function formatReview(Review $review): array
    {
        $payload = [
            'review_public_id' => $review->review_public_id,
            'order_id' => $review->order?->order_public_id ?? null,
            'rating' => $review->rating,
            'text' => $review->text,
            'images' => $review->images ?: [],
            'vendor' => $review->vendor ? [
                'vendor_public_id' => $review->vendor->vendor_public_id,
                'restaurant_name' => $review->vendor->restaurant_name,
            ] : null,
            'items' => $review->items->map(fn (ReviewItem $item) => [
                'cart_item_id' => $item->cart_item_id,
                'menu_item_id' => $item->menu_item_id,
                'menu_item_name' => $item->menuItem?->name,
                'menu_item_image' => $item->menuItem?->image_url,
                'rating' => $item->rating,
                'text' => $item->text,
            ])->values()->all(),
            'vendor_reply' => $review->vendor_reply,
            'vendor_replied_at' => $this->dateTimes->formatDateTime($review->vendor_replied_at, $review->vendor),
            'flagged' => $review->flagged,
            'created_at' => $this->dateTimes->formatDateTime($review->created_at, $review->vendor),
            'updated_at' => $this->dateTimes->formatDateTime($review->updated_at, $review->vendor),
        ];

        return $payload;
    }
}
