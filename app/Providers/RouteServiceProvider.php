<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $this->configureRateLimiting();

        // IMPORTANT: wrap all route groups in $this->routes(...)
        $this->routes(function () {

            // v1 — core user features
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));

            // v2 — restaurant partners & service features
            Route::middleware('api')
                ->prefix('api/v2')
                ->group(base_path('routes/api_v2.php'));

            // v3 — admin features
            Route::middleware('api')
                ->prefix('api/v3')
                ->group(base_path('routes/api_v3.php'));

            // v4 — delivery partner features
            Route::middleware('api')
                ->prefix('api/v4')
                ->group(base_path('routes/api_v4.php'));

            // v5 — advanced features & analytics
            Route::middleware('api')
                ->prefix('api/v5')
                ->group(base_path('routes/api_v5.php'));

            // (optional) legacy api.php during migration
            // Route::middleware('api')
            //     ->prefix('api')
            //     ->group(base_path('routes/api.php'));

            // web
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // throttle used by /api/v1/auth/login
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(($request->input('email') ?? 'guest') . '|' . $request->ip());
        });
    }
}
