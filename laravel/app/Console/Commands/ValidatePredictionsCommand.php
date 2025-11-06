<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Models\Candle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidatePredictionsCommand extends Command
{
    protected $signature = 'predict:validate
                            {--symbol= : Filter by trading symbol (e.g., BTCUSDT)}
                            {--interval= : Filter by interval (e.g., 1h, 4h, 1d)}
                            {--model= : Filter by model version}
                            {--days=7 : Number of days to look back}
                            {--min-confidence=0 : Minimum confidence threshold}
                            {--update : Update predictions with actual prices from candles}
                            {--signal= : Filter by signal type (BUY, SELL, HOLD)}';

    protected $description = 'Validate predictions and check confidence levels against actual results';

    public function handle(): int
    {
        $this->info('🔍 Validating Predictions...');
        $this->newLine();

        // Build query
        $query = Prediction::query()
            ->where('created_at', '>=', now()->subDays($this->option('days')));

        if ($symbol = $this->option('symbol')) {
            $query->forSymbol($symbol);
        }

        if ($interval = $this->option('interval')) {
            $query->forInterval($interval);
        }

        if ($model = $this->option('model')) {
            $query->forModel($model);
        }

        if ($minConfidence = $this->option('min-confidence')) {
            $query->where('confidence', '>=', $minConfidence);
        }

        if ($signal = $this->option('signal')) {
            $query->withSignal(strtoupper($signal));
        }

        $predictions = $query->orderBy('created_at', 'desc')->get();

        if ($predictions->isEmpty()) {
            $this->warn('No predictions found matching the criteria.');
            return self::SUCCESS;
        }

        $this->info("Found {$predictions->count()} predictions");
        $this->newLine();

        // Update predictions with actual prices if requested
        if ($this->option('update')) {
            $this->updatePredictionsWithActuals($predictions);
            $this->newLine();
        }

        // Display overall statistics
        $this->displayOverallStats($predictions);
        $this->newLine();

        // Display confidence-based statistics
        $this->displayConfidenceStats($predictions);
        $this->newLine();

        // Display signal-based statistics
        $this->displaySignalStats($predictions);
        $this->newLine();

        // Display detailed predictions table
        if ($this->confirm('Show detailed prediction breakdown?', true)) {
            $this->newLine();
            $this->displayDetailedPredictions($predictions);
        }

        return self::SUCCESS;
    }

    /**
     * Update predictions with actual prices from candles
     */
    protected function updatePredictionsWithActuals($predictions): void
    {
        $this->info('📊 Updating predictions with actual prices...');
        $progressBar = $this->output->createProgressBar($predictions->count());

        $updated = 0;

        foreach ($predictions as $prediction) {
            // Skip if already has actual price
            if ($prediction->actual_price !== null) {
                $progressBar->advance();
                continue;
            }

            // Get the candle at or after the target time
            $candle = Candle::forSymbol($prediction->symbol)
                ->forInterval($prediction->interval)
                ->where('open_time', '>=', $prediction->target_time->timestamp * 1000)
                ->orderBy('open_time', 'asc')
                ->first();

            if ($candle) {
                $actualPrice = $candle->close;
                $actualChangePercent = (($actualPrice - $prediction->current_price) / $prediction->current_price) * 100;

                // Calculate accuracy based on actual price error (more realistic for trading)
                $priceError = abs($prediction->predicted_price - $actualPrice);
                $priceErrorPercent = ($priceError / $prediction->current_price) * 100;
                $accuracy = max(0, 100 - ($priceErrorPercent * 10)); // Scale the error appropriately

                // Alternative: If price error is within 2%, give high accuracy
                if ($priceErrorPercent <= 2) {
                    $accuracy = 100 - ($priceErrorPercent * 10);
                } else {
                    $accuracy = max(0, 100 - ($priceErrorPercent * 5));
                }

                $prediction->update([
                    'actual_price' => $actualPrice,
                    'actual_change_percent' => $actualChangePercent,
                    'accuracy' => $accuracy,
                ]);

                $updated++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✓ Updated {$updated} predictions with actual prices");
    }

    /**
     * Display overall statistics
     */
    protected function displayOverallStats($predictions): void
    {
        $this->info('📈 Overall Statistics');
        $this->line(str_repeat('─', 70));

        $verified = $predictions->filter(fn($p) => $p->actual_price !== null);
        $verifiedCount = $verified->count();

        $totalCount = $predictions->count();
        $verificationRate = $totalCount > 0 ? ($verifiedCount / $totalCount) * 100 : 0;

        $avgConfidence = $predictions->avg('confidence');
        $avgAccuracy = $verified->avg('accuracy');

        // Calculate average price error
        $avgPriceError = $verified->map(function ($prediction) {
            return abs($prediction->predicted_price - $prediction->actual_price);
        })->avg();

        $correctDirections = $verified->filter(function ($prediction) {
            $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
            $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
            return $predictedDirection === $actualDirection;
        })->count();

        $directionAccuracy = $verifiedCount > 0 ? ($correctDirections / $verifiedCount) * 100 : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Predictions', $totalCount],
                ['Verified Predictions', "{$verifiedCount} (" . number_format($verificationRate, 2) . "%)"],
                ['Average Confidence', number_format($avgConfidence, 2) . '%'],
                ['Average Price Error', $avgPriceError ? number_format($avgPriceError, 4) : 'N/A'],
                ['Average Accuracy', $avgAccuracy ? number_format($avgAccuracy, 2) . '%' : 'N/A'],
                ['Direction Accuracy', number_format($directionAccuracy, 2) . '%'],
                ['Correct Directions', "{$correctDirections} / {$verifiedCount}"],
            ]
        );
    }

    /**
     * Display confidence-based statistics
     */
    protected function displayConfidenceStats($predictions): void
    {
        $this->info('🎯 Confidence Level Breakdown');
        $this->line(str_repeat('─', 70));

        $confidenceRanges = [
            'Very High (80-100%)' => [80, 100],
            'High (60-80%)' => [60, 80],
            'Medium (40-60%)' => [40, 60],
            'Low (20-40%)' => [20, 40],
            'Very Low (0-20%)' => [0, 20],
        ];

        $stats = [];

        foreach ($confidenceRanges as $label => [$min, $max]) {
            $inRange = $predictions->filter(fn($p) => $p->confidence >= $min && $p->confidence < $max);
            $verified = $inRange->filter(fn($p) => $p->actual_price !== null);

            $count = $inRange->count();
            $verifiedCount = $verified->count();
            $avgAccuracy = $verified->avg('accuracy');

            $correctDirections = $verified->filter(function ($prediction) {
                $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
                $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
                return $predictedDirection === $actualDirection;
            })->count();

            $directionAccuracy = $verifiedCount > 0 ? ($correctDirections / $verifiedCount) * 100 : 0;

            $stats[] = [
                $label,
                $count,
                $verifiedCount,
                $avgAccuracy ? number_format($avgAccuracy, 2) . '%' : 'N/A',
                number_format($directionAccuracy, 2) . '%',
            ];
        }

        $this->table(
            ['Confidence Range', 'Total', 'Verified', 'Avg Accuracy', 'Direction Accuracy'],
            $stats
        );
    }

    /**
     * Display signal-based statistics
     */
    protected function displaySignalStats($predictions): void
    {
        $this->info('📊 Signal Type Breakdown');
        $this->line(str_repeat('─', 70));

        $signals = ['BUY', 'SELL', 'HOLD'];
        $stats = [];

        foreach ($signals as $signal) {
            $signalPredictions = $predictions->filter(fn($p) => $p->signal === $signal);
            $verified = $signalPredictions->filter(fn($p) => $p->actual_price !== null);

            $count = $signalPredictions->count();
            $verifiedCount = $verified->count();
            $avgConfidence = $signalPredictions->avg('confidence');
            $avgAccuracy = $verified->avg('accuracy');

            $correctDirections = $verified->filter(function ($prediction) {
                $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
                $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
                return $predictedDirection === $actualDirection;
            })->count();

            $directionAccuracy = $verifiedCount > 0 ? ($correctDirections / $verifiedCount) * 100 : 0;

            $stats[] = [
                $signal,
                $count,
                $verifiedCount,
                number_format($avgConfidence, 2) . '%',
                $avgAccuracy ? number_format($avgAccuracy, 2) . '%' : 'N/A',
                number_format($directionAccuracy, 2) . '%',
            ];
        }

        $this->table(
            ['Signal', 'Total', 'Verified', 'Avg Confidence', 'Avg Accuracy', 'Direction Accuracy'],
            $stats
        );
    }

    /**
     * Display detailed predictions table
     */
    protected function displayDetailedPredictions($predictions): void
    {
        $this->info('📋 Detailed Predictions');
        $this->line(str_repeat('─', 70));

        $limit = min(50, $predictions->count());
        $this->warn("Showing latest {$limit} predictions (limited for readability)");
        $this->newLine();

        $data = [];

        foreach ($predictions->take($limit) as $prediction) {
            $status = $prediction->actual_price !== null ? '✓' : '⏳';
            $accuracy = $prediction->accuracy !== null ? number_format($prediction->accuracy, 1) . '%' : 'N/A';

            $directionCorrect = '';
            $priceError = 'N/A';
            if ($prediction->actual_price !== null) {
                $predictedDirection = $prediction->price_change_percent > 0 ? '↑' : '↓';
                $actualDirection = $prediction->actual_change_percent > 0 ? '↑' : '↓';
                $directionCorrect = $predictedDirection === $actualDirection ? '✓' : '✗';

                // Calculate price error
                $error = abs($prediction->predicted_price - $prediction->actual_price);
                $priceError = number_format($error, 2);
            }

            $data[] = [
                $status,
                $prediction->symbol,
                $prediction->interval,
                $prediction->signal,
                number_format($prediction->confidence, 1) . '%',
                number_format($prediction->price_change_percent, 2) . '%',
                $prediction->actual_change_percent !== null ? number_format($prediction->actual_change_percent, 2) . '%' : 'N/A',
                $directionCorrect,
                $priceError,
                $accuracy,
                $prediction->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table(
            ['✓', 'Symbol', 'Interval', 'Signal', 'Conf%', 'Pred Δ', 'Act Δ', 'Dir', 'Error', 'Acc%', 'Created'],
            $data
        );

        if ($predictions->count() > $limit) {
            $this->newLine();
            $this->warn("... and " . ($predictions->count() - $limit) . " more predictions");
        }
    }
}

