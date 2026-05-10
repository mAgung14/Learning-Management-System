<?php

return [
    'apps' => [
        [
            'app_id' => env('REVERB_APP_ID', 'laravel'),
            'app_key' => env('REVERB_APP_KEY'),
            'app_secret' => env('REVERB_APP_SECRET'),
            'allowed_origins' => [
                env('REVERB_ALLOWED_ORIGIN', '*'),
            ],
            'driver' => 'websocket',
        ],
    ],

    'host' => env('REVERB_HOST', '127.0.0.1'),
    'port' => env('REVERB_PORT', 8080),

    'server_host' => env('REVERB_SERVER_HOST', '127.0.0.1'),
    'server_port' => env('REVERB_SERVER_PORT', 8080),

    'tls' => [
        'options' => [],
    ],

    'scaling_enabled' => env('REVERB_SCALING_ENABLED', false),
];
