<?php

use App\Http\Controllers\Api\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\Vendor\AuthController as VendorAuthController;
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
