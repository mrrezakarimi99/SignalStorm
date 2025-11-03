<?php

namespace App\Jobs;

use App\Contracts\ITrainer;
use App\Contracts\INormalizer;
use App\Models\Candle;
use App\Models\ModelMetric;
use App\Services\NotificationService;
use App\Notifiers\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Train Model Job
 *
 * Trains ML model using stored candle data
 * Single Responsibility: Orchestrates the training process
 */
class TrainModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes
    public int $tries = 2;

    public function __construct(
        public string $symbol,
        public string $interval,
        public int $dataLimit = 1000,
        public array $config = [],
    ) {}

    /**
     * Execute the job
     */
    public function handle(
        ITrainer $trainer,
        INormalizer $normalizer,
        NotificationService $notificationService,
        TelegramNotifier $telegramNotifier
    ): void {
        Log::info('Starting model training', [
            'symbol' => $this->symbol,
            'interval' => $this->interval,
            'data_limit' => $this->dataLimit,
        ]);

        try {
            // Fetch candle data from database
            $candles = Candle::forSymbol($this->symbol)
                ->forInterval($this->interval)
                ->orderBy('open_time', 'asc')
                ->limit($this->dataLimit)
                ->get();

            if ($candles->isEmpty()) {
                Log::warning('No candles found for training', [
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                ]);

                // Send admin notification to private chat
                $telegramNotifier->sendToPrivate(
                    "⚠️ Training failed: No data available",
                    [
                        'job' => 'Train Model',
                        'symbol' => $this->symbol, 
                        'interval' => $this->interval
                    ]
                );
                return;
            }

            // Convert Eloquent models to DTOs
            $candleDTOs = $candles->map(function ($candle) {
                return new \App\DTOs\CandleData(
                    symbol: $candle->symbol,
                    interval: $candle->interval,
                    openTime: $candle->open_time,
                    open: (float) $candle->open,
                    high: (float) $candle->high,
                    low: (float) $candle->low,
                    close: (float) $candle->close,
                    volume: (float) $candle->volume,
                    closeTime: $candle->close_time,
                );
            })->toArray();

            // Normalize data
            $normalized = $normalizer->normalize($candleDTOs);

            Log::info('Data normalized', [
                'feature_count' => count($normalized['features']),
            ]);

            // Train model via Python service
            $result = $trainer->train($normalized['features'], array_merge([
                'symbol' => $this->symbol,
                'interval' => $this->interval,
                'metadata' => $normalized['metadata'],
            ], $this->config));

            if (!isset($result['success']) || !$result['success']) {
                throw new \Exception($result['error'] ?? 'Training failed');
            }

            // Store metrics in database
            if (isset($result['metrics'])) {
                foreach ($result['metrics'] as $metricName => $metricValue) {
                    ModelMetric::create([
                        'model_version' => $result['model_version'],
                        'metric_name' => $metricName,
                        'metric_value' => $metricValue,
                        'dataset_type' => $result['dataset_type'] ?? 'train',
                    ]);
                }
            }

            Log::info('Model training completed', [
                'model_version' => $result['model_version'],
                'metrics' => $result['metrics'] ?? [],
            ]);

            // Send success notification to admin private chat
            $telegramNotifier->sendToPrivate(
                "✅ Model training completed successfully!",
                [
                    'job' => 'Train Model',
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                    'model_version' => $result['model_version'],
                    'metrics' => $result['metrics'] ?? [],
                ]
            );

        } catch (\Exception $e) {
            Log::error('Model training failed', [
                'error' => $e->getMessage(),
                'symbol' => $this->symbol,
            ]);

            // Send error notification to admin private chat
            $telegramNotifier->sendToPrivate(
                "❌ Model training failed",
                [
                    'job' => 'Train Model',
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }
}

