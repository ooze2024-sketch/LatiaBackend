<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\IngredientsController;
use Illuminate\Support\Facades\Cache;

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
        Route::get('/products/{product}/image', [ProductController::class, 'image']);
        Route::post('/products/{product}/upload-image', [ProductController::class, 'uploadImage']);

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

        // Sync version (used by cashier/admin clients to detect catalog changes)
        Route::get('/sync/version', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'catalog_version' => (int) Cache::rememberForever('catalog:version', fn () => 1),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        });
        Route::get('/catalog/version', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'catalog_version' => (int) Cache::rememberForever('catalog:version', fn () => 1),
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);
        });

        // Ingredients/Product Links
        Route::post('/products/ingredients/batch', [IngredientsController::class, 'getMultipleProductsIngredients']);
        Route::get('/products/{product}/ingredients', [IngredientsController::class, 'getProductIngredients']);
        Route::post('/products/{product}/ingredients', [IngredientsController::class, 'linkIngredientsToProduct']);
        Route::delete('/product-ingredients/{productIngredient}', [IngredientsController::class, 'deleteIngredientLink']);
        Route::put('/product-ingredients/{productIngredient}', [IngredientsController::class, 'updateIngredientQuantity']);
    // });
});
