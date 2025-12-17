<?php

use Illuminate\Support\Facades\Route;

// This file is already inside an `/api` group by Laravel 11.
// API Versions:
// v1 - Core user features
// v2 - Restaurant partners & service features  
// v3 - Admin features
// v4 - Delivery partner features
// v5 - Advanced features

Route::prefix('v1')->group(function () {
    require base_path('routes/api_v1.php');
});

Route::prefix('v2')->group(function () {
    require base_path('routes/api_v2.php');
});

Route::prefix('v3')->group(function () {
    require base_path('routes/api_v3.php');
});

Route::prefix('v4')->group(function () {
    require base_path('routes/api_v4.php');
});

Route::prefix('v5')->group(function () {
    require base_path('routes/api_v5.php');
});