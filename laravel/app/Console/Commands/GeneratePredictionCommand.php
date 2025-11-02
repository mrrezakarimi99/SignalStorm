<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePredictionJob;
use Illuminate\Console\Command;

class GeneratePredictionCommand extends Command
{
    protected $signature = 'predict:generate
                            {symbol : Trading symbol (e.g., BTCUSDT)}
                            {interval : Candle interval (e.g., 1h, 4h, 1d)}
                            {--model= : Specific model version to use}';

    protected $description = 'Generate price prediction for a symbol';

    public function handle(): int
    {
        $symbol = $this->argument('symbol');
        $interval = $this->argument('interval');
        $modelVersion = $this->option('model');

        $this->info("Queuing prediction job for {$symbol} {$interval}...");

        GeneratePredictionJob::dispatch($symbol, $interval, $modelVersion);

        $this->info("✓ Prediction job queued successfully");

        return self::SUCCESS;
    }
}

