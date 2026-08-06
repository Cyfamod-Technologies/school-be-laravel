<?php

return [

    'paths' => [
        'api/*',
        'register-school',
        'login',
        'logout',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    // CORS_ALLOWED_ORIGINS: comma-separated exact origins (scheme + host + port,
    // no trailing slash), e.g. "https://app.example.com,https://admin.example.com".
    // '*' is intentionally NOT a safe default here since supports_credentials is
    // true below -- browsers reject wildcard origins on credentialed requests
    // anyway, so '*' silently does nothing useful in production and just masks
    // the fact that this was never actually configured.
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:3001')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'X-CSRF-TOKEN'],

    'max_age' => 0,

    'supports_credentials' => true,

];
