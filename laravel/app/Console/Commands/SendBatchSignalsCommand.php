<?php

namespace App\Console\Commands;

use App\Services\TradingSignalService;
use Illuminate\Console\Command;

class SendBatchSignalsCommand extends Command
{
    protected $signature = 'signal:send-batch
                            {--minutes=15 : Number of minutes to look back for predictions}';

    protected $description = 'Send batched trading signals to prevent notification spam';

    public function handle(TradingSignalService $signalService): int
    {
        $minutes = (int) $this->option('minutes');

        $this->info("Sending batch signals for last {$minutes} minutes...");

        $sent = $signalService->sendBatchSignals($minutes);

        if ($sent) {
            $this->info('✓ Batch signals sent successfully');
        } else {
            $this->info('No signals to send');
        }

        return self::SUCCESS;
    }
}

