<?php

namespace App\Jobs;

use App\Contracts\IDataProvider;
use App\Models\Candle;
use App\Services\NotificationService;
use App\Notifiers\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fetch Historical Data Job
 *
 * Fetches historical candle data and stores in database
 * Single Responsibility: Only handles data fetching and storage
 */
class FetchHistoricalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;

    public function __construct(
        public string $symbol,
        public string $interval,
        public int $limit = 100,
    ) {}

    /**
     * Execute the job
     */
    public function handle(
        IDataProvider $dataProvider, 
        NotificationService $notificationService,
        TelegramNotifier $telegramNotifier
    ): void
    {
        Log::info('Fetching historical data', [
            'symbol' => $this->symbol,
            'interval' => $this->interval,
            'limit' => $this->limit,
            'provider' => $dataProvider->getName(),
        ]);

        try {
            // Fetch data from provider
            $candles = $dataProvider->fetchHistorical(
                $this->symbol,
                $this->interval,
                $this->limit
            );

            if (empty($candles)) {
                Log::warning('No candles fetched', [
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                ]);
                return;
            }

            // Store candles in database
            $stored = 0;
            foreach ($candles as $candle) {
                Candle::updateOrCreate(
                    [
                        'symbol' => $candle->symbol,
                        'interval' => $candle->interval,
                        'open_time' => $candle->openTime,
                    ],
                    [
                        'open' => $candle->open,
                        'high' => $candle->high,
                        'low' => $candle->low,
                        'close' => $candle->close,
                        'volume' => $candle->volume,
                        'close_time' => $candle->closeTime,
                    ]
                );
                $stored++;
            }

            Log::info('Historical data fetched and stored', [
                'symbol' => $this->symbol,
                'interval' => $this->interval,
                'count' => $stored,
            ]);

            // Send admin notification to private chat
            $telegramNotifier->sendToPrivate(
                "✅ Historical data fetched successfully",
                [
                    'job' => 'Fetch Historical Data',
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                    'candles_stored' => $stored,
                    'provider' => $dataProvider->getName(),
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to fetch historical data', [
                'error' => $e->getMessage(),
                'symbol' => $this->symbol,
            ]);

            // Send error alert to admin private chat
            $telegramNotifier->sendToPrivate(
                "❌ Failed to fetch historical data for {$this->symbol}",
                [
                    'job' => 'Fetch Historical Data',
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
}

