<?php

use App\Http\Controllers\Api\Vendor\AllergenController;
use App\Http\Controllers\Api\Vendor\AnalyticsController;
use App\Http\Controllers\Api\Vendor\AuthController;
use App\Http\Controllers\Api\Vendor\BillingController;
use App\Http\Controllers\Api\Vendor\DashboardController;
use App\Http\Controllers\Api\Vendor\InventoryController;
use App\Http\Controllers\Api\Vendor\MenuCategoryController;
use App\Http\Controllers\Api\Vendor\MenuController;
use App\Http\Controllers\Api\Vendor\MenuItemController;
use App\Http\Controllers\Api\Vendor\ModifierGroupController;
use App\Http\Controllers\Api\Vendor\OrderController;
use App\Http\Controllers\Api\Vendor\ReservationController;
use App\Http\Controllers\Api\Vendor\ReviewController;
use App\Http\Controllers\Api\Vendor\SeedController;
use App\Http\Controllers\Api\Vendor\SpecialTagController;
use App\Http\Controllers\Api\Vendor\StripeConnectController;
use App\Http\Controllers\Api\Vendor\TableController;
use App\Http\Controllers\Api\Vendor\TeamController;
use App\Http\Controllers\Api\Vendor\VendorSettingsController;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::post('register', [AuthController::class, 'register'])->name('register');
Route::post('login',    [AuthController::class, 'login'])->name('login');

// Public QR scan endpoints (no auth — called from customer QR landing)
Route::post('{vendorId}/tables/{tableId}/scan', [TableController::class, 'recordScan'])->name('tables.scan');
Route::post('{vendorId}/takeaway/scan',         [TableController::class, 'recordTakeawayScan'])->name('takeaway.scan');

