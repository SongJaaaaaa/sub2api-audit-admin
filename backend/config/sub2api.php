<?php

return [
    'db_connection' => env('SUB2API_DB_CONNECTION', 'sub2api'),

    'admin_api' => [
        'base_url' => env('SUB2API_ADMIN_API_URL'),
        'key' => env('SUB2API_ADMIN_API_KEY'),
        'timeout' => env('SUB2API_ADMIN_API_TIMEOUT', 10),
        'idempotency_verified' => env('SUB2API_BALANCE_IDEMPOTENCY_VERIFIED', false),
    ],

    'user_api' => [
        'base_url' => env('SUB2API_USER_API_URL') ?: env('SUB2API_ADMIN_API_URL'),
        'timeout' => env('SUB2API_USER_API_TIMEOUT', 10),
    ],
];
