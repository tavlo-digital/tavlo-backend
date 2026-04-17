<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\FavoriteController;
use App\Http\Controllers\Api\Customer\LoyaltyController;
use App\Http\Controllers\Api\Customer\OrderHistoryController;
use App\Http\Controllers\Api\Customer\PrivacyController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\ReservationController;
use App\Http\Controllers\Api\Customer\RestaurantController;
use App\Http\Controllers\Api\Customer\ReviewController;
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
Route::post('login',           [AuthController::class, 'login'])->name('login');
Route::post('social/register', [AuthController::class, 'socialRegister'])->name('social.register');
Route::post('social/login',    [AuthController::class, 'socialLogin'])->name('social.login');

// Public browsing (no auth required)
Route::get('categories', [RestaurantController::class, 'allCategories'])->name('categories');

Route::prefix('restaurants')->name('restaurants.')->group(function () {
    Route::get('/',                                  [RestaurantController::class, 'index'])->name('index');
    Route::get('{vendorPublicId}',                   [RestaurantController::class, 'show'])->name('show');
    Route::get('{vendorPublicId}/categories',        [RestaurantController::class, 'categories'])->name('categories');
    Route::get('{vendorPublicId}/menu',              [RestaurantController::class, 'menu'])->name('menu');
    Route::get('{vendorPublicId}/menu/{itemId}',     [RestaurantController::class, 'menuItem'])->name('menu.item');
    Route::get('{vendorPublicId}/tables',            [RestaurantController::class, 'tables'])->name('tables');
});

// Authenticated customer routes
Route::middleware('auth:customer')->group(function () {
    Route::get('me',           [AuthController::class, 'me'])->name('me');
    Route::post('logout',      [AuthController::class, 'logout'])->name('logout');
    Route::post('logout-all',  [AuthController::class, 'logoutAll'])->name('logout.all');

    // Profile
    Route::get('profile',              [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('profile',            [ProfileController::class, 'update'])->name('profile.update');
    Route::post('profile/password',    [ProfileController::class, 'changePassword'])->name('profile.password');

    // Order History
    Route::get('orders/restaurants',                      [OrderHistoryController::class, 'restaurants'])->name('orders.restaurants');
    Route::get('orders/restaurants/{vendorPublicId}',     [OrderHistoryController::class, 'vendorOrders'])->name('orders.vendor');
    Route::get('orders/{orderPublicId}',                  [OrderHistoryController::class, 'show'])->name('orders.show');

    // Reservations
    Route::get('reservations',                              [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations',                             [ReservationController::class, 'store'])->name('reservations.store');
    Route::get('reservations/{reservationPublicId}',        [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('reservations/{reservationPublicId}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

    // Loyalty Points
    Route::get('loyalty',                     [LoyaltyController::class, 'index'])->name('loyalty.index');
    Route::get('loyalty/{vendorPublicId}',    [LoyaltyController::class, 'show'])->name('loyalty.show');

    // Favorites
    Route::get('favorites',                          [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('favorites',                         [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('favorites/{vendorPublicId}',      [FavoriteController::class, 'destroy'])->name('favorites.destroy');

    // Reviews
    Route::get('reviews',                       [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('reviews',                      [ReviewController::class, 'store'])->name('reviews.store');
    Route::patch('reviews/{reviewPublicId}',    [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{reviewPublicId}',   [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Privacy & Data
    Route::post('privacy/export',       [PrivacyController::class, 'requestDataExport'])->name('privacy.export');
    Route::post('privacy/delete',       [PrivacyController::class, 'requestAccountDeletion'])->name('privacy.delete');
    Route::get('privacy/requests',      [PrivacyController::class, 'gdprRequests'])->name('privacy.requests');
});
