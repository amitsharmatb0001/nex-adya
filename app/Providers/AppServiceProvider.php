<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\PushService::class, function ($app) {
            return new \App\Services\PushService();
        });
    }

    public function boot(): void
    {
        // Register FCM notification channel
        $this->app->make('Illuminate\Notifications\ChannelManager')
            ->extend('fcm', function ($app) {
                return new \App\Notifications\Channels\FcmChannel();
            });

        // Ensure middleware aliases are registered (belt-and-suspenders)
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
        if (class_exists(\App\Http\Middleware\FraudDetectionMiddleware::class)) {
            $router->aliasMiddleware('fraud', \App\Http\Middleware\FraudDetectionMiddleware::class);
            $router->aliasMiddleware('fraud.detect', \App\Http\Middleware\FraudDetectionMiddleware::class);
        }

        // TEMP: Log all DB queries to help diagnose where writes go
        DB::listen(function ($query) {
            try {
                Log::error('sql', [
                    'sql'        => $query->sql,
                    'bindings'   => $query->bindings,
                    'time_ms'    => $query->time,
                    'connection' => DB::getDefaultConnection(),
                    'database'   => DB::connection()->getDatabaseName(),
                ]);
            } catch (\Throwable $e) {
                // ignore logging failures
            }
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('email') ?? 'guest').'|'.$request->ip());
        });
    }
}
