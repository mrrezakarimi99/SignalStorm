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

        // Group models by symbol+interval and get only the latest version
        $latestModels = [];
        foreach ($models as $modelVersion) {
            // Parse model version: lstm_SYMBOL_INTERVAL_TIMESTAMP
            $parts = explode('_', $modelVersion);

            if (count($parts) < 4) {
                continue;
            }

            $symbol = $parts[1];
            $interval = $parts[2];
            $timestamp = $parts[3];

            // Filter by symbols if specified
            if (!empty($symbols) && !in_array($symbol, $symbols)) {
                continue;
            }

            // Filter by intervals if specified
            if (!empty($intervals) && !in_array($interval, $intervals)) {
                continue;
            }

            $key = "{$symbol}_{$interval}";

            // Keep only the latest model version (highest timestamp) per symbol+interval
            if (!isset($latestModels[$key]) || $timestamp > $latestModels[$key]['timestamp']) {
                $latestModels[$key] = [
                    'version' => $modelVersion,
                    'timestamp' => $timestamp,
                    'symbol' => $symbol,
                    'interval' => $interval,
                ];
            }
        }

        // Dispatch jobs only for latest models
        foreach ($latestModels as $model) {
            $this->line("Queuing prediction for {$model['symbol']} {$model['interval']} using latest model {$model['version']}");
            GeneratePredictionJob::dispatch($model['symbol'], $model['interval'], $model['version']);
            $queued++;
        }

        $this->info("✓ Queued {$queued} prediction jobs (using latest models only)");

        return self::SUCCESS;
    }
}

