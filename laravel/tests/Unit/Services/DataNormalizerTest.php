<?php

namespace Tests\Unit\Services;

use App\DTOs\CandleData;
use App\Services\DataNormalizer;
use Tests\TestCase;

/**
 * Test Data Normalizer
 */
class DataNormalizerTest extends TestCase
{
    private DataNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DataNormalizer();
    }

    public function test_normalize_empty_data(): void
    {
        $result = $this->normalizer->normalize([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('features', $result);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEmpty($result['features']);
    }

    public function test_normalize_candle_data(): void
    {
        $candles = [
            new CandleData(
                symbol: 'BTCUSDT',
                interval: '1h',
                openTime: 1000000,
                open: 50000,
                high: 51000,
                low: 49000,
                close: 50500,
                volume: 100,
                closeTime: 1003599,
            ),
            new CandleData(
                symbol: 'BTCUSDT',
                interval: '1h',
                openTime: 1003600,
                open: 50500,
                high: 52000,
                low: 50000,
                close: 51500,
                volume: 150,
                closeTime: 1007199,
            ),
        ];

        $result = $this->normalizer->normalize($candles);

        $this->assertCount(2, $result['features']);
        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals('BTCUSDT', $result['metadata']['symbol']);
        $this->assertEquals('1h', $result['metadata']['interval']);

        // Check first feature
        $feature = $result['features'][0];
        $this->assertArrayHasKey('open_norm', $feature);
        $this->assertArrayHasKey('close_norm', $feature);
        $this->assertArrayHasKey('volume_norm', $feature);

        // Normalized values should be between 0 and 1
        $this->assertGreaterThanOrEqual(0, $feature['open_norm']);
        $this->assertLessThanOrEqual(1, $feature['open_norm']);
    }

    public function test_denormalize_predictions(): void
    {
        $normalized = [0.5, 0.75];
        $metadata = [
            'min_price' => 50000,
            'price_range' => 2000,
        ];

        $result = $this->normalizer->denormalize($normalized, $metadata);

        $this->assertCount(2, $result);
        $this->assertEquals(51000, $result[0]); // 0.5 * 2000 + 50000
        $this->assertEquals(51500, $result[1]); // 0.75 * 2000 + 50000
    }
}

