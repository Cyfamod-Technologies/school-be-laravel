<?php

use Illuminate\Support\Arr;

$defaultOrigins = array_values(array_filter([
    'http://127.0.0.1:5500',
    'http://localhost:5500',
    'http://127.0.0.1:3000',
    'http://localhost:3000',
    'http://127.0.0.1:8000',
    'http://localhost:8000',
    rtrim(env('APP_URL', ''), '/'),
    'https://lynxglobal.com.ng',
    'https://api.lynxglobal.com.ng',
]));

$configuredOrigins = array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
));

$allowedOrigins = Arr::wrap(count($configuredOrigins) ? $configuredOrigins : $defaultOrigins);

return [

    'paths' => [
        'api/*',
        'register-school',
        'login',
        'logout',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => array_filter([
        '~^https?://([a-z0-9-]+\.)?lynxglobal\.com\.ng$~i',
    ]),

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'X-CSRF-TOKEN'],

    'max_age' => 0,

    'supports_credentials' => true,

];
