<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'register-school', 'login', 'logout', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://127.0.0.1:5500'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
