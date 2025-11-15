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

            // Convert to CandleData DTOs for normalization
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

            // Debug: Check what we're passing to the normalizer
            Log::info("Debug: About to normalize", [
                'candleDTOs_count' => count($candleDTOs),
                'first_candle_type' => get_class($candleDTOs[0] ?? null),
                'first_candle_data' => $candleDTOs[0] ?? null,
            ]);

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

            // FIXED: Properly denormalize the prediction using inverse min-max scaling
            $rawPrediction = $predictions[0]; // This is a normalized value (0-1)
            $currentPrice = (float) $lastCandle->close;

            // Get normalization metadata for inverse scaling
            $minPrice = $metadata['min_price'] ?? $currentPrice * 0.95; // Fallback
            $priceRange = $metadata['price_range'] ?? $currentPrice * 0.1; // Fallback

            // Apply inverse min-max scaling: denormalized = (normalized * range) + min
            $predictedPrice = ($rawPrediction * $priceRange) + $minPrice;

            // Calculate price change percentage
            $priceChange = (($predictedPrice - $currentPrice) / $currentPrice) * 100;

            // Debug logging
            Log::info("Prediction debugging", [
                'symbol' => $this->symbol,
                'interval' => $this->interval,
                'current_price' => $currentPrice,
                'raw_prediction' => $rawPrediction,
                'min_price' => $minPrice,
                'price_range' => $priceRange,
                'predicted_price' => $predictedPrice,
                'price_change_percent' => $priceChange,
            ]);

            // SAFETY CHECKS: Detect unrealistic predictions
            $absChange = abs($priceChange);
            $isUnrealistic = $this->isUnrealisticPrediction($absChange, $this->interval);
            
            if ($isUnrealistic) {
                Log::warning("Unrealistic prediction detected", [
                    'symbol' => $this->symbol,
                    'interval' => $this->interval,
                    'price_change_percent' => $priceChange,
                    'current_price' => $currentPrice,
                    'predicted_price' => $predictedPrice,
                    'reason' => "Price change {$absChange}% is too high for {$this->interval} timeframe",
                ]);
                
                // For MVP safety: Convert unrealistic predictions to "Review needed" signal
                $signal = 'HOLD';
                $confidence = 50.0; // Low confidence for safety
                $originalPredictedPrice = $predictedPrice; // Store original for debugging
                $originalPriceChange = $priceChange; // Store original for debugging
                $predictedPrice = $currentPrice; // Reset to current price
                $priceChange = 0.0; // No change for safety
            } else {
                // Determine signal normally
                $signal = $this->determineSignal($priceChange);
                $confidence = $this->calculateConfidence($priceChange);
            }

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
                    'raw_prediction' => $rawPrediction,
                    'unrealistic' => $isUnrealistic,
                    'original_predicted_price' => $isUnrealistic ? $originalPredictedPrice ?? null : null,
                    'original_price_change' => $isUnrealistic ? $originalPriceChange ?? null : null,
                    'safety_threshold' => $this->interval,
                ],
            ]);

            Log::info("Prediction generated for {$this->symbol} {$this->interval}: {$signal} ({$confidence}% confidence)");

            // Don't send individual notifications - they will be batched
            // The batch sending is handled by a separate scheduled job
            // $signalService->sendSignal($prediction);

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

    /**
     * Check if prediction is unrealistic for MVP safety
     */
    protected function isUnrealisticPrediction(float $absChange, string $interval): bool
    {
        // Define realistic thresholds for different timeframes
        $thresholds = [
            '1m' => 1.0,   // 1% max for 1 minute
            '5m' => 2.0,   // 2% max for 5 minutes
            '15m' => 3.0,  // 3% max for 15 minutes
            '1h' => 5.0,   // 5% max for 1 hour
            '4h' => 10.0,  // 10% max for 4 hours
            '1d' => 20.0,  // 20% max for 1 day
            '1w' => 30.0,  // 30% max for 1 week
        ];

        $threshold = $thresholds[$interval] ?? 5.0; // Default to 5% for unknown intervals

        return $absChange > $threshold;
    }
}

