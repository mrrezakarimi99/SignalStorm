<?php

namespace App\Console\Commands;

use App\Jobs\FetchHistoricalDataJob;
use App\Jobs\TrainModelJob;
use App\Models\Candle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * Run Complete Pipeline
 *
 * Fetches data and then trains models for all configured pairs
 */
class RunPipelineCommand extends Command
{
    protected $signature = 'pipeline:run
                            {--fetch-limit= : Number of candles to fetch}
                            {--train-limit= : Number of candles for training}
                            {--epochs= : Training epochs}
                            {--batch-size= : Batch size}
                            {--skip-fetch : Skip data fetching}
                            {--skip-train : Skip model training}';

    protected $description = 'Run complete data pipeline: fetch data and train models';

    public function handle(): int
    {
        $this->info('🚀 Starting SignalStorm Pipeline...');
        $this->newLine();

        $pairs = config('trading.pairs', []);

        if (empty($pairs)) {
            $this->error('No trading pairs configured in config/trading.php');
            return Command::FAILURE;
        }

        $enabledPairs = collect($pairs)->filter(fn($config) => $config['enabled'] ?? false);

        if ($enabledPairs->isEmpty()) {
            $this->error('No enabled trading pairs found in config/trading.php');
            return Command::FAILURE;
        }

        // Step 1: Fetch Data
        if (!$this->option('skip-fetch')) {
            $this->info('📥 Step 1: Fetching historical data...');
            $this->call('data:fetch-all', [
                '--limit' => $this->option('fetch-limit'),
            ]);
            $this->newLine();

            // Wait a bit for jobs to process
            $this->info('⏳ Waiting for fetch jobs to complete...');
            $this->comment('Tip: In production, use supervisord to manage queue workers');
            sleep(5);
        } else {
            $this->info('⏭️  Skipping data fetch');
            $this->newLine();
        }

        // Step 2: Train Models
        if (!$this->option('skip-train')) {
            $this->info('🧠 Step 2: Training models...');

            // Check if we have enough data
            $minCandles = config('trading.automation.min_candles_for_training', 500);
            $this->checkDataAvailability($enabledPairs, $minCandles);

            $this->call('model:train-all', [
                '--limit' => $this->option('train-limit'),
                '--epochs' => $this->option('epochs'),
                '--batch-size' => $this->option('batch-size'),
            ]);
            $this->newLine();
        } else {
            $this->info('⏭️  Skipping model training');
            $this->newLine();
        }

        $this->info('✅ Pipeline completed!');
        $this->newLine();

        $this->comment('Next steps:');
        $this->line('  1. Monitor job status: php artisan queue:monitor');
        $this->line('  2. Check logs: tail -f storage/logs/laravel.log');
        $this->line('  3. View trained models in storage/models/');

        return Command::SUCCESS;
    }

    private function checkDataAvailability($pairs, int $minCandles): void
    {
        $this->info('Checking data availability...');

        foreach ($pairs as $symbol => $config) {
            foreach ($config['intervals'] ?? [] as $interval) {
                $count = Candle::forSymbol($symbol)
                    ->forInterval($interval)
                    ->count();

                if ($count < $minCandles) {
                    $this->warn("⚠️  {$symbol} @ {$interval}: Only {$count} candles (minimum: {$minCandles})");
                } else {
                    $this->info("✓ {$symbol} @ {$interval}: {$count} candles available");
                }
            }
        }

        $this->newLine();
    }
}

