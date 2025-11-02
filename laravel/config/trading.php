<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trading Configuration
    |--------------------------------------------------------------------------
    |
    | Configure trading pairs, intervals, and automation settings
    |
    */

    'pairs' => [
        'BTCUSDT' => [
            'enabled' => env('TRADING_BTCUSDT_ENABLED', true),
            'intervals' => ['1h', '4h', '1d'],
        ],
        'ETHUSDT' => [
            'enabled' => env('TRADING_ETHUSDT_ENABLED', true),
            'intervals' => ['1h', '4h', '1d'],
        ],
        'BNBUSDT' => [
            'enabled' => env('TRADING_BNBUSDT_ENABLED', false),
            'intervals' => ['1h', '4h'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Fetching Configuration
    |--------------------------------------------------------------------------
    */

    'fetch' => [
        // How many candles to fetch per run
        'limit' => env('FETCH_LIMIT', 100),

        // Schedule (cron expression)
        // Default: every hour
        'schedule' => env('FETCH_SCHEDULE', 'hourly'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Training Configuration
    |--------------------------------------------------------------------------
    */

    'training' => [
        // Number of candles to use for training
        'data_limit' => env('TRAINING_DATA_LIMIT', 1000),

        // Training epochs
        'epochs' => env('TRAINING_EPOCHS', 50),

        // Batch size
        'batch_size' => env('TRAINING_BATCH_SIZE', 32),

        // Schedule (cron expression)
        // Default: daily at 02:00 AM
        'schedule' => env('TRAINING_SCHEDULE', 'daily'),

        // Time for daily schedule
        'schedule_time' => env('TRAINING_SCHEDULE_TIME', '02:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Automation Settings
    |--------------------------------------------------------------------------
    */

    'automation' => [
        // Enable automated data fetching
        'auto_fetch' => env('AUTO_FETCH_DATA', true),

        // Enable automated training
        'auto_train' => env('AUTO_TRAIN_MODEL', true),

        // Auto-train after data fetch (if enough new data)
        'train_after_fetch' => env('TRAIN_AFTER_FETCH', false),

        // Minimum candles required before training
        'min_candles_for_training' => env('MIN_CANDLES_FOR_TRAINING', 500),
    ],
];

