<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Cart-Session',
        'X-Session-Id',
        'X-XSRF-TOKEN',
        'X-Requested-With',
    ],

    'exposed_headers' => ['X-Cart-Session'],

    'max_age' => 0,

    'supports_credentials' => true,

];
