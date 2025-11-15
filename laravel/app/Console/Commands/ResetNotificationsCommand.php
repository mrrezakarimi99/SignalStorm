<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use Illuminate\Console\Command;

class ResetNotificationsCommand extends Command
{
    protected $signature = 'signal:reset-notifications
                            {--all : Reset all predictions}
                            {--hours=24 : Reset predictions from last X hours}';

    protected $description = 'Reset notification status for predictions (for testing)';

    public function handle(): int
    {
        if ($this->option('all')) {
            $count = Prediction::whereNotNull('notified_at')
                ->update(['notified_at' => null]);

            $this->info("✓ Reset notifications for {$count} predictions (all time)");
        } else {
            $hours = (int) $this->option('hours');
            $count = Prediction::whereNotNull('notified_at')
                ->where('created_at', '>=', now()->subHours($hours))
                ->update(['notified_at' => null]);

            $this->info("✓ Reset notifications for {$count} predictions (last {$hours} hours)");
        }

        $this->warn('These predictions can now be sent again in the next batch');

        return self::SUCCESS;
    }
}

