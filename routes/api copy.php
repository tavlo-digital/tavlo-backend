<?php

use App\Http\Controllers\Api\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Api\Customer\RestaurantController as CustomerRestaurantController;
use App\Http\Controllers\Api\Customer\OrderHistoryController as CustomerOrderHistoryController;
use App\Http\Controllers\Api\Customer\ReservationController as CustomerReservationController;
use App\Http\Controllers\Api\Customer\LoyaltyController as CustomerLoyaltyController;
use App\Http\Controllers\Api\Customer\FavoriteController as CustomerFavoriteController;
use App\Http\Controllers\Api\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Api\Customer\PrivacyController as CustomerPrivacyController;
use App\Http\Controllers\Api\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Api\Vendor\MenuController;
use App\Http\Controllers\Api\Vendor\MenuCategoryController;
use App\Http\Controllers\Api\Vendor\MenuItemController;
use App\Http\Controllers\Api\Vendor\ModifierGroupController;
use App\Http\Controllers\Api\Vendor\AllergenController;
use App\Http\Controllers\Api\Vendor\SpecialTagController;
use App\Http\Controllers\Api\Vendor\InventoryController;
use App\Http\Controllers\Api\Vendor\OrderController;
use App\Http\Controllers\Api\Vendor\VendorSettingsController;
use App\Http\Controllers\Api\Vendor\StripeConnectController;
use App\Http\Controllers\Api\Vendor\ReviewController;
use App\Http\Controllers\Api\Vendor\ReservationController;
use App\Http\Controllers\Api\Vendor\DashboardController;
use App\Http\Controllers\Api\Vendor\TableController;
use App\Http\Controllers\Api\Vendor\TeamController;
use App\Http\Controllers\Api\Vendor\AnalyticsController;
use App\Http\Controllers\Api\Vendor\SeedController;
use App\Http\Controllers\Api\Vendor\BillingController;
use Illuminate\Support\Facades\Route;

