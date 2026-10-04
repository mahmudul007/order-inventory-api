<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->middleware('throttle:api')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:login')->name('auth.register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders', [OrderController::class, 'store'])
            ->middleware(['throttle:orders', IdempotencyMiddleware::class])
            ->name('orders.store');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::middleware('admin')->group(function () {
            Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            Route::get('inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');
            Route::get('products/{product}/inventory', [InventoryController::class, 'show'])->name('inventory.show');
            Route::post('products/{product}/inventory/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

            Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
            Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
            Route::get('customers/{customer}/orders', [CustomerController::class, 'orders'])->name('customers.orders');

            Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
            Route::get('reports/top-products', [ReportController::class, 'topProducts'])->name('reports.top-products');
        });
    });
});
