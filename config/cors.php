<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | ✅ SECURE - Never use wildcards (*) in production
    | Set CORS_ALLOWED_ORIGINS in your environment file to specify
    | allowed origins explicitly.
    |
    | Example .env.local (development):
    | CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
    |
    | Example .env.production:
    | CORS_ALLOWED_ORIGINS=https://app.nexadya.com,https://www.nexadya.com
    |
    */

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | This should be true when using Sanctum for SPA authentication
    | as it allows cookies to be sent with cross-origin requests.
    |
    */

    'supports_credentials' => true,

];