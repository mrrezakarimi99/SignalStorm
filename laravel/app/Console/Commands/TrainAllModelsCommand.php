<?php

namespace App\Console\Commands;

use App\Jobs\TrainModelJob;
use Illuminate\Console\Command;

/**
 * Train Models for All Configured Pairs
 *
 * Automatically trains models for all enabled trading pairs
 */
class TrainAllModelsCommand extends Command
{
    protected $signature = 'model:train-all
                            {--limit= : Override data limit for training}
                            {--epochs= : Override training epochs}
                            {--batch-size= : Override batch size}';

    protected $description = 'Train models for all configured trading pairs';

    public function handle(): int
    {
        $pairs = config('trading.pairs', []);
        $dataLimit = $this->option('limit') ?? config('trading.training.data_limit', 1000);
        $epochs = $this->option('epochs') ?? config('trading.training.epochs', 50);
        $batchSize = $this->option('batch-size') ?? config('trading.training.batch_size', 32);

        if (empty($pairs)) {
            $this->error('No trading pairs configured in config/trading.php');
            return Command::FAILURE;
        }

        $config = [
            'epochs' => (int) $epochs,
            'batch_size' => (int) $batchSize,
        ];

        $jobCount = 0;

        foreach ($pairs as $symbol => $pairConfig) {
            if (!($pairConfig['enabled'] ?? false)) {
                $this->info("Skipping disabled pair: {$symbol}");
                continue;
            }

            $intervals = $pairConfig['intervals'] ?? [];

            foreach ($intervals as $interval) {
                $this->info("Dispatching training job: {$symbol} @ {$interval}");

                TrainModelJob::dispatch($symbol, $interval, (int) $dataLimit, $config);
                $jobCount++;
            }
        }

        $this->info("✅ Dispatched {$jobCount} training jobs successfully!");
        $this->comment('Jobs are queued. Make sure queue worker is running: php artisan queue:work');

        return Command::SUCCESS;
    }
}
