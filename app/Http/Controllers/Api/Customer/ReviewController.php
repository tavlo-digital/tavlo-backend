<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\TableScanSession;
use App\Services\MediaService;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReviewController extends Controller
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly MediaService $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $reviews = Review::where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
                'order:id,table_scan_session_id',
                'items.menuItem:id,name,image_url',
            ])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        $reviews->getCollection()->transform(
            fn (Review $review) => $this->formatReview($review)
        );

        return response()->json($reviews);
    }

    public function sessionOrders(Request $request, int $sessionScanTableId): JsonResponse
    {
        $session = TableScanSession::whereKey($sessionScanTableId)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        [$orders, $cartItems] = $this->assertSessionReviewable($session, $request->user()->id);

        return response()->json([
            'session_scan_table_id' => $session->id,
            'orders' => $this->formatSessionOrders($orders, $cartItems),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_scan_table_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.cart_item_id' => ['required', 'integer', 'distinct'],
            'items.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'items.*.review' => ['nullable', 'string', 'max:1000'],
            'items.*.photos' => ['nullable', 'array', 'max:5'],
            'items.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $customer = $request->user();
        $session = TableScanSession::whereKey($validated['session_scan_table_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['Session not found.'],
            ]);
        }

        [, $cartItems] = $this->assertSessionReviewable($session, $customer->id);

        $cartItemIds = $cartItems->pluck('id')->all();
        $submittedIds = collect($validated['items'])->pluck('cart_item_id')->all();
        $invalid = array_diff($submittedIds, $cartItemIds);
        if (! empty($invalid)) {
            throw ValidationException::withMessages([
                'items' => ['One or more item IDs do not belong to this session.'],
            ]);
        }

        $reviewPublicId = 'rev_'.Str::random(16);
        $storedPaths = [];

        try {
            $review = DB::transaction(function () use ($validated, $customer, $session, $cartItems, $reviewPublicId, &$storedPaths) {
                $lockedSession = TableScanSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

                if (Review::where('table_scan_session_id', $lockedSession->id)->exists()) {
                    throw ValidationException::withMessages([
                        'session_scan_table_id' => ['You have already reviewed this session.'],
                    ]);
                }

                $reviewImages = $this->storePhotos(
                    $validated['photos'] ?? [],
                    "reviews/{$reviewPublicId}/photos",
                    $storedPaths
                );

                $review = Review::create([
                    'review_public_id' => $reviewPublicId,
                    'customer_id' => $customer->id,
                    'vendor_id' => $session->vendor_id,
                    'table_scan_session_id' => $session->id,
                    'rating' => $validated['rating'],
                    'text' => $validated['review'] ?? null,
                    'images' => $reviewImages ?: null,
                ]);

                $cartItemMap = $cartItems->keyBy('id');
                $menuItemIds = [];

                foreach ($validated['items'] as $itemData) {
                    $cartItem = $cartItemMap->get($itemData['cart_item_id']);
                    $itemImages = $this->storePhotos(
                        $itemData['photos'] ?? [],
                        "reviews/{$reviewPublicId}/items/{$cartItem->id}",
                        $storedPaths
                    );

                    ReviewItem::create([
                        'review_id' => $review->id,
                        'cart_item_id' => $cartItem->id,
                        'menu_item_id' => $cartItem->menu_item_id,
                        'rating' => $itemData['rating'],
                        'text' => $itemData['review'] ?? null,
                        'images' => $itemImages ?: null,
                    ]);

                    $menuItemIds[] = $cartItem->menu_item_id;
                }

                $this->recalculateMenuItemRatings(array_values(array_unique($menuItemIds)));

                return $review;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $this->media->delete($path);
            }

            throw $exception;
        }

        $review->load([
            'vendor:id,vendor_public_id,restaurant_name',
            'vendor.vendorSetting:id,vendor_id,date_format,time_format',
            'order:id,table_scan_session_id',
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
                $allowedCartItems = $this->cartItemsForReview($review);
                $cartItemIds = $allowedCartItems->pluck('id')->all();
                $submittedIds = collect($validated['items'])->pluck('cart_item_id')->all();
                $invalid = array_diff($submittedIds, $cartItemIds);
                if (! empty($invalid)) {
                    throw ValidationException::withMessages([
                        'items' => ['One or more item IDs do not belong to this session.'],
                    ]);
                }

                $cartItemMap = $allowedCartItems->keyBy('id');
                foreach ($validated['items'] as $itemData) {
                    $cartItem = $cartItemMap->get($itemData['cart_item_id']);

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
        $storedPaths = collect($review->images ?? [])
            ->merge($review->items->flatMap(fn (ReviewItem $item) => $item->images ?? []))
            ->filter()
            ->values();

        $review->delete();

        $this->recalculateMenuItemRatings($menuItemIds);

        $storedPaths->each(fn (string $path) => $this->media->delete($path));

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
            'session_scan_table_id' => $review->table_scan_session_id
                ?? $review->order?->table_scan_session_id,
            'rating' => $review->rating,
            'text' => $review->text,
            'photos' => $this->mediaUrls($review->images),
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
                'photos' => $this->mediaUrls($item->images),
            ])->values()->all(),
            'vendor_reply' => $review->vendor_reply,
            'vendor_replied_at' => $this->dateTimes->formatDateTime($review->vendor_replied_at, $review->vendor),
            'flagged' => $review->flagged,
            'created_at' => $this->dateTimes->formatDateTime($review->created_at, $review->vendor),
            'updated_at' => $this->dateTimes->formatDateTime($review->updated_at, $review->vendor),
        ];

        return $payload;
    }

    private function assertSessionReviewable(TableScanSession $session, int $customerId): array
    {
        $alreadyReviewed = Review::where('table_scan_session_id', $session->id)
            ->orWhereHas('order', fn ($query) => $query->where('table_scan_session_id', $session->id))
            ->exists();

        if ($alreadyReviewed) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['You have already reviewed this session.'],
            ]);
        }

        $orders = Order::where('table_scan_session_id', $session->id)
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['This session has no orders.'],
            ]);
        }

        if ($orders->contains(fn (Order $order) => ! $order->payment_received)) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['All orders must be paid before you can review this session.'],
            ]);
        }

        $cartItems = $this->cartItemsForOrders($orders);

        if ($cartItems->isEmpty()) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['This session has no order items.'],
            ]);
        }

        if ($cartItems->contains(fn (CartItem $item) => $item->served_at === null)) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['Items are not served yet.'],
            ]);
        }

        return [$orders, $cartItems];
    }

    private function cartItemsForOrders(Collection $orders): Collection
    {
        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return CartItem::where(function ($query) use ($orderIds) {
            $query->whereIn('order_id', $orderIds->all());

            foreach ($orderIds as $orderId) {
                $query->orWhereJsonContains('shared_order_ids', $orderId);
            }
        })->orderBy('id')->get();
    }

    private function cartItemsForReview(Review $review): Collection
    {
        $sessionId = $review->table_scan_session_id ?? $review->order?->table_scan_session_id;

        if ($sessionId) {
            $orders = Order::where('table_scan_session_id', $sessionId)
                ->where('customer_id', $review->customer_id)
                ->get();

            return $this->cartItemsForOrders($orders);
        }

        return $review->order_id
            ? CartItem::where('order_id', $review->order_id)->get()
            : collect();
    }

    private function formatSessionOrders(Collection $orders, Collection $cartItems): array
    {
        return $orders->map(function (Order $order) use ($cartItems) {
            $items = $cartItems
                ->filter(fn (CartItem $item) => $this->cartItemBelongsToOrder($item, $order->id))
                ->unique('id')
                ->values();

            return [
                'order_id' => $order->order_public_id,
                'total_amount_paid' => round((float) $order->amount + (float) $order->tip_amount, 2),
                'items' => $items->map(fn (CartItem $item) => [
                    'cart_item_id' => $item->id,
                ])->all(),
            ];
        })->values()->all();
    }

    private function cartItemBelongsToOrder(CartItem $item, int $orderId): bool
    {
        $sharedOrderIds = array_map('intval', $item->shared_order_ids ?? []);

        return (int) $item->order_id === $orderId
            || in_array($orderId, $sharedOrderIds, true);
    }

    private function storePhotos(array $photos, string $directory, array &$storedPaths): array
    {
        $paths = [];

        foreach ($photos as $photo) {
            $path = $this->media->store($photo, $directory);
            $paths[] = $path;
            $storedPaths[] = $path;
        }

        return $paths;
    }

    private function mediaUrls(?array $paths): array
    {
        return collect($paths ?? [])
            ->map(fn (string $path) => $this->media->url($path))
            ->filter()
            ->values()
            ->all();
    }
}
