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
    | Prediction & Signal Configuration
    |--------------------------------------------------------------------------
    */

    'prediction' => [
        // Schedule for automatic predictions
        // Options: hourly, every4hours, every6hours
        'schedule' => env('PREDICTION_SCHEDULE', 'every4hours'),

        // Minimum confidence to generate trading signal
        'min_confidence' => env('MIN_SIGNAL_CONFIDENCE', 70),

        // Minimum price change percentage to send notification
        'min_price_change' => env('MIN_PRICE_CHANGE', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        // Send immediate notifications for strong signals
        'instant_signals' => env('INSTANT_SIGNAL_NOTIFICATIONS', false), // Disabled in favor of batch

        // Minimum confidence for instant notifications
        'instant_min_confidence' => env('INSTANT_NOTIFICATION_MIN_CONFIDENCE', 80),

        // Enable batch notifications (groups predictions to prevent spam)
        'batch_enabled' => env('BATCH_NOTIFICATIONS_ENABLED', true),

        // Batch interval in minutes (how often to send batch notifications)
        'batch_interval' => env('BATCH_NOTIFICATION_INTERVAL', 15),

        // Maximum predictions per message (to avoid Telegram 4096 char limit)
        'batch_max_per_message' => env('BATCH_MAX_PER_MESSAGE', 5),

        // Send daily summary
        'daily_summary' => env('DAILY_SUMMARY_ENABLED', true),

        // Time for daily summary
        'summary_time' => env('DAILY_SUMMARY_TIME', '08:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Settings
    |--------------------------------------------------------------------------
    */

    'validation' => [
        // Enable automatic validation of predictions
        'auto_validate' => env('AUTO_VALIDATE_PREDICTIONS', true),

        // Validation schedule: hourly, every4hours, daily
        'schedule' => env('VALIDATION_SCHEDULE', 'hourly'),

        // How many days back to validate
        'days' => env('VALIDATION_DAYS', 7),

        // Minimum confidence to include in validation
        'min_confidence' => env('VALIDATION_MIN_CONFIDENCE', 0),
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

        // Enable automated predictions
        'auto_predict' => env('AUTO_PREDICT', true),

        // Enable daily summary
        'daily_summary' => env('DAILY_SUMMARY_ENABLED', true),

        // Auto-train after data fetch (if enough new data)
        'train_after_fetch' => env('TRAIN_AFTER_FETCH', false),

        // Minimum candles required before training
        'min_candles_for_training' => env('MIN_CANDLES_FOR_TRAINING', 500),
    ],
];

