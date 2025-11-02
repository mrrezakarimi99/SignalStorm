<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Automated data fetching
        if (config('trading.automation.auto_fetch', true)) {
            $fetchSchedule = config('trading.fetch.schedule', 'hourly');

            if ($fetchSchedule === 'hourly') {
                $schedule->command('data:fetch-all')
                    ->hourly()
                    ->withoutOverlapping()
                    ->runInBackground();
            } elseif ($fetchSchedule === 'every30minutes') {
                $schedule->command('data:fetch-all')
                    ->everyThirtyMinutes()
                    ->withoutOverlapping()
                    ->runInBackground();
            } elseif ($fetchSchedule === 'every15minutes') {
                $schedule->command('data:fetch-all')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping()
                    ->runInBackground();
            }
        }

        // Automated model training
        if (config('trading.automation.auto_train', true)) {
            $trainingSchedule = config('trading.training.schedule', 'daily');
            $trainingTime = config('trading.training.schedule_time', '02:00');

            if ($trainingSchedule === 'daily') {
                $schedule->command('model:train-all')
                    ->dailyAt($trainingTime)
                    ->withoutOverlapping()
                    ->runInBackground();
            } elseif ($trainingSchedule === 'weekly') {
                $schedule->command('model:train-all')
                    ->weeklyOn(1, $trainingTime) // Monday
                    ->withoutOverlapping()
                    ->runInBackground();
            } elseif ($trainingSchedule === 'twiceDaily') {
                $schedule->command('model:train-all')
                    ->twiceDaily(2, 14) // 02:00 AM and 02:00 PM
                    ->withoutOverlapping()
                    ->runInBackground();
            }
        }

        // Automated predictions (generate trading signals)
        if (config('trading.automation.auto_predict', true)) {
            $predictionSchedule = config('trading.prediction.schedule', 'every4hours');

            if ($predictionSchedule === 'hourly') {
                $schedule->command('predict:all')
                    ->hourly()
                    ->withoutOverlapping()
                    ->runInBackground();
            } elseif ($predictionSchedule === 'every4hours') {
                $schedule->command('predict:all')
                    ->everyFourHours()
                    ->withoutOverlapping()
                    ->runInBackground();
            } elseif ($predictionSchedule === 'every6hours') {
                $schedule->command('predict:all')
                    ->everySixHours()
                    ->withoutOverlapping()
                    ->runInBackground();
            }
        }

        // Daily trading signals summary
        if (config('trading.automation.daily_summary', true)) {
            $summaryTime = config('trading.summary.schedule_time', '08:00');

            $schedule->command('signal:daily-summary')
                ->dailyAt($summaryTime)
                ->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

