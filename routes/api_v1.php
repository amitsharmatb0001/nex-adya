<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\{RegisterController, LoginController, MeController, LogoutController};
use App\Http\Controllers\Api\V1\Restaurant\{RestaurantController, MenuController};
use App\Http\Controllers\Api\V1\Order\OrderController;
use App\Http\Controllers\Api\V1\Address\AddressController;
use App\Http\Controllers\Api\V1\Search\SearchController;
use App\Http\Controllers\Api\V1\Account\AccountController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Auth\PasswordStrengthController;
use App\Http\Controllers\Api\V1\Payment\PaymentController;
use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Review\ReviewController;
use App\Http\Controllers\Api\V1\Wishlist\WishlistController;
use App\Http\Controllers\Api\V1\Activity\ActivityLogController;

Route::get('/health', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));     // Health Check     

Route::prefix('auth')->group(function () {
    Route::post('/register', RegisterController::class);     // RegisterController     // RegisterController     // RegisterController     // RegisterController
    Route::post('/login',    LoginController::class)->middleware('throttle:login', 'fraud', 'fraud.detect:login');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',      MeController::class);        // MeController
        Route::post('/logout', LogoutController::class);     // LogoutController     // LogoutController     // LogoutController     // LogoutController
        
    });
});

Route::get('/restaurants', [RestaurantController::class, 'index']);     // RestaurantController
Route::get('/restaurants/{id}/menus', [MenuController::class, 'forRestaurant']);     // MenuController    // MenuController    // MenuController    // MenuController
Route::get('/restaurants/{id}', [RestaurantController::class, 'show']);     // RestaurantController
Route::get('/search/suggest', [SearchController::class, 'suggest']);     // SearchController




Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);        // OrderController
    Route::post('/orders', [OrderController::class, 'store'])->middleware('role:user', 'fraud.detect:order');
    Route::patch('/orders/{id}', [OrderController::class, 'update'])->middleware('role:user');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->middleware('role:user');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('role:user');
    Route::post('/orders/{id}/confirm', [OrderController::class, 'confirm'])->middleware('role:restaurant');
    Route::post('/orders/{id}/update-status', [OrderController::class, 'updateStatus'])->middleware('role:restaurant');
    Route::get('/addresses',    [AddressController::class, 'index'])->middleware('role:user');
    Route::post('/addresses',   [AddressController::class, 'store'])->middleware('role:user');
    Route::patch('/addresses/{id}', [AddressController::class, 'update'])->middleware('role:user');
    Route::delete('/addresses/{id}',[AddressController::class, 'destroy'])->middleware('role:user');
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);           
    Route::post('/restaurants/{id}/reviews', [ReviewController::class, 'storeRestaurant'])->middleware('role:user');
    Route::get('/restaurants/{id}/reviews', [ReviewController::class, 'indexRestaurant'])->middleware('role:user');
    Route::post('/menu-items/{id}/reviews', [ReviewController::class, 'storeMenuItem'])->middleware('role:user');
    Route::get('/menu-items/{id}/reviews', [ReviewController::class, 'indexMenuItem'])->middleware('role:user');
    Route::put('/reviews/{id}', [ReviewController::class, 'update'])->middleware('role:user');
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->middleware('role:user');
    Route::post('/restaurants/{id}/wishlist', [WishlistController::class, 'store'])->middleware('role:user');
    Route::delete('/wishlist/{id}', [WishlistController::class, 'destroy'])->middleware('role:user');
    Route::get('/wishlist', [WishlistController::class, 'index'])->middleware('role:user');
    Route::post('/menu-items/{id}/wishlist', [WishlistController::class, 'store'])->middleware('role:user');
    Route::delete('/wishlist/{id}', [WishlistController::class, 'destroy'])->middleware('role:user');
    Route::get('/activity', [ActivityLogController::class, 'index'])->middleware('role:user');
    Route::delete('/activity/{id}', [ActivityLogController::class, 'destroy'])->middleware('role:user');
    Route::get('/activity/export', [ActivityLogController::class, 'export'])->middleware('role:user');

});
                                




Route::prefix('account')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
    Route::patch('/', [AccountController::class, 'update']);         // AccountController
    Route::post('/password', [AccountController::class, 'changePassword']);     // AccountController     // AccountController
    Route::delete('/', [AccountController::class, 'destroy']);         // AccountController     
    Route::get('/export', [AccountController::class, 'export']);         // AccountController
    });
});



// 2FA
Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verify']);     // TwoFactorController
Route::post('/auth/2fa/resend', [TwoFactorController::class, 'resend']);     // TwoFactorController     // TwoFactorController

// Password strength
Route::post('/auth/password-strength', [PasswordStrengthController::class, 'check']);     // PasswordStrengthController




// Payments
Route::prefix('payments')->group(function () {
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/initiate', [PaymentController::class, 'initiate'])->middleware('fraud', 'fraud.detect:payment'); // Fraud Detection Middleware
        Route::post('/{id}/verify', [PaymentController::class, 'verify'])->middleware('fraud', 'fraud.detect:payment'); // Fraud Detection Middleware
        Route::get('/{id}', [PaymentController::class, 'status'])->middleware('fraud', 'fraud.detect:payment'); // Fraud Detection Middleware
        Route::post('/payments', [PaymentController::class, 'store'])->middleware('fraud', 'fraud.detect:payment');
    });
});

// Admin
// Fraud logs (only admin)
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    Route::get('/fraud-logs', function () {
        abort_unless(auth()->user()->isAdmin(), 403);
        return \App\Models\FraudLog::latest()->paginate(50);
    });
    
    // Activity Logs (Admin only) - Moved to V3 API
    // These routes are now available in /api/v3/activity-logs
});



