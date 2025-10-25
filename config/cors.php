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

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Authorization', 'X-CSRF-TOKEN'],

    'max_age' => 0,

    'supports_credentials' => true,

];
