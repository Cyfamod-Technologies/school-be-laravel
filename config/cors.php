<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'register-school', 'login', 'logout', '*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