// Authenticated vendor routes
Route::middleware('auth:vendor')->group(function () {
    Route::get('me',      [AuthController::class, 'me'])->name('me');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Menu (legacy endpoints kept for compatibility)
    Route::get('{vendorId}/menu',                    [MenuController::class, 'show'])->name('menu.show');
    Route::put('{vendorId}/menu',                    [MenuController::class, 'update'])->name('menu.update');
    Route::patch('{vendorId}/menu/items/{itemId}',   [MenuController::class, 'updateItem'])->name('menu.updateItem');

    // Menu Categories
    Route::get('menu/categories',                    [MenuCategoryController::class, 'index'])->name('menu.categories.index');
    Route::post('menu/categories',                   [MenuCategoryController::class, 'store'])->name('menu.categories.store');
    Route::patch('menu/categories/{categoryId}',     [MenuCategoryController::class, 'update'])->name('menu.categories.update');
    Route::delete('menu/categories/{categoryId}',    [MenuCategoryController::class, 'destroy'])->name('menu.categories.destroy');
    Route::get('menu/tax-categories',                [MenuCategoryController::class, 'taxCategories'])->name('menu.taxCategories');

    // Menu Items
    Route::get('menu/items',                         [MenuItemController::class, 'index'])->name('menu.items.index');
    Route::post('menu/items',                        [MenuItemController::class, 'store'])->name('menu.items.store');
    Route::get('menu/items/{itemId}',                [MenuItemController::class, 'show'])->name('menu.items.show');
    Route::patch('menu/items/{itemId}',              [MenuItemController::class, 'update'])->name('menu.items.update');
    Route::delete('menu/items/{itemId}',             [MenuItemController::class, 'destroy'])->name('menu.items.destroy');
    Route::patch('menu/items/{itemId}/toggle',       [MenuItemController::class, 'toggleAvailability'])->name('menu.items.toggle');

    // Modifier Groups
    Route::get('menu/modifier-groups',               [ModifierGroupController::class, 'index'])->name('menu.modifierGroups.index');
    Route::post('menu/modifier-groups',              [ModifierGroupController::class, 'store'])->name('menu.modifierGroups.store');
    Route::patch('menu/modifier-groups/{groupId}',   [ModifierGroupController::class, 'update'])->name('menu.modifierGroups.update');
    Route::delete('menu/modifier-groups/{groupId}',  [ModifierGroupController::class, 'destroy'])->name('menu.modifierGroups.destroy');

    // Allergens & Special Tags (read-only system lookups)
    Route::get('allergens',                          [AllergenController::class, 'index'])->name('allergens.index');
    Route::get('special-tags',                       [SpecialTagController::class, 'index'])->name('specialTags.index');

    // Inventory
    Route::get('{vendorId}/inventory/items',                  [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('{vendorId}/inventory/items',                 [InventoryController::class, 'store'])->name('inventory.store');
    Route::patch('{vendorId}/inventory/items/{itemId}',       [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('{vendorId}/inventory/items/{itemId}',      [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('{vendorId}/inventory/settings',               [InventoryController::class, 'settings'])->name('inventory.settings');
    Route::put('{vendorId}/inventory/settings',               [InventoryController::class, 'updateSettings'])->name('inventory.updateSettings');
    Route::get('{vendorId}/inventory/categories',             [InventoryController::class, 'categoriesIndex'])->name('inventory.categories.index');
    Route::post('{vendorId}/inventory/categories',            [InventoryController::class, 'categoriesStore'])->name('inventory.categories.store');
    Route::delete('{vendorId}/inventory/categories/{name}',   [InventoryController::class, 'categoriesDestroy'])->name('inventory.categories.destroy');
    Route::get('{vendorId}/inventory/units',                  [InventoryController::class, 'unitsIndex'])->name('inventory.units.index');
    Route::post('{vendorId}/inventory/units',                 [InventoryController::class, 'unitsStore'])->name('inventory.units.store');
    Route::delete('{vendorId}/inventory/units/{name}',        [InventoryController::class, 'unitsDestroy'])->name('inventory.units.destroy');

    // Orders
    Route::get('{vendorId}/orders',                               [OrderController::class, 'index'])->name('orders.index');
    Route::get('{vendorId}/orders/{orderId}',                     [OrderController::class, 'show'])->name('orders.show');
    Route::patch('orders/{orderId}',                              [OrderController::class, 'update'])->name('orders.update');
    Route::patch('orders/{orderId}/confirm',                      [OrderController::class, 'confirm'])->name('orders.confirm');
    Route::patch('orders/{orderId}/confirm-cash',                 [OrderController::class, 'confirmCashPayment'])->name('orders.confirmCash');
    Route::patch('orders/{orderId}/ready',                        [OrderController::class, 'markReady'])->name('orders.ready');
    Route::patch('orders/{orderId}/picked-up',                    [OrderController::class, 'markPickedUp'])->name('orders.pickedUp');
    Route::patch('orders/{orderId}/served',                       [OrderController::class, 'markServed'])->name('orders.served');
    Route::patch('orders/{orderId}/cancel',                       [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('{vendorId}/sessions/{sessionId}/release',        [OrderController::class, 'releaseToKitchen'])->name('sessions.release');
    Route::post('{vendorId}/sessions/{sessionId}/fire-course',    [OrderController::class, 'fireNextCourse'])->name('sessions.fireCourse');
    Route::post('{vendorId}/sessions/{sessionId}/close',          [OrderController::class, 'closeSession'])->name('sessions.close');

    // Vendor Settings
    Route::get('{vendorId}/settings',                             [VendorSettingsController::class, 'show'])->name('settings.show');
    Route::put('{vendorId}/settings',                             [VendorSettingsController::class, 'update'])->name('settings.update');
    Route::get('{vendorId}/subscription',                         [VendorSettingsController::class, 'subscription'])->name('subscription');
    Route::post('{vendorId}/legal-info',                          [VendorSettingsController::class, 'submitLegalInfo'])->name('legalInfo.submit');
    Route::get('{vendorId}/legal-info/status',                    [VendorSettingsController::class, 'getLegalChangeStatus'])->name('legalInfo.status');
    Route::post('{vendorId}/settings/logo',                       [VendorSettingsController::class, 'uploadLogo'])->name('settings.logo');
    Route::post('{vendorId}/settings/cover-photo',                [VendorSettingsController::class, 'uploadCoverPhoto'])->name('settings.coverPhoto');
    Route::get('{vendorId}/settings/export',                      [VendorSettingsController::class, 'exportData'])->name('settings.export');

    // Stripe Connect
    Route::post('{vendorId}/stripe/connect',                      [StripeConnectController::class, 'createAccount'])->name('stripe.connect');
    Route::post('{vendorId}/stripe/onboarding-link',              [StripeConnectController::class, 'createOnboardingLink'])->name('stripe.onboarding');
    Route::get('{vendorId}/stripe/status',                        [StripeConnectController::class, 'getStatus'])->name('stripe.status');

    // Reviews
    Route::get('{vendorId}/complaints',                           [ReviewController::class, 'complaints'])->name('complaints');
    Route::post('{vendorId}/reviews/{reviewId}/reply',            [ReviewController::class, 'reply'])->name('reviews.reply');
    Route::get('{vendorId}/top-customers',                        [ReviewController::class, 'topCustomers'])->name('topCustomers');

    // Reservations
    Route::get('{vendorId}/reservations',                         [ReservationController::class, 'index'])->name('reservations.index');
    Route::patch('reservations/{reservationId}/status',           [ReservationController::class, 'updateStatus'])->name('reservations.updateStatus');

    // Dashboard
    Route::get('{vendorId}/dashboard',                            [DashboardController::class, 'index'])->name('dashboard');

    // Tables / QR
    Route::get('{vendorId}/tables',                               [TableController::class, 'index'])->name('tables.index');
    Route::post('{vendorId}/tables',                              [TableController::class, 'store'])->name('tables.store');
    Route::patch('{vendorId}/tables/{tableId}',                   [TableController::class, 'update'])->name('tables.update');
    Route::delete('{vendorId}/tables/{tableId}',                  [TableController::class, 'destroy'])->name('tables.destroy');
    Route::post('{vendorId}/tables/regenerate-all',               [TableController::class, 'regenerateAll'])->name('tables.regenerateAll');
    Route::post('{vendorId}/tables/{tableId}/refresh-qr',         [TableController::class, 'refreshQR'])->name('tables.refreshQR');
    Route::get('{vendorId}/tables/takeaway-qr',                   [TableController::class, 'takeawayQR'])->name('tables.takeawayQR');
    Route::post('{vendorId}/tables/takeaway-qr/refresh',          [TableController::class, 'refreshTakeawayQR'])->name('tables.refreshTakeawayQR');
    Route::post('{vendorId}/tables/sync',                         [TableController::class, 'sync'])->name('tables.sync');

    // Team
    Route::get('{vendorId}/team',                                 [TeamController::class, 'index'])->name('team.index');
    Route::post('{vendorId}/team/invite',                         [TeamController::class, 'invite'])->name('team.invite');
    Route::patch('{vendorId}/team/{memberId}',                    [TeamController::class, 'update'])->name('team.update');
    Route::delete('{vendorId}/team/{memberId}',                   [TeamController::class, 'destroy'])->name('team.destroy');

    // Analytics
    Route::get('{vendorId}/analytics',                            [AnalyticsController::class, 'index'])->name('analytics');

    // Billing & Subscription
    Route::get('{vendorId}/billing',                              [BillingController::class, 'show'])->name('billing.show');
    Route::get('{vendorId}/billing/invoices',                     [BillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('{vendorId}/billing/invoices/{invoiceId}/download', [BillingController::class, 'downloadInvoice'])->name('billing.downloadInvoice');
    Route::get('{vendorId}/billing/usage',                        [BillingController::class, 'usage'])->name('billing.usage');
    Route::post('{vendorId}/billing/upgrade',                     [BillingController::class, 'upgradePlan'])->name('billing.upgrade');
    Route::patch('{vendorId}/billing/cycle',                      [BillingController::class, 'changeCycle'])->name('billing.cycle');
    Route::post('{vendorId}/billing/payment-method',              [BillingController::class, 'updatePaymentMethod'])->name('billing.paymentMethod');
    Route::post('{vendorId}/billing/cancel',                      [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('{vendorId}/billing/portal',                      [BillingController::class, 'portalSession'])->name('billing.portal');

    // Seed demo data
    Route::post('seed', [SeedController::class, 'seed'])->name('seed');
});
