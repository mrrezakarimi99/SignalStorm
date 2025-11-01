<?php

namespace App\DTOs;

/**
 * Data Transfer Object for Candle Data
 * Immutable value object
 */
class CandleData
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $interval,
        public readonly int $openTime,
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly float $volume,
        public readonly int $closeTime,
    ) {}

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            symbol: $data['symbol'] ?? '',
            interval: $data['interval'] ?? '',
            openTime: $data['open_time'] ?? $data['openTime'] ?? 0,
            open: (float) ($data['open'] ?? 0),
            high: (float) ($data['high'] ?? 0),
            low: (float) ($data['low'] ?? 0),
            close: (float) ($data['close'] ?? 0),
            volume: (float) ($data['volume'] ?? 0),
            closeTime: $data['close_time'] ?? $data['closeTime'] ?? 0,
        );
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'interval' => $this->interval,
            'open_time' => $this->openTime,
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
            'close_time' => $this->closeTime,
        ];
    }
}

