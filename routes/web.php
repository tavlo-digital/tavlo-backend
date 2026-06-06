<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DiagnosticsController;
use App\Http\Controllers\Admin\MenuCategoryController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\PublicMediaController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::get('media/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.public.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('menu-categories', [MenuCategoryController::class, 'index'])->name('menu-categories.index');
    Route::post('menu-categories', [MenuCategoryController::class, 'store'])->name('menu-categories.store');
    Route::put('menu-categories/{category}', [MenuCategoryController::class, 'update'])->name('menu-categories.update');
    Route::delete('menu-categories/{category}', [MenuCategoryController::class, 'destroy'])->name('menu-categories.destroy');
    Route::get('customer/{customer}/{tab}', [CustomerController::class, 'show'])
        ->name('customer.show')
        ->where('tab', 'overview|orders|refunds|reviews|activity|gdpr');
    Route::redirect('customer/{customer}', '/admin/customer/{customer}/overview');

    // Vendor profile tabs
    Route::get('vendor/{vendor}/{tab}', [VendorController::class, 'show'])
        ->name('vendor.show')
        ->where('tab', 'overview|pending-changes|payments|subscription|orders|reviews|activity');
    Route::redirect('vendor/{vendor}', '/admin/vendor/{vendor}/overview');
    Route::post('vendor/{vendor}/changes/{change}/approve', [VendorController::class, 'approveChange'])->name('vendor.changes.approve');
    Route::post('vendor/{vendor}/changes/{change}/decline', [VendorController::class, 'declineChange'])->name('vendor.changes.decline');
    // Subscription management
    Route::redirect('subscriptions', '/admin/subscriptions/plans')->name('subscriptions.index');
    Route::get('subscriptions/plans', [SubscriptionPlanController::class, 'index'])->name('subscriptions.plans')->defaults('tab', 'plans');
    Route::get('subscriptions/active', [SubscriptionPlanController::class, 'index'])->name('subscriptions.active')->defaults('tab', 'active');
    Route::get('subscriptions/overdue', [SubscriptionPlanController::class, 'index'])->name('subscriptions.overdue')->defaults('tab', 'overdue');
    Route::post('subscriptions/plans', [SubscriptionPlanController::class, 'store'])->name('subscriptions.plans.store');
    Route::put('subscriptions/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('subscriptions.plans.update');
    Route::delete('subscriptions/plans/{plan}', [SubscriptionPlanController::class, 'destroy'])->name('subscriptions.plans.destroy');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::patch('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Diagnostics
    Route::get('diagnostics', [DiagnosticsController::class, 'index'])->name('diagnostics.index');
    Route::get('diagnostics/webhook-logs', [DiagnosticsController::class, 'webhookLogs'])->name('diagnostics.webhook-logs');
    Route::get('diagnostics/application-logs', [DiagnosticsController::class, 'applicationLogs'])->name('diagnostics.application-logs');
    Route::post('diagnostics/reconcile', [DiagnosticsController::class, 'reconcile'])->name('diagnostics.reconcile');
    Route::post('diagnostics/test-notification', [DiagnosticsController::class, 'sendTestNotification'])->name('diagnostics.test-notification');
});

require __DIR__.'/settings.php';
