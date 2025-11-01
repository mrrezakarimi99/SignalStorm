<?php

namespace App\Providers\DataProviders;

use App\Contracts\IDataProvider;
use App\DTOs\CandleData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Binance Data Provider
 *
 * Single Responsibility: Only handles Binance API integration
 * Open/Closed: Can be extended without modifying core logic
 */
class BinanceProvider implements IDataProvider
{
    private string $baseUrl;
    private ?string $apiKey;
    private ?string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.binance.base_url', 'https://api.binance.com');
        $this->apiKey = config('services.binance.api_key');
        $this->apiSecret = config('services.binance.api_secret');
    }

    /**
     * Fetch historical klines from Binance
     */
    public function fetchHistorical(string $symbol, string $interval, int $limit): array
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/v3/klines", [
                'symbol' => $symbol,
                'interval' => $interval,
                'limit' => $limit,
            ]);

            if (!$response->successful()) {
                Log::error('Binance API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            return array_map(function ($kline) use ($symbol, $interval) {
                return new CandleData(
                    symbol: $symbol,
                    interval: $interval,
                    openTime: $kline[0],
                    open: (float) $kline[1],
                    high: (float) $kline[2],
                    low: (float) $kline[3],
                    close: (float) $kline[4],
                    volume: (float) $kline[5],
                    closeTime: $kline[6],
                );
            }, $data);

        } catch (\Exception $e) {
            Log::error('Failed to fetch Binance data', [
                'error' => $e->getMessage(),
                'symbol' => $symbol,
            ]);
            return [];
        }
    }

    /**
     * Subscribe to Binance WebSocket for realtime data
     *
     * Note: This is a simplified implementation
     * In production, use a proper WebSocket library like Ratchet
     */
    public function subscribeRealtime(string $symbol, callable $callback): void
    {
        // WebSocket URL format: wss://stream.binance.com:9443/ws/{symbol}@kline_{interval}
        $wsUrl = 'wss://stream.binance.com:9443/ws/' . strtolower($symbol) . '@kline_1m';

        Log::info('Binance WebSocket subscription initiated', [
            'symbol' => $symbol,
            'url' => $wsUrl,
        ]);

        // In a real implementation, you would:
        // 1. Use a WebSocket client library
        // 2. Run this in a separate process/queue job
        // 3. Handle reconnection logic
        // 4. Parse incoming messages and call $callback with CandleData

        // Pseudo-code for reference:
        // $client = new WebSocketClient($wsUrl);
        // $client->on('message', function($message) use ($callback) {
        //     $data = json_decode($message, true);
        //     $candle = $this->parseWebSocketMessage($data);
        //     $callback($candle);
        // });
        // $client->connect();
    }

    public function getName(): string
    {
        return 'binance';
    }

    /**
     * Parse WebSocket message to CandleData
     */
    private function parseWebSocketMessage(array $data): CandleData
    {
        $k = $data['k'] ?? [];

        return new CandleData(
            symbol: $k['s'] ?? '',
            interval: $k['i'] ?? '',
            openTime: $k['t'] ?? 0,
            open: (float) ($k['o'] ?? 0),
            high: (float) ($k['h'] ?? 0),
            low: (float) ($k['l'] ?? 0),
            close: (float) ($k['c'] ?? 0),
            volume: (float) ($k['v'] ?? 0),
            closeTime: $k['T'] ?? 0,
        );
    }
}

