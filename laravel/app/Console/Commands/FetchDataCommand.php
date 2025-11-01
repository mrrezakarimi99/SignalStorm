<?php

namespace App\Console\Commands;

use App\Jobs\FetchHistoricalDataJob;
use Illuminate\Console\Command;

/**
 * Fetch Historical Data Command
 *
 * Triggers data fetching job
 */
class FetchDataCommand extends Command
{
    protected $signature = 'data:fetch
                            {symbol : Trading pair (e.g., BTCUSDT)}
                            {interval : Timeframe (1m, 5m, 1h, 1d)}
                            {limit=100 : Number of candles to fetch}';

    protected $description = 'Fetch historical candle data from data provider';

    public function handle(): int
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');
        $limit = (int) $this->argument('limit');

        $this->info("Dispatching job to fetch {$limit} candles for {$symbol} ({$interval})...");

        FetchHistoricalDataJob::dispatch($symbol, $interval, $limit);

        $this->info('Job dispatched successfully! Check queue worker for progress.');

        return Command::SUCCESS;
    }
}

