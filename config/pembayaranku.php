<?php

return [
    'api_key' => env('PEMBAYARANKU_API_KEY'),
    'environment' => env('PEMBAYARANKU_ENVIRONMENT', 'sandbox'),
    'base_url' => env('PEMBAYARANKU_BASE_URL', 'https://www.pembayaranku.com/api/v1'),
    'timeout_seconds' => (int) env('PEMBAYARANKU_TIMEOUT_SECONDS', 15),

    'webhooks' => [
        'secrets' => [
            'live' => env('PEMBAYARANKU_WEBHOOK_SECRET_LIVE'),
            'sandbox' => env('PEMBAYARANKU_WEBHOOK_SECRET_SANDBOX'),
        ],
        // Implement PembayaranKu\Laravel\Contracts\WebhookHandler in your application.
        'handler' => env('PEMBAYARANKU_WEBHOOK_HANDLER'),
        'max_clock_skew_seconds' => 300,
        'max_body_bytes' => 262144,
        'stale_processing_seconds' => 180,
    ],
];
