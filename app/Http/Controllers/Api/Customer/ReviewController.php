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

    /**
     * GET /api/customer/reviews/item/{menuItemId}
     * Returns only the matching item-level entry from each session review.
     */
    public function itemReviews(Request $request, int $menuItemId): JsonResponse
    {
        $menuItem = MenuItem::query()
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format,is_live_and_discoverable',
            ])
            ->whereKey($menuItemId)
            ->where('is_active', true)
            ->firstOrFail();

        if (! $menuItem->vendor?->vendorSetting?->is_live_and_discoverable) {
            abort(404);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $reviews = ReviewItem::query()
            ->where('menu_item_id', $menuItem->id)
            ->whereHas('review', fn ($query) => $query->where('flagged', false))
            ->with([
                'review:id,review_public_id,customer_id,vendor_id,order_id,table_scan_session_id,anonymous,flagged,created_at,updated_at',
                'review.customer:id,first_name,last_name,profile_picture',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $reviews->getCollection()->transform(function (ReviewItem $itemReview) use ($menuItem) {
            $review = $itemReview->review;
            $customer = $review?->customer;
            $isAnonymous = (bool) $review?->anonymous;

            if ($isAnonymous) {
                $reviewerName = $this->anonymousDisplayName($review->id);
            } else {
                $reviewerName = $customer
                    ? trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''))
                    : '';
            }

            return [
                'review_public_id' => $review?->review_public_id,
                'session_scan_table_id' => $review?->table_scan_session_id,
                'cart_item_id' => $itemReview->cart_item_id,
                'rating' => $itemReview->rating,
                'text' => $itemReview->text,
                'photos' => $this->mediaUrls($itemReview->images),
                'reviewer' => [
                    'name' => $reviewerName !== '' ? $reviewerName : 'Anonymous',
                    'profile_picture' => $isAnonymous ? null : $customer?->profile_picture,
                ],
                'anonymous' => $isAnonymous,
                'created_at' => $this->dateTimes->formatDateTime($itemReview->created_at, $menuItem->vendor),
                'updated_at' => $this->dateTimes->formatDateTime($itemReview->updated_at, $menuItem->vendor),
            ];
        });

        $counts = ReviewItem::query()
            ->where('menu_item_id', $menuItem->id)
            ->whereHas('review', fn ($query) => $query->where('flagged', false))
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        $totalReviews = (int) $counts->sum();
        $weightedRating = $counts->reduce(
            fn (int|float $total, $count, $rating) => $total + ((int) $rating * (int) $count),
            0,
        );
        $payload = $reviews->toArray();
        $payload['menu_item'] = [
            'id' => $menuItem->id,
            'name' => $menuItem->name,
            'image_url' => $menuItem->image_url,
        ];
        $payload['review_summary'] = [
            'average_rating' => $totalReviews > 0 ? round($weightedRating / $totalReviews, 1) : 0,
            'total_reviews' => $totalReviews,
            'rating_breakdown' => collect(range(5, 1))->map(function (int $rating) use ($counts, $totalReviews) {
                $count = (int) ($counts[$rating] ?? 0);

                return [
                    'star' => $rating,
                    'count' => $count,
                    'percent' => $totalReviews > 0 ? round(($count / $totalReviews) * 100, 1) : 0,
                ];
            })->all(),
        ];

        return response()->json($payload);
    }

    public function sessions(Request $request): JsonResponse
    {
        $customer = $request->user();

        $sessions = TableScanSession::where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $sessionIds = $sessions->pluck('id');

        $allOrders = Order::whereIn('table_scan_session_id', $sessionIds)
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        $allReviews = Review::with([
            'items.menuItem:id,name,image_url',
            'vendor:id,vendor_public_id,restaurant_name',
            'vendor.vendorSetting:id,vendor_id,date_format,time_format,enable_reviews',
        ])
            ->whereIn('table_scan_session_id', $sessionIds)
            ->get()
            ->keyBy('table_scan_session_id');

        $vendorIds = $sessions->pluck('vendor_id')->unique()->all();
        $vendorSettings = \App\Models\VendorSetting::whereIn('vendor_id', $vendorIds)
            ->pluck('enable_reviews', 'vendor_id');

        $data = $sessions->map(function (TableScanSession $session) use ($allOrders, $allReviews, $vendorSettings) {
            $orders = $allOrders->get($session->id, collect());

            if ($orders->isEmpty()) {
                return null;
            }

            $reviewsEnabled = $vendorSettings[$session->vendor_id] ?? true;

            $activeOrders = $orders->filter(fn (Order $order) => ! in_array($order->status, ['cancelled', 'draft']));
            $cartItems = $this->cartItemsForOrders($orders);

            $allPaid = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $order) => ! $order->payment_received);
            $allServed = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $order) => ! in_array($order->status, ['served', 'picked_up']));

            $existingReview = $allReviews->get($session->id);

            $entry = [
                'session_scan_table_id' => $session->id,
                'reviewed' => $existingReview !== null,
                'reviewable' => $reviewsEnabled && $allPaid && $allServed && $existingReview === null,
                'all_paid' => $allPaid,
                'all_served' => $allServed,
                'orders' => $this->formatSessionOrders($orders, $cartItems),
            ];

            if ($existingReview) {
                $entry['review'] = $this->formatReview($existingReview);
            }

            return $entry;
        })->filter()->values()->all();

        return response()->json(['data' => $data]);
    }

    public function vendorReviewableSessions(Request $request, string $vendorPublicId): JsonResponse
    {
        $customer = $request->user();

        $vendor = \App\Models\Vendor::where('vendor_public_id', $vendorPublicId)
            ->with('vendorSetting:id,vendor_id,enable_reviews')
            ->firstOrFail();

        $reviewsEnabled = $vendor->vendorSetting->enable_reviews ?? true;

        if (! $reviewsEnabled) {
            return response()->json([
                'vendor' => [
                    'vendor_public_id' => $vendor->vendor_public_id,
                    'restaurant_name' => $vendor->restaurant_name,
                ],
                'data' => [],
            ]);
        }

        $sessions = TableScanSession::where('customer_id', $customer->id)
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('id')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $sessionIds = $sessions->pluck('id');

        $allOrders = Order::whereIn('table_scan_session_id', $sessionIds)
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get()
            ->groupBy('table_scan_session_id');

        $reviewedSessionIds = Review::whereIn('table_scan_session_id', $sessionIds)
            ->pluck('table_scan_session_id')
            ->all();

        $data = $sessions->map(function (TableScanSession $session) use ($allOrders, $reviewedSessionIds) {
            if (in_array($session->id, $reviewedSessionIds, true)) {
                return null;
            }

            $orders = $allOrders->get($session->id, collect());

            if ($orders->isEmpty()) {
                return null;
            }

            $activeOrders = $orders->filter(fn (Order $order) => ! in_array($order->status, ['cancelled', 'draft']));

            $allPaid = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $order) => ! $order->payment_received);
            $allServed = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $order) => ! in_array($order->status, ['served', 'picked_up']));

            if (! $allPaid || ! $allServed) {
                return null;
            }

            $cartItems = $this->cartItemsForOrders($orders)->load('menuItem:id,name,image_url');

            return [
                'session_scan_table_id' => $session->id,
                'scanned_at' => $session->scanned_at?->toDateTimeString(),
                'items' => $cartItems->map(fn (CartItem $item) => [
                    'cart_item_id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'menu_item_name' => $item->menuItem?->name,
                    'menu_item_image' => $item->menuItem?->image_url,
                    'quantity' => $item->quantity,
                ])->values()->all(),
            ];
        })->filter()->values()->all();

        return response()->json([
            'vendor' => [
                'vendor_public_id' => $vendor->vendor_public_id,
                'restaurant_name' => $vendor->restaurant_name,
            ],
            'data' => $data,
        ]);
    }

    /**
     * GET /api/customer/reviews/vendor/{vendorPublicId}/eligibility
     *
     * Reports which kinds of reviews the vendor currently allows, based on the
     * vendor's Reviews settings. Lets the customer app decide whether to surface
     * the restaurant-review, per-item-review, and anonymous-review options.
     */
    public function eligibility(string $vendorPublicId): JsonResponse
    {
        $vendor = \App\Models\Vendor::where('vendor_public_id', $vendorPublicId)
            ->with('vendorSetting:id,vendor_id,enable_reviews,enable_menu_reviews,allow_anonymous_reviews')
            ->firstOrFail();

        $settings = $vendor->vendorSetting;

        return response()->json([
            'enableReviews' => $settings->enable_reviews ?? true,
            'enableMenuReviews' => $settings->enable_menu_reviews ?? true,
            'allowAnonymousReviews' => (bool) ($settings->allow_anonymous_reviews ?? false),
        ]);
    }

    public function sessionByOrder(Request $request, string $orderPublicId): JsonResponse
    {
        $customer = $request->user();

        $order = Order::where('order_public_id', $orderPublicId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        if (! $order->table_scan_session_id) {
            throw ValidationException::withMessages([
                'order' => ['This order is not linked to a table session.'],
            ]);
        }

        $session = TableScanSession::findOrFail($order->table_scan_session_id);

        $vendorSettings = \App\Models\VendorSetting::where('vendor_id', $session->vendor_id)->first();
        $reviewsEnabled = $vendorSettings->enable_reviews ?? true;

        $orders = Order::where('table_scan_session_id', $session->id)
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        $activeOrders = $orders->filter(fn (Order $o) => ! in_array($o->status, ['cancelled', 'draft']));
        $cartItems = $this->cartItemsForOrders($orders)->load('menuItem:id,name,image_url');

        $allPaid = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $o) => ! $o->payment_received);
        $allServed = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $o) => ! in_array($o->status, ['served', 'picked_up']));

        $existingReview = Review::where('table_scan_session_id', $session->id)->exists();

        return response()->json([
            'session_scan_table_id' => $session->id,
            'reviewable' => $reviewsEnabled && $allPaid && $allServed && ! $existingReview,
            'all_paid' => $allPaid,
            'all_served' => $allServed,
            'reviewed' => $existingReview,
            'items' => $cartItems->map(fn (CartItem $item) => [
                'cart_item_id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'menu_item_name' => $item->menuItem?->name,
                'menu_item_image' => $item->menuItem?->image_url,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ]);
    }

    public function sessionOrders(Request $request, int $sessionScanTableId): JsonResponse
    {
        $customer = $request->user();

        $session = TableScanSession::whereKey($sessionScanTableId)
            ->where('customer_id', $customer->id)
            ->firstOrFail();

        $vendorSettings = \App\Models\VendorSetting::where('vendor_id', $session->vendor_id)->first();
        $reviewsEnabled = $vendorSettings->enable_reviews ?? true;

        $orders = Order::where('table_scan_session_id', $session->id)
            ->where('customer_id', $customer->id)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['This session has no orders.'],
            ]);
        }

        $activeOrders = $orders->filter(fn (Order $order) => ! in_array($order->status, ['cancelled', 'draft']));
        $cartItems = $this->cartItemsForOrders($orders);

        $allPaid = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $order) => ! $order->payment_received);
        $allServed = $activeOrders->isNotEmpty() && ! $activeOrders->contains(fn (Order $order) => ! in_array($order->status, ['served', 'picked_up']));

        $existingReview = Review::with([
            'items.menuItem:id,name,image_url',
            'vendor:id,vendor_public_id,restaurant_name',
            'vendor.vendorSetting:id,vendor_id,date_format,time_format',
        ])
            ->where('table_scan_session_id', $session->id)
            ->first();

        $response = [
            'session_scan_table_id' => $session->id,
            'reviewed' => $existingReview !== null,
            'reviewable' => $reviewsEnabled && $allPaid && $allServed && $existingReview === null,
            'all_paid' => $allPaid,
            'all_served' => $allServed,
            'orders' => $this->formatSessionOrders($orders, $cartItems),
        ];

        if ($existingReview) {
            $response['review'] = $this->formatReview($existingReview);
        }

        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();

        $sessionId = $request->input('session_scan_table_id');
        $session = TableScanSession::whereKey($sessionId)
            ->where('customer_id', $customer->id)
            ->first();

        if (! $session) {
            throw ValidationException::withMessages([
                'session_scan_table_id' => ['Session not found.'],
            ]);
        }

        $vendorSettings = \App\Models\VendorSetting::where('vendor_id', $session->vendor_id)->first();

        if (! ($vendorSettings->enable_reviews ?? true)) {
            return response()->json(['message' => 'Reviews are disabled for this restaurant.'], 403);
        }

        $menuReviewsEnabled = $vendorSettings->enable_menu_reviews ?? false;
        $anonymousAllowed = (bool) ($vendorSettings->allow_anonymous_reviews ?? false);

        $itemsRules = $menuReviewsEnabled
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        $validated = $request->validate([
            'session_scan_table_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'review' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'anonymous' => ['sometimes', 'boolean'],
            'items' => $itemsRules,
            'items.*.cart_item_id' => ['required_with:items', 'integer', 'distinct'],
            'items.*.rating' => ['required_with:items', 'integer', 'min:1', 'max:5'],
            'items.*.review' => ['nullable', 'string', 'max:1000'],
            'items.*.photos' => ['nullable', 'array', 'max:5'],
            'items.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        [, $cartItems] = $this->assertSessionReviewable($session, $customer->id);

        $submittedItems = $validated['items'] ?? [];

        if (! empty($submittedItems)) {
            $cartItemIds = $cartItems->pluck('id')->all();
            $submittedIds = collect($submittedItems)->pluck('cart_item_id')->all();
            $invalid = array_diff($submittedIds, $cartItemIds);
            if (! empty($invalid)) {
                throw ValidationException::withMessages([
                    'items' => ['One or more item IDs do not belong to this session.'],
                ]);
            }
        }

        $isAnonymous = $anonymousAllowed && ($validated['anonymous'] ?? false);

        $reviewPublicId = 'rev_'.Str::random(16);
        $storedPaths = [];

        try {
            $review = DB::transaction(function () use ($validated, $customer, $session, $cartItems, $reviewPublicId, &$storedPaths, $submittedItems, $isAnonymous) {
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
                    'anonymous' => $isAnonymous,
                ]);

                $cartItemMap = $cartItems->keyBy('id');
                $menuItemIds = [];

                foreach ($submittedItems as $itemData) {
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

                if (! empty($menuItemIds)) {
                    $this->recalculateMenuItemRatings(array_values(array_unique($menuItemIds)));
                }

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
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['string'],
            'anonymous' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array'],
            'items.*.cart_item_id' => ['required_with:items', 'integer'],
            'items.*.rating' => ['required_with:items', 'integer', 'min:1', 'max:5'],
            'items.*.review' => ['nullable', 'string', 'max:1000'],
            'items.*.photos' => ['nullable', 'array', 'max:5'],
            'items.*.photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'items.*.remove_photos' => ['nullable', 'array'],
            'items.*.remove_photos.*' => ['string'],
        ]);

        $review = Review::where('review_public_id', $reviewPublicId)
            ->where('customer_id', $request->user()->id)
            ->with([
                'vendor:id,vendor_public_id,restaurant_name',
                'vendor.vendorSetting:id,vendor_id,date_format,time_format',
                'items',
            ])
            ->firstOrFail();

        $vendorSettings = \App\Models\VendorSetting::where('vendor_id', $review->vendor_id)->first();
        $anonymousAllowed = (bool) ($vendorSettings->allow_anonymous_reviews ?? false);

        $storedPaths = [];
        $removedPaths = [];

        try {
            DB::transaction(function () use ($review, $validated, &$storedPaths, &$removedPaths, $anonymousAllowed) {
                $existingImages = $review->images ?? [];

                $removeReviewPhotos = $validated['remove_photos'] ?? [];
                if (! empty($removeReviewPhotos)) {
                    $removedPaths = array_merge($removedPaths, array_intersect($existingImages, $removeReviewPhotos));
                    $existingImages = array_values(array_diff($existingImages, $removeReviewPhotos));
                }

                $newReviewPhotos = $this->storePhotos(
                    $validated['photos'] ?? [],
                    "reviews/{$review->review_public_id}/photos",
                    $storedPaths
                );

                $mergedImages = array_merge($existingImages, $newReviewPhotos);
                if (count($mergedImages) > 5) {
                    throw ValidationException::withMessages([
                        'photos' => ['A review may have at most 5 photos.'],
                    ]);
                }

                $updateData = [
                    'rating' => $validated['rating'] ?? $review->rating,
                    'text' => array_key_exists('text', $validated) ? $validated['text'] : $review->text,
                    'images' => $mergedImages ?: null,
                ];

                if (array_key_exists('anonymous', $validated)) {
                    $updateData['anonymous'] = $anonymousAllowed && $validated['anonymous'];
                }

                $review->update($updateData);

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
                        $existingItem = $review->items->firstWhere('cart_item_id', $cartItem->id);

                        $itemExistingImages = $existingItem?->images ?? [];

                        $removeItemPhotos = $itemData['remove_photos'] ?? [];
                        if (! empty($removeItemPhotos)) {
                            $removedPaths = array_merge($removedPaths, array_intersect($itemExistingImages, $removeItemPhotos));
                            $itemExistingImages = array_values(array_diff($itemExistingImages, $removeItemPhotos));
                        }

                        $newItemPhotos = $this->storePhotos(
                            $itemData['photos'] ?? [],
                            "reviews/{$review->review_public_id}/items/{$cartItem->id}",
                            $storedPaths
                        );

                        $mergedItemImages = array_merge($itemExistingImages, $newItemPhotos);
                        if (count($mergedItemImages) > 5) {
                            throw ValidationException::withMessages([
                                'items' => ["Item {$cartItem->id} may have at most 5 photos."],
                            ]);
                        }

                        ReviewItem::updateOrCreate(
                            ['review_id' => $review->id, 'cart_item_id' => $cartItem->id],
                            [
                                'menu_item_id' => $cartItem->menu_item_id,
                                'rating' => $itemData['rating'],
                                'text' => $itemData['review'] ?? null,
                                'images' => $mergedItemImages ?: null,
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
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                $this->media->delete($path);
            }

            throw $exception;
        }

        foreach ($removedPaths as $path) {
            $this->media->delete($path);
        }

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
            'anonymous' => (bool) $review->anonymous,
            'flagged' => $review->flagged,
            'created_at' => $this->dateTimes->formatDateTime($review->created_at, $review->vendor),
            'updated_at' => $this->dateTimes->formatDateTime($review->updated_at, $review->vendor),
        ];

        return $payload;
    }

    private function anonymousDisplayName(int $id): string
    {
        $adjectives = [
            'Happy', 'Clever', 'Brave', 'Gentle', 'Witty', 'Calm', 'Bright',
            'Bold', 'Kind', 'Swift', 'Merry', 'Noble', 'Keen', 'Warm', 'Fair',
            'Lucky', 'Jolly', 'Lively', 'Cheerful', 'Friendly',
        ];
        $nouns = [
            'Foodie', 'Diner', 'Gourmet', 'Taster', 'Explorer', 'Guest',
            'Patron', 'Traveler', 'Connoisseur', 'Epicure', 'Reviewer',
            'Visitor', 'Wanderer', 'Adventurer', 'Enthusiast', 'Critic',
            'Voyager', 'Pilgrim', 'Nomad', 'Discoverer',
        ];

        $hash = crc32((string) $id);
        $adj = $adjectives[abs($hash) % count($adjectives)];
        $noun = $nouns[abs($hash >> 8) % count($nouns)];

        return "$adj $noun";
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
