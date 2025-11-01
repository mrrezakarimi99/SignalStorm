<?php

namespace App\Console\Commands;

use App\Jobs\TrainModelJob;
use Illuminate\Console\Command;

/**
 * Train Model Command
 *
 * Triggers ML model training job
 */
class TrainModelCommand extends Command
{
    protected $signature = 'model:train
                            {symbol : Trading pair (e.g., BTCUSDT)}
                            {interval : Timeframe (1m, 5m, 1h, 1d)}
                            {--limit=1000 : Number of candles to use for training}
                            {--epochs=50 : Training epochs}
                            {--batch-size=32 : Batch size}';

    protected $description = 'Train ML model using historical data';

    public function handle(): int
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');
        $limit = (int) $this->option('limit');

        $config = [
            'epochs' => (int) $this->option('epochs'),
            'batch_size' => (int) $this->option('batch-size'),
        ];

        $this->info("Dispatching training job for {$symbol} ({$interval})...");
        $this->info("Config: " . json_encode($config));

        TrainModelJob::dispatch($symbol, $interval, $limit, $config);

        $this->info('Training job dispatched! Check queue worker for progress.');

        return Command::SUCCESS;
    }
}

