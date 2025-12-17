<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V4\Admin\DeliveryPartnerAdminController;
use App\Http\Controllers\Api\V4\Delivery\DeliveryPartnerController;
use App\Http\Controllers\Api\V4\Delivery\DeliveryController;

/*
|--------------------------------------------------------------------------
| API V4 Routes - Delivery Partners
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for delivery partner management.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Admin routes for delivery partner management
Route::prefix('admin')->middleware('auth:sanctum','role:admin')->group(function () {
    Route::apiResource('delivery-partners', DeliveryPartnerAdminController::class);
    Route::post('delivery-partners/{deliveryPartner}/activate', [DeliveryPartnerAdminController::class, 'activate']);
    Route::get('delivery-partners/available', [DeliveryPartnerAdminController::class, 'available']);
});

// Delivery partner routes (for delivery partners themselves)
Route::prefix('delivery')->middleware('auth:sanctum','role:delivery')->group(function () {
    Route::get('profile', [DeliveryPartnerController::class, 'profile']);
    Route::put('profile', [DeliveryPartnerController::class, 'updateProfile']);
    Route::post('availability', [DeliveryPartnerController::class, 'updateAvailability']);
    Route::post('location', [DeliveryPartnerController::class, 'updateLocation']);
    Route::get('orders', [DeliveryPartnerController::class, 'orders']);
    Route::get('orders/{order}', [DeliveryPartnerController::class, 'showOrder']);
    Route::patch('orders/{order}/status', [DeliveryPartnerController::class, 'updateOrderStatus']);
    Route::get('stats', [DeliveryPartnerController::class, 'stats']);
    Route::post('deliveries/{id}/accept', [DeliveryController::class, 'accept']);
    Route::post('deliveries/{id}/pickup', [DeliveryController::class, 'pickup']);
    Route::post('deliveries/{id}/deliver', [DeliveryController::class, 'deliver']);
    
});


