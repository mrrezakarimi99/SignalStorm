<?php

namespace App\Console\Commands;

use App\Jobs\FetchHistoricalDataJob;
use Illuminate\Console\Command;

/**
 * Fetch Data for All Configured Pairs
 *
 * Automatically fetches data for all enabled trading pairs
 */
class FetchAllDataCommand extends Command
{
    protected $signature = 'data:fetch-all
                            {--limit= : Override default fetch limit}';

    protected $description = 'Fetch historical data for all configured trading pairs';

    public function handle(): int
    {
        $pairs = config('trading.pairs', []);
        $limit = $this->option('limit') ?? config('trading.fetch.limit', 100);

        if (empty($pairs)) {
            $this->error('No trading pairs configured in config/trading.php');
            return Command::FAILURE;
        }

        $jobCount = 0;

        foreach ($pairs as $symbol => $pairConfig) {
            if (!($pairConfig['enabled'] ?? false)) {
                $this->info("Skipping disabled pair: {$symbol}");
                continue;
            }

            $intervals = $pairConfig['intervals'] ?? [];

            foreach ($intervals as $interval) {
                $this->info("Dispatching fetch job: {$symbol} @ {$interval}");

                FetchHistoricalDataJob::dispatch($symbol, $interval, (int) $limit);
                $jobCount++;
            }
        }

        $this->info("✅ Dispatched {$jobCount} fetch jobs successfully!");
        $this->comment('Jobs are queued. Make sure queue worker is running: php artisan queue:work');

        return Command::SUCCESS;
    }
}

