<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\FavoriteController;
use App\Http\Controllers\Api\Customer\LoyaltyController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Customer\OrderHistoryController;
use App\Http\Controllers\Api\Customer\PaymentController;
use App\Http\Controllers\Api\Customer\PrivacyController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\ReservationController;
use App\Http\Controllers\Api\Customer\RestaurantController;
use App\Http\Controllers\Api\Customer\ReviewController;
use App\Http\Controllers\Api\Customer\TableScanController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Health & diagnostics (public)
Route::get('ping', fn () => response()->json(['message' => 'pong']))->name('ping');

Route::get('health', function () {
    try {
        DB::connection()->getPdo();
        $db = true;
    } catch (\Throwable) {
        $db = false;
    }

    $status = $db ? 'healthy' : 'degraded';

    return response()->json([
        'status' => $status,
        'database' => $db,
        'timestamp' => now()->toIso8601String(),
    ], $db ? 200 : 503);
})->name('health');

// Auth (public)
Route::post('register',        [AuthController::class, 'register'])->name('register');
Route::post('guest',           [AuthController::class, 'loginAsGuest'])->name('guest');
Route::post('login',           [AuthController::class, 'login'])->name('login');
Route::post('social/register', [AuthController::class, 'socialRegister'])->name('social.register');
Route::post('social/login',    [AuthController::class, 'socialLogin'])->name('social.login');

// Public browsing (no auth required)
Route::get('categories', [RestaurantController::class, 'allCategories'])->name('categories');

// Stripe webhooks are authenticated by Stripe signature, not customer bearer auth.
Route::post('payments/webhook', [PaymentController::class, 'webhook'])->name('payments.webhook');
Route::get('payment-methods', [PaymentController::class, 'methods'])->name('payment-methods');

Route::prefix('restaurants')->name('restaurants.')->group(function () {
    Route::get('/',                                  [RestaurantController::class, 'index'])->name('index');
    Route::get('{vendorPublicId}',                   [RestaurantController::class, 'show'])->name('show');
    Route::get('{vendorPublicId}/categories',        [RestaurantController::class, 'categories'])->name('categories');
    Route::get('{vendorPublicId}/menu',              [RestaurantController::class, 'menu'])->name('menu');
    Route::get('{vendorPublicId}/menu/{itemId}',     [RestaurantController::class, 'menuItem'])->name('menu.item');
    Route::get('{vendorPublicId}/tables',            [RestaurantController::class, 'tables'])->name('tables');
    Route::get('{vendorPublicId}/reviews',           [RestaurantController::class, 'reviews'])->name('reviews');
    Route::get('{vendorPublicId}/about',             [RestaurantController::class, 'about'])->name('about');
    Route::get('{vendorPublicId}/languages',         [RestaurantController::class, 'languages'])->name('languages');
});

// Public table QR status lookup
Route::get('table/status', [TableScanController::class, 'status'])->name('table.status');

// Authenticated customer routes
Route::middleware(['auth:customer', 'track.session.activity'])->group(function () {
    Route::get('me',           [AuthController::class, 'me'])->name('me');
    Route::post('logout',      [AuthController::class, 'logout'])->name('logout');
    Route::post('logout-all',  [AuthController::class, 'logoutAll'])->name('logout.all');

    // Profile
    Route::get('profile',              [ProfileController::class, 'show'])->name('profile.show');
    Route::match(['patch', 'put'], 'profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/change-phone', [ProfileController::class, 'changePhone'])->name('profile.phone');
    Route::match(['put', 'post'], 'profile/change-email', [ProfileController::class, 'changeEmail'])->name('profile.email');
    Route::post('profile/password',    [ProfileController::class, 'changePassword'])->name('profile.password');

    // Table session flow
    Route::post('table/scan', [TableScanController::class, 'scan'])->name('table.scan');
    Route::post('table/pin', [TableScanController::class, 'pin'])->name('table.pin');
    Route::post('table/close', [TableScanController::class, 'close'])->name('table.close');
    Route::post('table/call', [TableScanController::class, 'call'])->name('table.call');
    Route::get('table/order/start',      [CartController::class, 'orderStart'])->name('table.order.start');
    Route::post('table/order/draft',     [CartController::class, 'createOrderDraft'])->name('table.order.draft');
    Route::put('table/order/update/{order_id}', [CartController::class, 'updateOrder'])->name('table.order.update');
    Route::post('table/order/confirmed', [CartController::class, 'createOrderConfirmed'])->name('table.order.confirmed');
    Route::get('table/history',          [CartController::class, 'tableHistory'])->name('table.history');

    // Table cart
    Route::prefix('cart')->name('cart.')->group(function () {
        Route::get('/',             [CartController::class, 'index'])->name('index');
        Route::post('items',        [CartController::class, 'addItem'])->name('items.add');
        Route::patch('items/{id}',  [CartController::class, 'updateItem'])->name('items.update');
        Route::delete('items/{id}', [CartController::class, 'removeItem'])->name('items.remove');
    });

    // Order History
    Route::get('orders/history',                          [OrderHistoryController::class, 'history'])->name('orders.history');
    Route::get('orders/restaurants',                      [OrderHistoryController::class, 'restaurants'])->name('orders.restaurants');
    Route::get('orders/restaurants/{vendorPublicId}',     [OrderHistoryController::class, 'vendorOrders'])->name('orders.vendor');
    Route::get('orders/{orderPublicId}/tracking',         [OrderHistoryController::class, 'tracking'])->name('orders.tracking');
    Route::get('orders/{orderPublicId}/receipt',          [OrderHistoryController::class, 'receipt'])->name('orders.receipt');
    Route::get('orders/{orderPublicId}',                  [OrderHistoryController::class, 'show'])->name('orders.show');

    // Stripe Elements Payments
    Route::post('payments/create-intent', [PaymentController::class, 'createIntent'])->name('payments.create-intent');
    Route::post('payments/update-intent', [PaymentController::class, 'updateIntent'])->name('payments.update-intent');
    Route::get('payments/verify', [PaymentController::class, 'verify'])->name('payments.verify');

    // Reservations
    Route::get('reservations',                              [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations',                             [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('reservations/{reservationPublicId}',        [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('reservations/{reservationPublicId}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

    // Loyalty Points
    Route::get('loyalty',                     [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::get('loyalty/{vendorPublicId}',    [LoyaltyController::class, 'show'])->name('loyalty.show');

    // Favorites
    Route::get('favorites',                                  [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('favorites/{vendorPublicId}/add',            [FavoriteController::class, 'store'])->name('favorites.add');
    Route::delete('favorites/{vendorPublicId}/delete',       [FavoriteController::class, 'destroy'])->name('favorites.delete');

    // Reviews
    Route::get('reviews',                       [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews',                      [ReviewController::class, 'store'])->name('reviews.store');
    Route::patch('reviews/{reviewPublicId}',    [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{reviewPublicId}',   [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Notifications
    Route::get('notifications',                      [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{id}/read',          [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all',            [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Privacy & Data
    Route::post('privacy/export',       [PrivacyController::class, 'requestDataExport'])->name('privacy.export');
    Route::post('privacy/delete',       [PrivacyController::class, 'requestAccountDeletion'])->name('privacy.delete');
    Route::get('privacy/requests',      [PrivacyController::class, 'gdprRequests'])->name('privacy.requests');
});
