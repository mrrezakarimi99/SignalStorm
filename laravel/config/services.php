<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],


    // Data Provider Configuration
    'data_provider' => env('DATA_PROVIDER', 'mock'),

    // Binance API
    'binance' => [
        'api_key' => env('BINANCE_API_KEY'),
        'api_secret' => env('BINANCE_API_SECRET'),
        'base_url' => env('BINANCE_BASE_URL', 'https://api.binance.com'),
    ],

    // Notifier Configuration
    'notifiers' => env('NOTIFIERS', 'console'),

    // Telegram Bot
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    // Python Trainer Service
    'trainer' => [
        'url' => env('TRAINER_SERVICE_URL', 'http://python-trainer:8001'),
        'timeout' => env('TRAINER_TIMEOUT', 300),
    ],

    // Model Storage
    'model_storage' => [
        'driver' => env('MODEL_STORAGE', 'disk'),
        'disk_path' => env('MODEL_STORAGE_PATH', storage_path('models')),
    ],

    // MinIO Configuration
    'minio' => [
        'endpoint' => env('MINIO_ENDPOINT', 'minio:9000'),
        'access_key' => env('MINIO_ACCESS_KEY'),
        'secret_key' => env('MINIO_SECRET_KEY'),
        'bucket' => env('MINIO_BUCKET', 'models'),
        'use_ssl' => env('MINIO_USE_SSL', false),
    ],
];
