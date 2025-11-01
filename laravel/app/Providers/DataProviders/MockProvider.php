<?php

namespace App\Providers\DataProviders;

use App\Contracts\IDataProvider;
use App\DTOs\CandleData;
use Illuminate\Support\Facades\Log;

/**
 * Mock Data Provider for Testing
 *
 * Generates synthetic candle data
 * Useful for development and testing without API dependencies
 */
class MockProvider implements IDataProvider
{
    /**
     * Generate mock historical candle data
     */
    public function fetchHistorical(string $symbol, string $interval, int $limit): array
    {
        Log::info('Generating mock candle data', [
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => $limit,
        ]);

        $candles = [];
        $basePrice = 50000; // Starting price
        $baseVolume = 100;
        $currentTime = time() * 1000; // Convert to milliseconds

        // Calculate interval in milliseconds
        $intervalMs = $this->intervalToMilliseconds($interval);

        for ($i = $limit - 1; $i >= 0; $i--) {
            $openTime = $currentTime - ($i * $intervalMs);
            $closeTime = $openTime + $intervalMs - 1;

            // Generate realistic price movements
            $open = $basePrice + (rand(-1000, 1000) / 10);
            $high = $open + (rand(0, 500) / 10);
            $low = $open - (rand(0, 500) / 10);
            $close = $low + (rand(0, (int)(($high - $low) * 10)) / 10);
            $volume = $baseVolume + (rand(0, 50));

            // Update base price for next candle (trending)
            $basePrice = $close + (rand(-100, 100) / 10);

            $candles[] = new CandleData(
                symbol: $symbol,
                interval: $interval,
                openTime: (int) $openTime,
                open: $open,
                high: $high,
                low: $low,
                close: $close,
                volume: $volume,
                closeTime: (int) $closeTime,
            );
        }

        return $candles;
    }

    /**
     * Mock realtime subscription
     * In reality, would periodically call callback with new data
     */
    public function subscribeRealtime(string $symbol, callable $callback): void
    {
        Log::info('Mock realtime subscription', ['symbol' => $symbol]);

        // In a real implementation, this would:
        // 1. Start a loop that generates new candles periodically
        // 2. Call $callback with each new candle
        // 3. Run in a separate process/queue

        // Example pseudo-code:
        // while (true) {
        //     sleep($this->intervalToSeconds($interval));
        //     $newCandle = $this->generateMockCandle($symbol, $interval);
        //     $callback($newCandle);
        // }
    }

    public function getName(): string
    {
        return 'mock';
    }

    /**
     * Convert interval string to milliseconds
     */
    private function intervalToMilliseconds(string $interval): int
    {
        $unit = substr($interval, -1);
        $value = (int) substr($interval, 0, -1);

        return match($unit) {
            'm' => $value * 60 * 1000,          // Minutes
            'h' => $value * 60 * 60 * 1000,     // Hours
            'd' => $value * 24 * 60 * 60 * 1000, // Days
            'w' => $value * 7 * 24 * 60 * 60 * 1000, // Weeks
            default => 60 * 1000, // Default to 1 minute
        };
    }
}

