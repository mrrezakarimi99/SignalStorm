<?php

namespace App\Contracts;

use App\DTOs\CandleData;

/**
 * Interface INormalizer
 *
 * Abstraction for data normalization
 * Single Responsibility: Only handles data transformation
 */
interface INormalizer
{
    /**
     * Normalize candle data for ML training
     *
     * @param array<CandleData> $candles
     * @return array Normalized data ready for ML
     */
    public function normalize(array $candles): array;

    /**
     * Denormalize predictions back to original scale
     *
     * @param array $normalized
     * @param array $metadata Normalization metadata
     * @return array
     */
    public function denormalize(array $normalized, array $metadata): array;
}

