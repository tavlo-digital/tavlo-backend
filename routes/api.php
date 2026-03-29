<?php

use App\Http\Controllers\Api\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\Vendor\AuthController as VendorAuthController;
use App\Http\Controllers\Api\Vendor\MenuController;
use App\Http\Controllers\Api\Vendor\MenuCategoryController;
use App\Http\Controllers\Api\Vendor\MenuItemController;
use App\Http\Controllers\Api\Vendor\AllergenController;
use App\Http\Controllers\Api\Vendor\SpecialTagController;
use App\Http\Controllers\Api\Vendor\InventoryController;
use App\Http\Controllers\Api\Vendor\OrderController;
use App\Http\Controllers\Api\Vendor\VendorSettingsController;
use App\Http\Controllers\Api\Vendor\ReviewController;
use App\Http\Controllers\Api\Vendor\ReservationController;
use Illuminate\Support\Facades\Route;

// Customer auth
Route::prefix('customer')->name('customer.')->group(function () {
    Route::post('register', [CustomerAuthController::class, 'register'])->name('register');
    Route::post('login', [CustomerAuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [CustomerAuthController::class, 'me'])->name('me');
        Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');
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

    // Menu Items (new RESTful)
    Route::get('vendor/menu/items', [MenuItemController::class, 'index']);
    Route::post('vendor/menu/items', [MenuItemController::class, 'store']);
    Route::get('vendor/menu/items/{itemId}', [MenuItemController::class, 'show']);
    Route::patch('vendor/menu/items/{itemId}', [MenuItemController::class, 'update']);
    Route::delete('vendor/menu/items/{itemId}', [MenuItemController::class, 'destroy']);
    Route::patch('vendor/menu/items/{itemId}/toggle', [MenuItemController::class, 'toggleAvailability']);

    // Allergens & Special Tags (read-only lookups)
    Route::get('vendor/allergens', [AllergenController::class, 'index']);
    Route::get('vendor/special-tags', [SpecialTagController::class, 'index']);

    // Inventory
    Route::get('vendor/{vendorId}/inventory/items', [InventoryController::class, 'index']);
    Route::post('vendor/{vendorId}/inventory/items', [InventoryController::class, 'store']);
    Route::patch('vendor/{vendorId}/inventory/items/{itemId}', [InventoryController::class, 'update']);
    Route::delete('vendor/{vendorId}/inventory/items/{itemId}', [InventoryController::class, 'destroy']);
    Route::get('vendor/{vendorId}/inventory/settings', [InventoryController::class, 'settings']);
    Route::put('vendor/{vendorId}/inventory/settings', [InventoryController::class, 'updateSettings']);

    // Orders
    Route::get('vendor/{vendorId}/orders', [OrderController::class, 'index']);
    Route::patch('orders/{orderId}', [OrderController::class, 'update']);
    Route::patch('orders/{orderId}/ready', [OrderController::class, 'markReady']);
    Route::patch('orders/{orderId}/picked-up', [OrderController::class, 'markPickedUp']);

    // Vendor settings
    Route::get('vendor/{vendorId}/settings', [VendorSettingsController::class, 'show']);
    Route::put('vendor/{vendorId}/settings', [VendorSettingsController::class, 'update']);
    Route::get('vendor/{vendorId}/subscription', [VendorSettingsController::class, 'subscription']);
    Route::post('vendor/{vendorId}/legal-info', [VendorSettingsController::class, 'submitLegalInfo']);

    // Reviews & analytics
    Route::get('vendor/{vendorId}/complaints', [ReviewController::class, 'complaints']);
    Route::post('vendor/{vendorId}/reviews/{reviewId}/reply', [ReviewController::class, 'reply']);
    Route::get('vendor/{vendorId}/top-customers', [ReviewController::class, 'topCustomers']);

    // Reservations
    Route::get('vendor/{vendorId}/reservations', [ReservationController::class, 'index']);
    Route::patch('reservations/{reservationId}/status', [ReservationController::class, 'updateStatus']);
});
