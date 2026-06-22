<?php

use App\Http\Controllers\Api\Admin\AllergenController;
use App\Http\Controllers\Api\Admin\SpecialTagController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::apiResource('allergens', AllergenController::class);
    Route::apiResource('special-tags', SpecialTagController::class);
});
