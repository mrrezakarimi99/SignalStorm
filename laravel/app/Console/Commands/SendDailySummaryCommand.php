<?php

namespace App\Console\Commands;

use App\Services\TradingSignalService;
use Illuminate\Console\Command;

class SendDailySummaryCommand extends Command
{
    protected $signature = 'signal:daily-summary';
    protected $description = 'Send daily trading signals summary to Telegram';

    public function handle(TradingSignalService $signalService): int
    {
        $this->info("Sending daily summary...");

        $success = $signalService->sendDailySummary();

        if ($success) {
            $this->info("✓ Daily summary sent successfully");
            return self::SUCCESS;
        }

        $this->warn("No signals to report or notification failed");
        return self::SUCCESS;
    }
}

