<?php

use App\Http\Controllers\Api\RestaurantPlanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Public API routes that do not belong to the customer, vendor, or admin
| surfaces are registered here. Actor-specific routes live under routes/api/:
|
|   routes/api/customer.php  → /api/customer/*
|   routes/api/vendor.php    → /api/vendor/*
|   routes/api/admin.php     → /api/admin/*
|
| They are registered in bootstrap/app.php via withRouting().
|
*/

Route::get('restaurant/plans', [RestaurantPlanController::class, 'index'])
    ->name('restaurant.plans');
