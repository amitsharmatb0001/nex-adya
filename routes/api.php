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





/**
 * ✅ P3 FIX #17: Health Check Endpoints
 * 
 * ADD TO: routes/api.php (at the bottom)
 * 
 * Provides monitoring endpoints to check system health.
 * Use /health for uptime monitoring, /health/detailed for debugging.
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// Simple health check (fast, for uptime monitoring)
Route::get('/health', function() {
    return response()->json([
        'ok' => true,
        'service' => 'Nexadya API',
        'timestamp' => now()->toIso8601String(),
        'environment' => app()->environment(),
    ]);
});

// Detailed health check (comprehensive diagnostics)
Route::get('/health/detailed', function() {
    $checks = [];
    $overall = true;
    $startTime = microtime(true);
    
    // 1. Database connectivity check
    try {
        DB::connection()->getPdo();
        $dbCheck = DB::select('SELECT 1 as test');
        $checks['database'] = [
            'status' => 'ok',
            'driver' => config('database.default'),
            'connection' => 'connected'
        ];
    } catch (\Exception $e) {
        $checks['database'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
        $overall = false;
    }
    
    // 2. Redis cache check
    try {
        $testKey = 'health-check-' . time();
        Cache::store('redis')->put($testKey, 'ok', 10);
        $value = Cache::store('redis')->get($testKey);
        Cache::store('redis')->forget($testKey);
        
        $checks['redis'] = [
            'status' => $value === 'ok' ? 'ok' : 'error',
            'driver' => 'redis',
            'connection' => 'connected'
        ];
    } catch (\Exception $e) {
        $checks['redis'] = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'note' => 'Redis may not be running'
        ];
        // Don't mark as overall failure for local dev
        if (app()->environment('production')) {
            $overall = false;
        }
    }
    
    // 3. Queue health check
    try {
        $queueSize = Queue::size();
        $status = 'ok';
        
        if ($queueSize > 1000) {
            $status = 'warning';
            $overall = false;
        }
        
        if ($queueSize > 5000) {
            $status = 'critical';
            $overall = false;
        }
        
        $checks['queue'] = [
            'status' => $status,
            'size' => $queueSize,
            'connection' => config('queue.default')
        ];
    } catch (\Exception $e) {
        $checks['queue'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
        if (app()->environment('production')) {
            $overall = false;
        }
    }
    
    // 4. Storage check
    try {
        $testFile = 'health-check-' . time() . '.txt';
        Storage::put($testFile, 'ok');
        $content = Storage::get($testFile);
        Storage::delete($testFile);
        
        $checks['storage'] = [
            'status' => $content === 'ok' ? 'ok' : 'error',
            'disk' => config('filesystems.default'),
            'writable' => true
        ];
    } catch (\Exception $e) {
        $checks['storage'] = [
            'status' => 'error',
            'message' => $e->getMessage(),
            'disk' => config('filesystems.default')
        ];
        $overall = false;
    }
    
    // 5. Disk space check
    try {
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskPercent = ($diskFree / $diskTotal) * 100;
        
        $diskStatus = 'ok';
        if ($diskPercent < 20) {
            $diskStatus = 'warning';
        }
        if ($diskPercent < 10) {
            $diskStatus = 'critical';
            $overall = false;
        }
        
        $checks['disk'] = [
            'status' => $diskStatus,
            'free_percent' => round($diskPercent, 2),
            'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
            'total_gb' => round($diskTotal / 1024 / 1024 / 1024, 2)
        ];
    } catch (\Exception $e) {
        $checks['disk'] = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
    
    // 6. Memory check
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    $checks['memory'] = [
        'status' => 'ok',
        'current_mb' => round($memoryUsage / 1024 / 1024, 2),
        'limit' => $memoryLimit
    ];
    
    // 7. Application version
    $checks['application'] = [
        'name' => config('app.name'),
        'env' => app()->environment(),
        'debug' => config('app.debug'),
        'timezone' => config('app.timezone'),
        'locale' => config('app.locale'),
    ];
    
    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
    
    return response()->json([
        'ok' => $overall,
        'timestamp' => now()->toIso8601String(),
        'response_time_ms' => $responseTime,
        'checks' => $checks
    ], $overall ? 200 : 503);
})->middleware('throttle:60,1'); // Rate limit: 60 requests per minute