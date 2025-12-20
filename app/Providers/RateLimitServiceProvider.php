<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{                       

public function boot(): void
{
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });

    RateLimiter::for('otp', function (Request $request) {
        return Limit::perMinute(3)->by($request->ip());
    });

    RateLimiter::for('orders', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('admin', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()->id);
    });
}
}