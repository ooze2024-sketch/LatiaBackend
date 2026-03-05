<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\DashboardController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes (temporarily disabled for development)
    // Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Products
        Route::apiResource('products', ProductController::class);
        Route::get('/products/category/{categoryId}', [ProductController::class, 'byCategory']);

        // Inventory
        Route::apiResource('inventory', InventoryController::class);
        Route::post('/inventory/{inventoryItem}/movements', [InventoryController::class, 'addMovement']);
        Route::get('/inventory/{inventoryItem}/movements', [InventoryController::class, 'movements']);

        // Sales
        Route::apiResource('sales', SaleController::class, ['only' => ['index', 'store', 'show']]);
        Route::post('/sales/{sale}/payment', [SaleController::class, 'recordPayment']);
        Route::get('/sales/date-range', [SaleController::class, 'getSalesByDateRange']);
        Route::get('/sales/daily/today', [SaleController::class, 'getDailySales']);

        // Dashboard
        Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('/dashboard/sales-trend', [DashboardController::class, 'salesTrend']);
    // });
});
