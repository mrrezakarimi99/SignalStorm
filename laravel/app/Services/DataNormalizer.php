<?php

namespace App\Services;

use App\Contracts\INormalizer;
use App\DTOs\CandleData;

/**
 * Data Normalizer Service
 *
 * Single Responsibility: Transforms raw candle data for ML training
 * Handles feature engineering and scaling
 */
class DataNormalizer implements INormalizer
{
    /**
     * Normalize candle data for ML training
     *
     * Applies:
     * - Feature extraction (returns, volatility, etc.)
     * - Min-Max scaling
     * - Technical indicators
     */
    public function normalize(array $candles): array
    {
        if (empty($candles)) {
            return ['features' => [], 'metadata' => []];
        }

        $features = [];
        $prices = array_map(fn($c) => $c->close, $candles);

        // Calculate metadata for normalization
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $priceRange = $maxPrice - $minPrice;

        $volumes = array_map(fn($c) => $c->volume, $candles);
        $minVolume = min($volumes);
        $maxVolume = max($volumes);
        $volumeRange = $maxVolume - $minVolume;

        foreach ($candles as $i => $candle) {
            $feature = [
                // Normalized OHLC
                'open_norm' => $this->minMaxScale($candle->open, $minPrice, $priceRange),
                'high_norm' => $this->minMaxScale($candle->high, $minPrice, $priceRange),
                'low_norm' => $this->minMaxScale($candle->low, $minPrice, $priceRange),
                'close_norm' => $this->minMaxScale($candle->close, $minPrice, $priceRange),
                'volume_norm' => $this->minMaxScale($candle->volume, $minVolume, $volumeRange),

                // Raw values
                'open' => $candle->open,
                'high' => $candle->high,
                'low' => $candle->low,
                'close' => $candle->close,
                'volume' => $candle->volume,

                // Technical features
                'price_change' => $i > 0 ?
                    ($candle->close - $candles[$i - 1]->close) / $candles[$i - 1]->close : 0,
                'candle_range' => ($candle->high - $candle->low) / $candle->close,
                'body_size' => abs($candle->close - $candle->open) / $candle->close,
            ];

            // Add moving averages if enough data
            if ($i >= 5) {
                $feature['sma_5'] = $this->calculateSMA($candles, $i, 5);
            }

            if ($i >= 10) {
                $feature['sma_10'] = $this->calculateSMA($candles, $i, 10);
            }

            $features[] = $feature;
        }

        $metadata = [
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'price_range' => $priceRange,
            'min_volume' => $minVolume,
            'max_volume' => $maxVolume,
            'volume_range' => $volumeRange,
            'count' => count($candles),
            'symbol' => $candles[0]->symbol ?? null,
            'interval' => $candles[0]->interval ?? null,
        ];

        return [
            'features' => $features,
            'metadata' => $metadata,
        ];
    }

    /**
     * Denormalize predictions back to original scale
     */
    public function denormalize(array $normalized, array $metadata): array
    {
        $denormalized = [];

        $minPrice = $metadata['min_price'] ?? 0;
        $priceRange = $metadata['price_range'] ?? 1;

        foreach ($normalized as $value) {
            if (is_array($value) && isset($value['price_norm'])) {
                $denormalized[] = [
                    'price' => $this->minMaxDenormalize($value['price_norm'], $minPrice, $priceRange),
                    'confidence' => $value['confidence'] ?? null,
                ];
            } else {
                // Simple scalar denormalization
                $denormalized[] = $this->minMaxDenormalize($value, $minPrice, $priceRange);
            }
        }

        return $denormalized;
    }

    /**
     * Min-Max scaling: (x - min) / (max - min)
     */
    private function minMaxScale(float $value, float $min, float $range): float
    {
        if ($range == 0) {
            return 0;
        }

        return ($value - $min) / $range;
    }

    /**
     * Reverse Min-Max scaling: x * (max - min) + min
     */
    private function minMaxDenormalize(float $normalized, float $min, float $range): float
    {
        return ($normalized * $range) + $min;
    }

    /**
     * Calculate Simple Moving Average
     */
    private function calculateSMA(array $candles, int $currentIndex, int $period): float
    {
        $sum = 0;
        $startIndex = max(0, $currentIndex - $period + 1);

        for ($i = $startIndex; $i <= $currentIndex; $i++) {
            $sum += $candles[$i]->close;
        }

        return $sum / min($period, $currentIndex + 1);
    }
}

