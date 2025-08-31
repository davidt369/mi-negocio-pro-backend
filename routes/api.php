<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\BusinessIntelligenceController;
use App\Http\Controllers\Api\CategoriesController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\SaleItemsController;
use App\Http\Controllers\Api\PurchasesController;
use App\Http\Controllers\Api\PurchaseItemsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes (public)
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logoutAll');
    Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');

    // User routes
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('users.index');
        Route::post('/', [UserController::class, 'store'])->name('users.store');
        Route::get('/profile', [UserController::class, 'profile'])->name('users.profile');
        Route::put('/profile', [UserController::class, 'updateProfile'])->name('users.updateProfile');
        Route::get('/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');
    });

    // Categories routes
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoriesController::class, 'index'])->name('categories.index');
        Route::post('/', [CategoriesController::class, 'store'])->name('categories.store');
        Route::get('/{category}', [CategoriesController::class, 'show'])->name('categories.show');
        Route::put('/{category}', [CategoriesController::class, 'update'])->name('categories.update');
        Route::delete('/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');
        Route::patch('/{category}/toggle-status', [CategoriesController::class, 'toggleStatus'])->name('categories.toggleStatus');
    });

    // Products routes
    Route::prefix('products')->group(function () {
        Route::get('/', [ProductsController::class, 'index'])->name('products.index');
        Route::post('/', [ProductsController::class, 'store'])->name('products.store');
        Route::get('/{product}', [ProductsController::class, 'show'])->name('products.show');
        Route::put('/{product}', [ProductsController::class, 'update'])->name('products.update');
        Route::delete('/{product}', [ProductsController::class, 'destroy'])->name('products.destroy');
        Route::patch('/{product}/toggle-status', [ProductsController::class, 'toggleStatus'])->name('products.toggleStatus');
        Route::post('/{product}/adjust-stock', [ProductsController::class, 'adjustStock'])->name('products.adjustStock');
        Route::delete('/{product}/image', [ProductsController::class, 'deleteImage'])->name('products.deleteImage');
    });

    // Sales routes
    Route::prefix('sales')->group(function () {
        Route::get('/stats', [SalesController::class, 'stats'])->name('sales.stats');
        Route::get('/', [SalesController::class, 'index'])->name('sales.index');
        Route::post('/', [SalesController::class, 'store'])->name('sales.store');
        Route::get('/{sale}', [SalesController::class, 'show'])->name('sales.show');
        Route::put('/{sale}', [SalesController::class, 'update'])->name('sales.update');
        Route::delete('/{sale}', [SalesController::class, 'destroy'])->name('sales.destroy');
    });

    // Sale Items routes
    Route::prefix('sale-items')->group(function () {
        Route::get('/stats', [SaleItemsController::class, 'stats'])->name('sale-items.stats');
        Route::get('/by-sale/{saleId}', [SaleItemsController::class, 'getBySale'])->name('sale-items.by-sale');
        Route::get('/', [SaleItemsController::class, 'index'])->name('sale-items.index');
        Route::post('/', [SaleItemsController::class, 'store'])->name('sale-items.store');
        Route::get('/{saleItem}', [SaleItemsController::class, 'show'])->name('sale-items.show');
        Route::put('/{saleItem}', [SaleItemsController::class, 'update'])->name('sale-items.update');
        Route::delete('/{saleItem}', [SaleItemsController::class, 'destroy'])->name('sale-items.destroy');
    });

    // Purchases routes
    Route::prefix('purchases')->group(function () {
        Route::get('/stats', [PurchasesController::class, 'stats'])->name('purchases.stats');
        Route::get('/recent', [PurchasesController::class, 'recent'])->name('purchases.recent');
        Route::get('/', [PurchasesController::class, 'index'])->name('purchases.index');
        Route::post('/', [PurchasesController::class, 'store'])->name('purchases.store');
        Route::get('/{purchase}', [PurchasesController::class, 'show'])->name('purchases.show');
        Route::put('/{purchase}', [PurchasesController::class, 'update'])->name('purchases.update');
        Route::delete('/{purchase}', [PurchasesController::class, 'destroy'])->name('purchases.destroy');
        Route::get('/{purchase}/summary', [PurchasesController::class, 'summary'])->name('purchases.summary');
        Route::get('/{purchase_id}/items', [PurchaseItemsController::class, 'getByPurchase'])->name('purchases.items');
    });

    // Purchase Items routes
    Route::prefix('purchase-items')->group(function () {
        Route::get('/stats', [PurchaseItemsController::class, 'stats'])->name('purchase-items.stats');
        Route::get('/', [PurchaseItemsController::class, 'index'])->name('purchase-items.index');
        Route::post('/', [PurchaseItemsController::class, 'store'])->name('purchase-items.store');
        Route::get('/{purchaseItem}', [PurchaseItemsController::class, 'show'])->name('purchase-items.show');
        Route::put('/{purchaseItem}', [PurchaseItemsController::class, 'update'])->name('purchase-items.update');
        Route::delete('/{purchaseItem}', [PurchaseItemsController::class, 'destroy'])->name('purchase-items.destroy');
    });

    // Product purchase history route
    Route::get('/products/{product_id}/purchase-history', [PurchaseItemsController::class, 'getPurchaseHistory'])->name('products.purchase-history');

    // Business routes
    Route::prefix('business')->group(function () {
        Route::get('/', [BusinessController::class, 'show'])->name('business.show');
        Route::put('/', [BusinessController::class, 'update'])->name('business.update');
        Route::patch('/', [BusinessController::class, 'patch'])->name('business.patch');
        Route::get('/summary', [BusinessController::class, 'summary'])->name('business.summary');
    });

    // Business Intelligence routes (para chatbot IA)
    Route::prefix('business-intelligence')->group(function () {
        Route::get('/dashboard', [BusinessIntelligenceController::class, 'getDashboard'])->name('bi.dashboard');
        Route::get('/revenue/{period}', [BusinessIntelligenceController::class, 'getRevenue'])->name('bi.revenue');
        Route::get('/product-stock/{productName}', [BusinessIntelligenceController::class, 'getProductStock'])->name('bi.product-stock');
        Route::get('/top-selling-products', [BusinessIntelligenceController::class, 'getTopSellingProducts'])->name('bi.top-selling');
        Route::get('/daily-sales', [BusinessIntelligenceController::class, 'getDailySales'])->name('bi.daily-sales');
        Route::get('/monthly-sales', [BusinessIntelligenceController::class, 'getMonthlySales'])->name('bi.monthly-sales');
        Route::get('/top-products', [BusinessIntelligenceController::class, 'getTopProducts'])->name('bi.top-products');
        Route::get('/low-stock', [BusinessIntelligenceController::class, 'getLowStock'])->name('bi.low-stock');
    });
});