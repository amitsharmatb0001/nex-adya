<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V5 Routes - Advanced Features
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for advanced features.
| These routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// V5 API - Advanced Features & Analytics
// This is a placeholder for future advanced features

// Lightweight health check for v5
Route::get('/health', fn () => response()->json(['ok' => true, 'version' => 'v5']));
