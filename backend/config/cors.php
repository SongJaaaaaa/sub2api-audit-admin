<?php

$origins = [
    'https://audit.sjiaa.cc.cd',
    'http://localhost',
    'http://localhost:5173',
    'http://localhost:5175',
    'http://127.0.0.1',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:5175',
    'https://localhost',
    'capacitor://localhost',
];

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Origin', 'X-Requested-With'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 600,

    'supports_credentials' => false,
];
