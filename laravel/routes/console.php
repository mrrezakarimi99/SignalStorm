<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trading System Scheduled Tasks
// Automated data fetching
if (config('trading.automation.auto_fetch', true)) {
    $fetchSchedule = config('trading.fetch.schedule', 'hourly');

    if ($fetchSchedule === 'hourly') {
        Schedule::command('data:fetch-all')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($fetchSchedule === 'every30minutes') {
        Schedule::command('data:fetch-all')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($fetchSchedule === 'every15minutes') {
        Schedule::command('data:fetch-all')
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
        Schedule::command('model:train-all')
            ->dailyAt($trainingTime)
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($trainingSchedule === 'weekly') {
        Schedule::command('model:train-all')
            ->weeklyOn(1, $trainingTime) // Monday
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($trainingSchedule === 'twiceDaily') {
        Schedule::command('model:train-all')
            ->twiceDaily(2, 14) // 02:00 AM and 02:00 PM
            ->withoutOverlapping()
            ->runInBackground();
    }
}

// Automated predictions (generate trading signals)
if (config('trading.automation.auto_predict', true)) {
    $predictionSchedule = config('trading.prediction.schedule', 'every4hours');

    if ($predictionSchedule === 'hourly') {
        Schedule::command('predict:all')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($predictionSchedule === 'every4hours') {
        Schedule::command('predict:all')
            ->everyFourHours()
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($predictionSchedule === 'every6hours') {
        Schedule::command('predict:all')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();
    }
}

// Batch signal notifications (send predictions grouped together)
// This prevents notification spam by sending all predictions in one message
if (config('trading.notifications.batch_enabled', true)) {
    $batchInterval = config('trading.notifications.batch_interval', 15);

    // Send batch notifications based on configured interval
    $schedule = Schedule::command('signal:send-batch', ['--minutes' => $batchInterval])
        ->withoutOverlapping()
        ->runInBackground();

    // Apply the appropriate schedule based on interval
    if ($batchInterval <= 5) {
        $schedule->everyFiveMinutes();
    } elseif ($batchInterval <= 10) {
        $schedule->everyTenMinutes();
    } elseif ($batchInterval <= 15) {
        $schedule->everyFifteenMinutes();
    } elseif ($batchInterval <= 30) {
        $schedule->everyThirtyMinutes();
    } else {
        $schedule->hourly();
    }
}

// Automatic prediction validation
// Validates predictions against actual prices and updates accuracy
if (config('trading.validation.auto_validate', true)) {
    $validationSchedule = config('trading.validation.schedule', 'hourly');
    $validationDays = config('trading.validation.days', 7);

    if ($validationSchedule === 'hourly') {
        Schedule::command('predict:validate', [
            '--update' => true,
            '--days' => $validationDays,
        ])
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($validationSchedule === 'every4hours') {
        Schedule::command('predict:validate', [
            '--update' => true,
            '--days' => $validationDays,
        ])
            ->everyFourHours()
            ->withoutOverlapping()
            ->runInBackground();
    } elseif ($validationSchedule === 'daily') {
        Schedule::command('predict:validate', [
            '--update' => true,
            '--days' => $validationDays,
        ])
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();
    }
}

// Daily trading signals summary
if (config('trading.automation.daily_summary', true)) {
    $summaryTime = config('trading.summary.schedule_time', '08:00');

    Schedule::command('signal:daily-summary')
        ->dailyAt($summaryTime)
        ->runInBackground();
}
