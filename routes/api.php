<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PublicVendorController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// Public routes
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Public vendor registration
Route::prefix('public/vendors')->group(function () {
    Route::get('/categories', [PublicVendorController::class, 'categories']);
    Route::post('/register', [PublicVendorController::class, 'register']);
    Route::post('/check-cnpj', [PublicVendorController::class, 'checkCnpj']);
    Route::post('/check-email', [PublicVendorController::class, 'checkEmail']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // Dashboard (Cliente)
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/budget-overview', [DashboardController::class, 'budgetOverview']);
    Route::get('/dashboard/proposals-by-status', [DashboardController::class, 'proposalsByStatus']);
    Route::get('/dashboard/spending-by-category', [DashboardController::class, 'spendingByCategory']);
    Route::get('/dashboard/spending-history', [DashboardController::class, 'spendingHistory']);

    // Admin Dashboard (Super Admin only)
    Route::prefix('admin')->middleware('can:super-admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
        Route::get('/dashboard/organizations', [AdminDashboardController::class, 'organizations']);
        Route::get('/dashboard/vendors', [AdminDashboardController::class, 'vendors']);
        Route::get('/dashboard/geographic', [AdminDashboardController::class, 'geographic']);
        Route::get('/dashboard/categories', [AdminDashboardController::class, 'categories']);
    });

    // Categories
    Route::apiResource('categories', CategoryController::class);
    Route::post('/categories/{category}/toggle-active', [CategoryController::class, 'toggleActive']);
    Route::post('/categories/reorder', [CategoryController::class, 'reorder']);

    // Vendors (static routes before apiResource to avoid {vendor} capturing them)
    Route::get('/vendors/nearby', [VendorController::class, 'nearby']);
    Route::get('/vendors/by-category/{category}', [VendorController::class, 'byCategory']);
    Route::apiResource('vendors', VendorController::class);
    Route::post('/vendors/{vendor}/toggle-active', [VendorController::class, 'toggleActive']);
    Route::post('/vendors/{vendor}/verify', [VendorController::class, 'verify']);
    Route::post('/vendors/{vendor}/approve', [VendorController::class, 'approve']);
    Route::post('/vendors/{vendor}/reject', [VendorController::class, 'reject']);
    Route::get('/vendors/{vendor}/compliance', [VendorController::class, 'compliance']);

    // Events (static routes before apiResource to avoid {event} capturing them)
    Route::get('/events/upcoming', [EventController::class, 'upcoming']);
    Route::apiResource('events', EventController::class);
    Route::post('/events/{event}/status', [EventController::class, 'updateStatus']);
    Route::post('/events/{event}/duplicate', [EventController::class, 'duplicate']);
    Route::get('/events/{event}/statistics', [EventController::class, 'statistics']);
});
