<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePredictionJob;
use App\Models\ModelMetric;
use Illuminate\Console\Command;

class GenerateAllPredictionsCommand extends Command
{
    protected $signature = 'predict:all
                            {--symbols=* : Specific symbols to predict (default: all trained models)}
                            {--intervals=* : Specific intervals to predict (default: all trained models)}';

    protected $description = 'Generate predictions for all trained models';

    public function handle(): int
    {
        $symbols = $this->option('symbols');
        $intervals = $this->option('intervals');

        $this->info("Finding all trained models...");

        // Get all unique model versions
        $models = ModelMetric::select('model_version')
            ->distinct()
            ->get()
            ->pluck('model_version');

        if ($models->isEmpty()) {
            $this->error("No trained models found!");
            return self::FAILURE;
        }

        $queued = 0;

        foreach ($models as $modelVersion) {
            // Parse model version: lstm_SYMBOL_INTERVAL_TIMESTAMP
            $parts = explode('_', $modelVersion);

            if (count($parts) < 3) {
                continue;
            }

            $symbol = $parts[1];
            $interval = $parts[2];

            // Filter by symbols if specified
            if (!empty($symbols) && !in_array($symbol, $symbols)) {
                continue;
            }

            // Filter by intervals if specified
            if (!empty($intervals) && !in_array($interval, $intervals)) {
                continue;
            }

            $this->line("Queuing prediction for {$symbol} {$interval}...");
            GeneratePredictionJob::dispatch($symbol, $interval, $modelVersion);
            $queued++;
        }

        $this->info("✓ Queued {$queued} prediction jobs");

        return self::SUCCESS;
    }
}

