<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V2\Owner\RestaurantOwnerController;
use App\Http\Controllers\Api\V2\Auth\RestaurantAuthController;
use App\Http\Controllers\Api\V2\Order\OrderManagementController;
use App\Http\Controllers\Api\V2\Owner\DashboardController;
use App\Http\Controllers\Api\V2\Menu\MenuController;
use App\Http\Controllers\Api\V2\Menu\MenuItemController;
use App\Http\Controllers\Api\V2\NotificationController;
use App\Http\Controllers\Api\V2\Owner\ReviewReplyController;
use App\Http\Controllers\Api\V2\Owner\PayoutController;
use App\Http\Controllers\Api\V2\Admin\PayoutApprovalController;
use App\Http\Controllers\Api\V2\Restaurant\PayoutRequestController;
use App\Http\Controllers\Api\V2\Auth\TrustedDeviceController;

// NOTE: This file is mounted under /api/v2 by RouteServiceProvider.
// Do NOT add another 'v2' prefix here.
// V2 API - Restaurant Partners & Service Features

// Public owner auth
Route::post('/auth/register', [RestaurantAuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login',    [RestaurantAuthController::class, 'login'])->middleware('throttle:6,1');

// Protected restaurant-owner endpoints
Route::middleware(['auth:sanctum','role:restaurant'])->group(function () {
    // Owner self
    Route::get('/auth/me', [RestaurantAuthController::class, 'me'])->middleware('auth:sanctum');

    // Order management for the restaurant the owner is assigned to
    Route::get('/orders', [OrderManagementController::class,'index']);
    Route::get('/orders/{id}', [OrderManagementController::class,'show']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/top-items', [DashboardController::class, 'topItems']);
    Route::get('/dashboard/orders', [DashboardController::class, 'orders']);
    Route::get('/dashboard/trend', [DashboardController::class, 'trend']);

    // Menus (restaurant can have multiple menus)
    Route::get('/menus', [MenuController::class, 'index']);
    Route::post('/menus', [MenuController::class, 'store']);
    Route::patch('/menus/{id}', [MenuController::class, 'update']);
    Route::delete('/menus/{id}', [MenuController::class, 'destroy']);

    // Menu items
    Route::get('/menus/{menuId}/items', [MenuItemController::class, 'index']);
    Route::post('/menus/{menuId}/items', [MenuItemController::class, 'store']);
    Route::patch('/menus/{menuId}/items/{id}', [MenuItemController::class, 'update']);
    Route::delete('/menus/{menuId}/items/{id}', [MenuItemController::class, 'destroy']);

    // Toggle availability quick endpoint
    Route::post('/menus/{menuId}/items/{id}/toggle-availability', [\App\Http\Controllers\Api\V2\Menu\MenuItemController::class, 'toggleAvailability']);

    // Reviews
    Route::post('/reviews/{id}/reply', [ReviewReplyController::class, 'store']);
    Route::delete('/reviews/{id}/reply', [ReviewReplyController::class, 'destroy']);
    Route::post('/reviews/{id}/request-deletion', [ReviewReplyController::class, 'requestDeletion']);

    // Payouts
    Route::get('/owner/payouts', [PayoutController::class, 'index']);
    Route::post('/owner/payouts', [PayoutController::class, 'store']);



    Route::post('/payouts/request', [PayoutRequestController::class, 'store']);
    Route::get('/payouts', [PayoutRequestController::class, 'index']);

    Route::patch('/orders/{order}/status', [OrderManagementController::class, 'updateStatus']);

});

// Order status updates - accessible by both restaurant owners and admins
Route::middleware(['auth:sanctum'])->group(function () {
    Route::patch('/orders/{id}/status', [OrderManagementController::class,'updateStatus']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::get('/trusted-devices', [TrustedDeviceController::class, 'index']);
    Route::delete('/trusted-devices/{id}', [TrustedDeviceController::class, 'destroy']);
});

// Admin-only endpoints
Route::middleware(['auth:sanctum','role:admin'])->group(function () {
    // Payouts (Admin)
    Route::get('/payouts', [PayoutApprovalController::class, 'index']);
    Route::post('/payouts/{id}/approve', [PayoutApprovalController::class, 'approve']);
    Route::post('/payouts/{id}/reject', [PayoutApprovalController::class, 'reject']);
    Route::get('/trusted-devices', [TrustedDeviceController::class, 'adminIndex']);
});



// Lightweight health check for v2
Route::get('/health', fn () => response()->json(['ok' => true, 'version' => 'v2']));
