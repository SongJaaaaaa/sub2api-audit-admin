<?php

return [
    'timezone' => env('APP_TIMEZONE', 'Asia/Shanghai'),
    'money_scale' => 2,
    'quota_scale' => 2,
    'sub2api_rate' => '1.00',
    'remote_reconcile_delay_seconds' => env('SUB2API_REMOTE_RECONCILE_DELAY_SECONDS', 60),
];
