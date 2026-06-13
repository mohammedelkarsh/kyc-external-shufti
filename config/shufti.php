<?php

declare(strict_types=1);

return [

    'client_id' => env('SHUFTI_CLIENT_ID'),
    'secret' => env('SHUFTI_SECRET'),
    'base_url' => env('SHUFTI_BASE_URL', 'https://api.shuftipro.com'),
    'callback_url' => env('SHUFTI_CALLBACK_URL'),

    'poll_seconds' => (int) env('SHUFTI_POLL_SECONDS', 30),
    'poll_interval' => (int) env('SHUFTI_POLL_INTERVAL', 2),

    'routes' => [
        'webhook' => (bool) env('SHUFTI_WEBHOOK_ROUTE', true),
        'prefix' => env('SHUFTI_WEBHOOK_PREFIX', 'kyc/webhooks'),
    ],

];
