<?php

namespace Tests\Unit\Providers;

use App\Providers\DataProviders\MockProvider;
use Tests\TestCase;

/**
 * Test Mock Data Provider
 */
class MockProviderTest extends TestCase
{
    private MockProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new MockProvider();
    }

    public function test_provider_name(): void
    {
        $this->assertEquals('mock', $this->provider->getName());
    }

    public function test_fetch_historical_data(): void
    {
        $candles = $this->provider->fetchHistorical('BTCUSDT', '1h', 10);

        $this->assertCount(10, $candles);

        $candle = $candles[0];
        $this->assertEquals('BTCUSDT', $candle->symbol);
        $this->assertEquals('1h', $candle->interval);
        $this->assertGreaterThan(0, $candle->open);
        $this->assertGreaterThan(0, $candle->high);
        $this->assertGreaterThan(0, $candle->low);
        $this->assertGreaterThan(0, $candle->close);
        $this->assertGreaterThan(0, $candle->volume);

        // High should be >= Low
        $this->assertGreaterThanOrEqual($candle->low, $candle->high);
    }

    public function test_fetch_different_limits(): void
    {
        $candles50 = $this->provider->fetchHistorical('BTCUSDT', '1h', 50);
        $candles100 = $this->provider->fetchHistorical('BTCUSDT', '1h', 100);

        $this->assertCount(50, $candles50);
        $this->assertCount(100, $candles100);
    }
}

