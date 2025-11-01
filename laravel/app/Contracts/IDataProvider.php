<?php

namespace App\Contracts;

use App\DTOs\CandleData;

/**
 * Interface IDataProvider
 *
 * Abstraction for data providers (Binance, Mock, etc.)
 * Follows Dependency Inversion Principle - depend on abstraction, not concretions
 */
interface IDataProvider
{
    /**
     * Fetch historical candle data
     *
     * @param string $symbol Trading pair (e.g., BTCUSDT)
     * @param string $interval Timeframe (1m, 5m, 1h, 1d)
     * @param int $limit Number of candles to fetch
     * @return array<CandleData>
     */
    public function fetchHistorical(string $symbol, string $interval, int $limit): array;

    /**
     * Subscribe to realtime data feed
     *
     * @param string $symbol Trading pair
     * @param callable $callback Function to call with new data
     * @return void
     */
    public function subscribeRealtime(string $symbol, callable $callback): void;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string;
}

