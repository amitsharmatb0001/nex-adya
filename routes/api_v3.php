<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V3\AdminController;
use App\Http\Controllers\Api\V3\FraudAdminController;
use App\Http\Controllers\Api\V3\PayoutAdminController;
use App\Http\Controllers\Api\V3\RestaurantAdminController;
use App\Http\Controllers\Api\V3\RestaurantOwnerAdminController;
use App\Http\Controllers\Api\V3\ReviewAdminController;

/*
|--------------------------------------------------------------------------
| API V3 Routes - Admin Panel
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v3 and should be protected by
| 'auth:sanctum' and 'role:admin' middleware.
|
*/
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // General Admin
    // Route::get('/dashboard', [AdminController::class, 'dashboard']);

    // Payouts
    Route::get('/payouts', [PayoutAdminController::class, 'index']);
    Route::post('/payouts/{id}/approve', [PayoutAdminController::class, 'approve']);
    Route::post('/payouts/{id}/reject', [PayoutAdminController::class, 'reject']);
    Route::post('/payouts/{id}/fail', [PayoutAdminController::class, 'fail']);

    // Restaurants
    Route::get('/restaurants', [RestaurantAdminController::class, 'index']);
    Route::get('/restaurants/{id}', [RestaurantAdminController::class, 'show']);
    Route::patch('/restaurants/{id}', [RestaurantAdminController::class, 'update']);
    Route::delete('/restaurants/{id}', [RestaurantAdminController::class, 'destroy']);

    // Restaurant Owners
    Route::get('/restaurant-owners', [RestaurantOwnerAdminController::class, 'index']);
    Route::post('/restaurant-owners/{id}/approve', [RestaurantOwnerAdminController::class, 'approve']);
    Route::post('/restaurant-owners/{id}/reject', [RestaurantOwnerAdminController::class, 'reject']);

    // Reviews
    Route::get('/reviews', [ReviewAdminController::class, 'index']);
    Route::post('/reviews/{id}/delete', [ReviewAdminController::class, 'delete']);
    Route::post('/reviews/{id}/restore', [ReviewAdminController::class, 'restore']);
    
    // Review Deletion Requests
    Route::get('/review-deletion-requests', [ReviewAdminController::class, 'deletionRequests']);
    Route::post('/review-deletion-requests/{id}/approve', [ReviewAdminController::class, 'approveDeletionRequest']);
    Route::post('/review-deletion-requests/{id}/reject', [ReviewAdminController::class, 'rejectDeletionRequest']);

    // Fraud Logs
    Route::get('/fraud-logs', [FraudAdminController::class, 'index']);
    Route::post('/fraud-logs/{id}/flag', [FraudAdminController::class, 'flag']);

});

// Publicly accessible webhooks should be outside the admin-only group.
// They should have their own security (e.g., signature verification).
// Route::post('/payouts/webhook/provider', [\App\Http\Controllers\Api\V3\PayoutWebhookController::class, 'handle']);