// ----------------------------------------------------------------
// Customer API
// ----------------------------------------------------------------
Route::prefix('customer')->name('customer.')->group(function () {
    // Auth (public)
    Route::post('register',        [CustomerAuthController::class, 'register'])->name('register');
    Route::post('login',           [CustomerAuthController::class, 'login'])->name('login');
    Route::post('social/register', [CustomerAuthController::class, 'socialRegister'])->name('social.register');
    Route::post('social/login',    [CustomerAuthController::class, 'socialLogin'])->name('social.login');

    // Public restaurant browsing (no auth required)
    Route::prefix('restaurants')->name('restaurants.')->group(function () {
        Route::get('/',                                  [CustomerRestaurantController::class, 'index'])->name('index');
        Route::get('{vendorPublicId}',                   [CustomerRestaurantController::class, 'show'])->name('show');
        Route::get('{vendorPublicId}/categories',        [CustomerRestaurantController::class, 'categories'])->name('categories');
        Route::get('{vendorPublicId}/menu',              [CustomerRestaurantController::class, 'menu'])->name('menu');
        Route::get('{vendorPublicId}/menu/{itemId}',     [CustomerRestaurantController::class, 'menuItem'])->name('menu.item');
        Route::get('{vendorPublicId}/tables',            [CustomerRestaurantController::class, 'tables'])->name('tables');
    });

    // Authenticated customer routes
    Route::middleware('auth:customer')->group(function () {
        Route::get('me',           [CustomerAuthController::class, 'me'])->name('me');
        Route::post('logout',      [CustomerAuthController::class, 'logout'])->name('logout');
        Route::post('logout-all',  [CustomerAuthController::class, 'logoutAll'])->name('logout.all');

        // Profile
        Route::get('profile',              [CustomerProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile',            [CustomerProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/password',    [CustomerProfileController::class, 'changePassword'])->name('profile.password');

        // Order History
        Route::get('orders/restaurants',              [CustomerOrderHistoryController::class, 'restaurants'])->name('orders.restaurants');
        Route::get('orders/restaurants/{vendorPublicId}', [CustomerOrderHistoryController::class, 'vendorOrders'])->name('orders.vendor');
        Route::get('orders/{orderPublicId}',          [CustomerOrderHistoryController::class, 'show'])->name('orders.show');

        // Reservations
        Route::get('reservations',                              [CustomerReservationController::class, 'index'])->name('reservations.index');
        Route::post('reservations',                             [CustomerReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservations/{reservationPublicId}',        [CustomerReservationController::class, 'show'])->name('reservations.show');
        Route::post('reservations/{reservationPublicId}/cancel', [CustomerReservationController::class, 'cancel'])->name('reservations.cancel');

        // Loyalty Points
        Route::get('loyalty',                     [CustomerLoyaltyController::class, 'index'])->name('loyalty.index');
        Route::get('loyalty/{vendorPublicId}',    [CustomerLoyaltyController::class, 'show'])->name('loyalty.show');

        // Favorites
        Route::get('favorites',                          [CustomerFavoriteController::class, 'index'])->name('favorites.index');
        Route::post('favorites',                         [CustomerFavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('favorites/{vendorPublicId}',      [CustomerFavoriteController::class, 'destroy'])->name('favorites.destroy');

        // Reviews
        Route::get('reviews',                       [CustomerReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews',                      [CustomerReviewController::class, 'store'])->name('reviews.store');
        Route::patch('reviews/{reviewPublicId}',    [CustomerReviewController::class, 'update'])->name('reviews.update');
        Route::delete('reviews/{reviewPublicId}',   [CustomerReviewController::class, 'destroy'])->name('reviews.destroy');

        // Privacy & Data
        Route::post('privacy/export',       [CustomerPrivacyController::class, 'requestDataExport'])->name('privacy.export');
        Route::post('privacy/delete',       [CustomerPrivacyController::class, 'requestAccountDeletion'])->name('privacy.delete');
        Route::get('privacy/requests',      [CustomerPrivacyController::class, 'gdprRequests'])->name('privacy.requests');
    });
});

// Vendor auth
Route::prefix('vendor')->name('vendor.')->group(function () {
    Route::post('register', [VendorAuthController::class, 'register'])->name('register');
    Route::post('login', [VendorAuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [VendorAuthController::class, 'me'])->name('me');
        Route::post('logout', [VendorAuthController::class, 'logout'])->name('logout');
    });
});

// ----------------------------------------------------------------
// Public QR scan endpoints (no auth — called from customer QR landing)
// ----------------------------------------------------------------
Route::post('vendor/{vendorId}/tables/{tableId}/scan', [TableController::class, 'recordScan']);
Route::post('vendor/{vendorId}/takeaway/scan',         [TableController::class, 'recordTakeawayScan']);

// ----------------------------------------------------------------
// Vendor API (authenticated)
// ----------------------------------------------------------------
Route::middleware('auth:vendor')->group(function () {
    // Menu (legacy endpoints kept for compatibility)
    Route::get('restaurants/{vendorId}/menu', [MenuController::class, 'show']);
    Route::put('restaurants/{vendorId}/menu', [MenuController::class, 'update']);
    Route::patch('vendor/{vendorId}/menu/items/{itemId}', [MenuController::class, 'updateItem']);

    // Menu Categories (new RESTful)
    Route::get('vendor/menu/categories', [MenuCategoryController::class, 'index']);
    Route::post('vendor/menu/categories', [MenuCategoryController::class, 'store']);
    Route::patch('vendor/menu/categories/{categoryId}', [MenuCategoryController::class, 'update']);
    Route::delete('vendor/menu/categories/{categoryId}', [MenuCategoryController::class, 'destroy']);
    Route::get('vendor/menu/tax-categories',             [MenuCategoryController::class, 'taxCategories']);

    // Menu Items (RESTful)
    Route::get('vendor/menu/items',                      [MenuItemController::class, 'index']);
    Route::post('vendor/menu/items',                     [MenuItemController::class, 'store']);
    Route::get('vendor/menu/items/{itemId}',             [MenuItemController::class, 'show']);
    Route::patch('vendor/menu/items/{itemId}',           [MenuItemController::class, 'update']);
    Route::delete('vendor/menu/items/{itemId}',          [MenuItemController::class, 'destroy']);
    Route::patch('vendor/menu/items/{itemId}/toggle',    [MenuItemController::class, 'toggleAvailability']);

    // Modifier Groups
    Route::get('vendor/menu/modifier-groups',            [ModifierGroupController::class, 'index']);
    Route::post('vendor/menu/modifier-groups',           [ModifierGroupController::class, 'store']);
    Route::patch('vendor/menu/modifier-groups/{groupId}',[ModifierGroupController::class, 'update']);
    Route::delete('vendor/menu/modifier-groups/{groupId}',[ModifierGroupController::class, 'destroy']);

    // Allergens & Special Tags (read-only system lookups)
    Route::get('vendor/allergens',                       [AllergenController::class, 'index']);
    Route::get('vendor/special-tags',                    [SpecialTagController::class, 'index']);

    // Inventory
    Route::get('vendor/{vendorId}/inventory/items',      [InventoryController::class, 'index']);
    Route::post('vendor/{vendorId}/inventory/items',     [InventoryController::class, 'store']);
    Route::patch('vendor/{vendorId}/inventory/items/{itemId}', [InventoryController::class, 'update']);
    Route::delete('vendor/{vendorId}/inventory/items/{itemId}', [InventoryController::class, 'destroy']);
    Route::get('vendor/{vendorId}/inventory/settings',   [InventoryController::class, 'settings']);
    Route::put('vendor/{vendorId}/inventory/settings',   [InventoryController::class, 'updateSettings']);

    // Orders
    Route::get('vendor/{vendorId}/orders',                               [OrderController::class, 'index']);
    Route::get('vendor/{vendorId}/orders/{orderId}',                     [OrderController::class, 'show']);
    Route::patch('orders/{orderId}',                                     [OrderController::class, 'update']);
    Route::patch('orders/{orderId}/confirm',                             [OrderController::class, 'confirm']);
    Route::patch('orders/{orderId}/confirm-cash',                        [OrderController::class, 'confirmCashPayment']);
    Route::patch('orders/{orderId}/ready',                               [OrderController::class, 'markReady']);
    Route::patch('orders/{orderId}/picked-up',                           [OrderController::class, 'markPickedUp']);
    Route::patch('orders/{orderId}/served',                              [OrderController::class, 'markServed']);
    Route::patch('orders/{orderId}/cancel',                              [OrderController::class, 'cancel']);
    Route::post('vendor/{vendorId}/sessions/{sessionId}/release',        [OrderController::class, 'releaseToKitchen']);
    Route::post('vendor/{vendorId}/sessions/{sessionId}/fire-course',    [OrderController::class, 'fireNextCourse']);
    Route::post('vendor/{vendorId}/sessions/{sessionId}/close',          [OrderController::class, 'closeSession']);

    // Vendor Settings
    Route::get('vendor/{vendorId}/settings',                  [VendorSettingsController::class, 'show']);
    Route::put('vendor/{vendorId}/settings',                  [VendorSettingsController::class, 'update']);
    Route::get('vendor/{vendorId}/subscription',              [VendorSettingsController::class, 'subscription']);
    Route::post('vendor/{vendorId}/legal-info',               [VendorSettingsController::class, 'submitLegalInfo']);
    Route::get('vendor/{vendorId}/legal-info/status',         [VendorSettingsController::class, 'getLegalChangeStatus']);
    Route::post('vendor/{vendorId}/settings/logo',            [VendorSettingsController::class, 'uploadLogo']);
    Route::post('vendor/{vendorId}/settings/cover-photo',     [VendorSettingsController::class, 'uploadCoverPhoto']);
    Route::get('vendor/{vendorId}/settings/export',           [VendorSettingsController::class, 'exportData']);

    // Stripe Connect
    Route::post('vendor/{vendorId}/stripe/connect',           [StripeConnectController::class, 'createAccount']);
    Route::post('vendor/{vendorId}/stripe/onboarding-link',   [StripeConnectController::class, 'createOnboardingLink']);
    Route::get('vendor/{vendorId}/stripe/status',             [StripeConnectController::class, 'getStatus']);

    // Reviews
    Route::get('vendor/{vendorId}/complaints',           [ReviewController::class, 'complaints']);
    Route::post('vendor/{vendorId}/reviews/{reviewId}/reply', [ReviewController::class, 'reply']);
    Route::get('vendor/{vendorId}/top-customers',        [ReviewController::class, 'topCustomers']);

    // Reservations
    Route::get('vendor/{vendorId}/reservations',         [ReservationController::class, 'index']);
    Route::patch('reservations/{reservationId}/status',  [ReservationController::class, 'updateStatus']);

    // Dashboard
    Route::get('vendor/{vendorId}/dashboard',            [DashboardController::class, 'index']);

    // Tables / QR
    Route::get('vendor/{vendorId}/tables',                            [TableController::class, 'index']);
    Route::post('vendor/{vendorId}/tables',                           [TableController::class, 'store']);
    Route::patch('vendor/{vendorId}/tables/{tableId}',                [TableController::class, 'update']);
    Route::delete('vendor/{vendorId}/tables/{tableId}',               [TableController::class, 'destroy']);
    Route::post('vendor/{vendorId}/tables/regenerate-all',            [TableController::class, 'regenerateAll']);
    Route::post('vendor/{vendorId}/tables/{tableId}/refresh-qr',      [TableController::class, 'refreshQR']);
    Route::get('vendor/{vendorId}/tables/takeaway-qr',                [TableController::class, 'takeawayQR']);
    Route::post('vendor/{vendorId}/tables/takeaway-qr/refresh',       [TableController::class, 'refreshTakeawayQR']);
    Route::post('vendor/{vendorId}/tables/sync',                      [TableController::class, 'sync']);

    // Team
    Route::get('vendor/{vendorId}/team',                 [TeamController::class, 'index']);
    Route::post('vendor/{vendorId}/team/invite',         [TeamController::class, 'invite']);
    Route::patch('vendor/{vendorId}/team/{memberId}',    [TeamController::class, 'update']);
    Route::delete('vendor/{vendorId}/team/{memberId}',   [TeamController::class, 'destroy']);

    // Analytics
    Route::get('vendor/{vendorId}/analytics',            [AnalyticsController::class, 'index']);

    // Billing & Subscription
    Route::get('vendor/{vendorId}/billing',                                   [BillingController::class, 'show']);
    Route::get('vendor/{vendorId}/billing/invoices',                          [BillingController::class, 'invoices']);
    Route::get('vendor/{vendorId}/billing/invoices/{invoiceId}/download',     [BillingController::class, 'downloadInvoice']);
    Route::get('vendor/{vendorId}/billing/usage',                             [BillingController::class, 'usage']);
    Route::post('vendor/{vendorId}/billing/upgrade',                          [BillingController::class, 'upgradePlan']);
    Route::patch('vendor/{vendorId}/billing/cycle',                           [BillingController::class, 'changeCycle']);
    Route::post('vendor/{vendorId}/billing/payment-method',                   [BillingController::class, 'updatePaymentMethod']);
    Route::post('vendor/{vendorId}/billing/cancel',                           [BillingController::class, 'cancel']);
    Route::post('vendor/{vendorId}/billing/portal',                           [BillingController::class, 'portalSession']);

    // Seed demo data
    Route::post('seed',                                  [SeedController::class, 'seed']);
});
