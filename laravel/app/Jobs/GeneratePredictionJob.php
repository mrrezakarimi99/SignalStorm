<?php

namespace App\Jobs;

use App\Models\Candle;
use App\Models\Prediction;
use App\Models\ModelMetric;
use App\Services\DataNormalizer;
use App\Services\TradingSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeneratePredictionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    protected string $symbol;
    protected string $interval;
    protected ?string $modelVersion;

    public function __construct(string $symbol, string $interval, ?string $modelVersion = null)
    {
        $this->symbol = $symbol;
        $this->interval = $interval;
        $this->modelVersion = $modelVersion;
    }

    public function handle(DataNormalizer $normalizer, TradingSignalService $signalService): void
    {
        Log::info("Generating prediction for {$this->symbol} {$this->interval}");

        try {
            // Get the latest model version if not specified
            if (!$this->modelVersion) {
                $this->modelVersion = $this->getLatestModelVersion();
            }

            if (!$this->modelVersion) {
                Log::error("No trained model found for {$this->symbol} {$this->interval}");
                return;
            }

            // Fetch recent candles for prediction input
            $candles = Candle::forSymbol($this->symbol)
                ->forInterval($this->interval)
                ->orderBy('open_time', 'desc')
                ->limit(100)
                ->get()
                ->sortBy('open_time')
                ->values();

            if ($candles->count() < 10) {
                Log::error("Insufficient data for prediction: {$candles->count()} candles");
                return;
            }

            // Normalize the data
            $normalized = $normalizer->normalize($candles->all());

            if (empty($normalized)) {
                Log::error("Failed to normalize candle data");
                return;
            }

            // Extract features array (DataNormalizer returns ['features' => [...], 'metadata' => [...]])
            $features = $normalized['features'] ?? $normalized;

            // Call Python trainer service for prediction
            $trainerUrl = config('services.trainer.url', 'http://python-trainer:8001');
            $response = Http::timeout(60)->post("{$trainerUrl}/predict", [
                'features' => $features,
                'model_version' => $this->modelVersion,
            ]);

            if (!$response->successful()) {
                Log::error("Prediction request failed: {$response->body()}");
                return;
            }

            $result = $response->json();

            if (!$result['success']) {
                Log::error("Prediction failed: " . ($result['error'] ?? 'Unknown error'));
                return;
            }

            // Get the last candle to calculate predicted price
            $lastCandle = $candles->last();
            $predictions = $result['predictions'][0] ?? [];

            if (empty($predictions)) {
                Log::error("No predictions returned from model");
                return;
            }

            // Denormalize the prediction
            $predictedChange = $predictions[0]; // Predicted normalized close price change
            $currentPrice = $lastCandle->close;

            // Simple denormalization (adjust based on your normalization strategy)
            $predictedPrice = $currentPrice * (1 + $predictedChange);

            // Calculate price change percentage
            $priceChange = (($predictedPrice - $currentPrice) / $currentPrice) * 100;

            // Determine signal
            $signal = $this->determineSignal($priceChange);
            $confidence = $this->calculateConfidence($priceChange);

            // Store prediction
            $prediction = Prediction::create([
                'symbol' => $this->symbol,
                'interval' => $this->interval,
                'model_version' => $this->modelVersion,
                'current_price' => $currentPrice,
                'predicted_price' => $predictedPrice,
                'price_change_percent' => $priceChange,
                'signal' => $signal,
                'confidence' => $confidence,
                'prediction_time' => now(),
                'target_time' => $this->calculateTargetTime(),
                'metadata' => [
                    'candles_used' => $candles->count(),
                    'last_candle_time' => $lastCandle->open_time,
                    'raw_prediction' => $predictedChange,
                ],
            ]);

            Log::info("Prediction generated for {$this->symbol} {$this->interval}: {$signal} ({$confidence}% confidence)");

            // Send Telegram notification via TradingSignalService
            $signalService->sendSignal($prediction);

        } catch (\Exception $e) {
            Log::error("Error generating prediction: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function getLatestModelVersion(): ?string
    {
        // Find the latest model for this symbol and interval
        $pattern = "lstm_{$this->symbol}_{$this->interval}_%";

        $metric = ModelMetric::where('model_version', 'like', $pattern)
            ->orderBy('created_at', 'desc')
            ->first();

        return $metric?->model_version;
    }

    protected function calculateTargetTime(): \DateTime
    {
        // Calculate when the prediction is for
        $intervalMap = [
            '1m' => '+1 minute',
            '5m' => '+5 minutes',
            '15m' => '+15 minutes',
            '1h' => '+1 hour',
            '4h' => '+4 hours',
            '1d' => '+1 day',
            '1w' => '+1 week',
        ];

        $modifier = $intervalMap[$this->interval] ?? '+1 hour';
        return now()->modify($modifier);
    }

    protected function determineSignal(float $priceChange): string
    {
        $threshold = 0.5; // 0.5% change threshold

        if ($priceChange > $threshold) {
            return 'BUY';
        } elseif ($priceChange < -$threshold) {
            return 'SELL';
        }

        return 'HOLD';
    }

    protected function calculateConfidence(float $priceChange): float
    {
        // Simple confidence calculation based on prediction magnitude
        // Stronger predictions = higher confidence
        $absChange = abs($priceChange);

        if ($absChange >= 5) {
            return 95.0;
        } elseif ($absChange >= 3) {
            return 85.0;
        } elseif ($absChange >= 2) {
            return 75.0;
        } elseif ($absChange >= 1) {
            return 65.0;
        } elseif ($absChange >= 0.5) {
            return 55.0;
        }

        return 50.0;
    }
}

