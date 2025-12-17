<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: base_path('routes/web.php'),
        api: base_path('routes/api.php'),
        commands: base_path('routes/console.php'),
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register default middleware groups
        $middleware->web();
        $middleware->api();

        // (optional) aliases / global / prepend / append
        // $middleware->alias(['role' => \App\Http\Middleware\Role::class]);
        // $middleware->append(\App\Http\Middleware\Something::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Customize exception handling if needed
    })
    ->create();
