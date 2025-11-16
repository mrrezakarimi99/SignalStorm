<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Models\Candle;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyAccuracySummaryCommand extends Command
{
    protected $signature = 'signal:accuracy-summary
                            {--days=7 : Number of days to analyze}
                            {--update : Update predictions with actual prices before analysis}
                            {--dry-run : Show summary without sending notification}';

    protected $description = 'Send daily accuracy summary analyzing confidence vs actual accuracy to find best signals';

    public function handle(NotificationService $notificationService): int
    {
        $this->info('📊 Generating Daily Accuracy Summary...');
        $this->newLine();

        $days = (int) $this->option('days');

        // Get verified predictions (those with actual prices)
        $query = Prediction::query()
            ->whereNotNull('actual_price')
            ->where('created_at', '>=', now()->subDays($days));

        $predictions = $query->orderBy('created_at', 'desc')->get();

        // Update predictions if requested
        if ($this->option('update')) {
            $this->updatePredictionsWithActuals($days);
            // Reload predictions after update
            $predictions = $query->get();
        }

        if ($predictions->isEmpty()) {
            $this->warn('No verified predictions found for analysis.');
            $this->info('Tip: Run with --update flag to fetch actual prices from candles');
            return self::SUCCESS;
        }

        $this->info("Analyzing {$predictions->count()} verified predictions from last {$days} days");
        $this->newLine();

        // Generate summary report
        $summary = $this->generateAccuracySummary($predictions, $days);

        // Display in console
        $this->displaySummary($summary);

        // Format message for notification
        $message = $this->formatNotificationMessage($summary, $days);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('🔍 DRY RUN - Message preview:');
            $this->line(str_repeat('─', 70));
            $this->line(strip_tags($message));
            $this->line(str_repeat('─', 70));
            return self::SUCCESS;
        }

        // Send notification
        $this->info('📤 Sending notification...');
        $results = $notificationService->notify($message, [
            'type' => 'accuracy_summary',
            'days' => $days,
            'predictions_count' => $predictions->count(),
        ]);

        $success = in_array(true, $results, true);

        if ($success) {
            $this->info('✓ Accuracy summary sent successfully');
        } else {
            $this->error('✗ Failed to send accuracy summary');
        }

        return self::SUCCESS;
    }

    /**
     * Update predictions with actual prices from candles
     */
    protected function updatePredictionsWithActuals(int $days): void
    {
        $this->info('📊 Updating predictions with actual prices...');

        $predictions = Prediction::query()
            ->whereNull('actual_price')
            ->where('created_at', '>=', now()->subDays($days))
            ->where('target_time', '<=', now())
            ->get();

        if ($predictions->isEmpty()) {
            $this->info('No predictions need updating');
            return;
        }

        $progressBar = $this->output->createProgressBar($predictions->count());
        $updated = 0;

        foreach ($predictions as $prediction) {
            $candle = Candle::forSymbol($prediction->symbol)
                ->forInterval($prediction->interval)
                ->where('open_time', '>=', $prediction->target_time->timestamp * 1000)
                ->orderBy('open_time', 'asc')
                ->first();

            if ($candle) {
                $actualPrice = $candle->close;
                $actualChangePercent = (($actualPrice - $prediction->current_price) / $prediction->current_price) * 100;

                $priceError = abs($prediction->predicted_price - $actualPrice);
                $priceErrorPercent = ($priceError / $prediction->current_price) * 100;

                // Better accuracy calculation
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
        $this->newLine();
    }

    /**
     * Generate comprehensive accuracy summary
     */
    protected function generateAccuracySummary($predictions, int $days): array
    {
        $summary = [
            'days' => $days,
            'total_predictions' => $predictions->count(),
            'overall' => $this->calculateOverallStats($predictions),
            'by_confidence' => $this->analyzeByConfidence($predictions),
            'by_signal' => $this->analyzeBySignal($predictions),
            'by_symbol' => $this->analyzeBySymbol($predictions),
            'best_performers' => $this->findBestPerformers($predictions),
            'recommendations' => $this->generateRecommendations($predictions),
        ];

        return $summary;
    }

    /**
     * Calculate overall statistics
     */
    protected function calculateOverallStats($predictions): array
    {
        $correctDirections = $predictions->filter(function ($prediction) {
            $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
            $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
            return $predictedDirection === $actualDirection;
        })->count();

        return [
            'avg_confidence' => $predictions->avg('confidence'),
            'avg_accuracy' => $predictions->avg('accuracy'),
            'direction_accuracy' => ($correctDirections / $predictions->count()) * 100,
            'correct_directions' => $correctDirections,
            'total' => $predictions->count(),
        ];
    }

    /**
     * Analyze predictions by confidence levels
     */
    protected function analyzeByConfidence($predictions): array
    {
        $ranges = [
            'Very High (80-100%)' => [80, 100],
            'High (60-80%)' => [60, 80],
            'Medium (40-60%)' => [40, 60],
            'Low (0-40%)' => [0, 40],
        ];

        $analysis = [];

        foreach ($ranges as $label => [$min, $max]) {
            $inRange = $predictions->filter(fn($p) => $p->confidence >= $min && $p->confidence < $max);

            if ($inRange->isEmpty()) {
                continue;
            }

            $correctDirections = $inRange->filter(function ($prediction) {
                $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
                $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
                return $predictedDirection === $actualDirection;
            })->count();

            $analysis[$label] = [
                'count' => $inRange->count(),
                'avg_accuracy' => $inRange->avg('accuracy'),
                'direction_accuracy' => ($correctDirections / $inRange->count()) * 100,
                'avg_confidence' => $inRange->avg('confidence'),
            ];
        }

        return $analysis;
    }

    /**
     * Analyze predictions by signal type
     */
    protected function analyzeBySignal($predictions): array
    {
        $signals = ['BUY', 'SELL', 'HOLD'];
        $analysis = [];

        foreach ($signals as $signal) {
            $signalPredictions = $predictions->filter(fn($p) => $p->signal === $signal);

            if ($signalPredictions->isEmpty()) {
                continue;
            }

            $correctDirections = $signalPredictions->filter(function ($prediction) {
                $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
                $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
                return $predictedDirection === $actualDirection;
            })->count();

            $analysis[$signal] = [
                'count' => $signalPredictions->count(),
                'avg_confidence' => $signalPredictions->avg('confidence'),
                'avg_accuracy' => $signalPredictions->avg('accuracy'),
                'direction_accuracy' => ($correctDirections / $signalPredictions->count()) * 100,
            ];
        }

        return $analysis;
    }

    /**
     * Analyze predictions by symbol
     */
    protected function analyzeBySymbol($predictions): array
    {
        $bySymbol = $predictions->groupBy('symbol');
        $analysis = [];

        foreach ($bySymbol as $symbol => $symbolPredictions) {
            if ($symbolPredictions->count() < 3) {
                continue; // Skip symbols with too few predictions
            }

            $correctDirections = $symbolPredictions->filter(function ($prediction) {
                $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
                $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
                return $predictedDirection === $actualDirection;
            })->count();

            $analysis[$symbol] = [
                'count' => $symbolPredictions->count(),
                'avg_confidence' => $symbolPredictions->avg('confidence'),
                'avg_accuracy' => $symbolPredictions->avg('accuracy'),
                'direction_accuracy' => ($correctDirections / $symbolPredictions->count()) * 100,
            ];
        }

        // Sort by accuracy
        uasort($analysis, fn($a, $b) => $b['avg_accuracy'] <=> $a['avg_accuracy']);

        return array_slice($analysis, 0, 5, true); // Top 5 symbols
    }

    /**
     * Find best performing combinations
     */
    protected function findBestPerformers($predictions): array
    {
        // Find best confidence + signal combinations
        $performers = [];

        foreach (['BUY', 'SELL'] as $signal) {
            foreach ([80, 70, 60] as $minConfidence) {
                $filtered = $predictions->filter(function($p) use ($signal, $minConfidence) {
                    return $p->signal === $signal && $p->confidence >= $minConfidence;
                });

                if ($filtered->count() < 3) {
                    continue;
                }

                $correctDirections = $filtered->filter(function ($prediction) {
                    $predictedDirection = $prediction->price_change_percent > 0 ? 'up' : 'down';
                    $actualDirection = $prediction->actual_change_percent > 0 ? 'up' : 'down';
                    return $predictedDirection === $actualDirection;
                })->count();

                $directionAccuracy = ($correctDirections / $filtered->count()) * 100;

                $performers[] = [
                    'signal' => $signal,
                    'min_confidence' => $minConfidence,
                    'count' => $filtered->count(),
                    'avg_accuracy' => $filtered->avg('accuracy'),
                    'direction_accuracy' => $directionAccuracy,
                    'score' => $directionAccuracy * ($filtered->count() / 10), // Weighted by sample size
                ];
            }
        }

        // Sort by score
        usort($performers, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($performers, 0, 3); // Top 3 performers
    }

    /**
     * Generate trading recommendations based on analysis
     */
    protected function generateRecommendations($predictions): array
    {
        $recommendations = [];

        // Find the confidence level with best direction accuracy
        $byConfidence = $this->analyzeByConfidence($predictions);
        $bestConfidenceRange = collect($byConfidence)
            ->sortByDesc('direction_accuracy')
            ->first();

        if ($bestConfidenceRange && $bestConfidenceRange['direction_accuracy'] > 60) {
            $recommendations[] = [
                'type' => 'confidence_threshold',
                'message' => "Best results with confidence range showing {$bestConfidenceRange['direction_accuracy']}% direction accuracy",
            ];
        }

        // Find best performing signal type
        $bySignal = $this->analyzeBySignal($predictions);
        $bestSignal = collect($bySignal)
            ->filter(fn($s) => in_array($s, ['BUY', 'SELL']))
            ->sortByDesc('direction_accuracy')
            ->first();

        if ($bestSignal && $bestSignal['direction_accuracy'] > 60) {
            $recommendations[] = [
                'type' => 'best_signal',
                'message' => "Focus on signals with {$bestSignal['direction_accuracy']}% direction accuracy",
            ];
        }

        // Check if high confidence correlates with high accuracy
        $highConf = $predictions->filter(fn($p) => $p->confidence >= 80);
        $lowConf = $predictions->filter(fn($p) => $p->confidence < 60);

        if ($highConf->count() > 5 && $lowConf->count() > 5) {
            $highAccuracy = $highConf->avg('accuracy');
            $lowAccuracy = $lowConf->avg('accuracy');

            $accuracyDiff = $highAccuracy - $lowAccuracy;
            if ($accuracyDiff > 10) {
                $recommendations[] = [
                    'type' => 'confidence_correlation',
                    'message' => "High confidence predictions are significantly more accurate (+" . number_format($accuracyDiff, 1) . "%)",
                ];
            } elseif ($accuracyDiff < 5) {
                $recommendations[] = [
                    'type' => 'confidence_warning',
                    'message' => "⚠️ Confidence levels may not reliably indicate accuracy - review model calibration",
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Display summary in console
     */
    protected function displaySummary(array $summary): void
    {
        $this->info('📈 Overall Performance');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Avg Confidence', number_format($summary['overall']['avg_confidence'], 2) . '%'],
                ['Avg Accuracy', number_format($summary['overall']['avg_accuracy'], 2) . '%'],
                ['Direction Accuracy', number_format($summary['overall']['direction_accuracy'], 2) . '%'],
                ['Correct Predictions', "{$summary['overall']['correct_directions']} / {$summary['overall']['total']}"],
            ]
        );
        $this->newLine();

        if (!empty($summary['best_performers'])) {
            $this->info('🏆 Best Performing Combinations');
            $data = [];
            foreach ($summary['best_performers'] as $performer) {
                $data[] = [
                    $performer['signal'],
                    $performer['min_confidence'] . '%+',
                    $performer['count'],
                    number_format($performer['direction_accuracy'], 1) . '%',
                    number_format($performer['avg_accuracy'], 1) . '%',
                ];
            }
            $this->table(['Signal', 'Min Conf', 'Count', 'Dir Acc', 'Avg Acc'], $data);
            $this->newLine();
        }

        if (!empty($summary['recommendations'])) {
            $this->info('💡 Recommendations');
            foreach ($summary['recommendations'] as $rec) {
                $this->line('  • ' . $rec['message']);
            }
        }
    }

    /**
     * Format message for notification
     */
    protected function formatNotificationMessage(array $summary, int $days): string
    {
        $message = "<b>📊 DAILY ACCURACY SUMMARY</b>\n";
        $message .= "<i>Last {$days} days • {$summary['total_predictions']} verified predictions</i>\n\n";

        // Overall stats
        $message .= "<b>📈 Overall Performance</b>\n";
        $message .= "• Avg Confidence: <code>" . number_format($summary['overall']['avg_confidence'], 1) . "%</code>\n";
        $message .= "• Avg Accuracy: <code>" . number_format($summary['overall']['avg_accuracy'], 1) . "%</code>\n";
        $message .= "• Direction Accuracy: <code>" . number_format($summary['overall']['direction_accuracy'], 1) . "%</code>\n";
        $message .= "• Correct: <code>{$summary['overall']['correct_directions']}/{$summary['overall']['total']}</code>\n\n";

        // Best performers
        if (!empty($summary['best_performers'])) {
            $message .= "<b>🏆 Best Performing Signals</b>\n";
            foreach (array_slice($summary['best_performers'], 0, 3) as $index => $performer) {
                $emoji = ['🥇', '🥈', '🥉'][$index] ?? '•';
                $message .= "{$emoji} <b>{$performer['signal']}</b> (conf ≥{$performer['min_confidence']}%)\n";
                $message .= "   Dir Acc: <code>" . number_format($performer['direction_accuracy'], 1) . "%</code> ";
                $message .= "({$performer['count']} predictions)\n";
            }
            $message .= "\n";
        }

        // By signal type
        if (!empty($summary['by_signal'])) {
            $message .= "<b>📊 By Signal Type</b>\n";
            foreach ($summary['by_signal'] as $signal => $stats) {
                $emoji = match($signal) {
                    'BUY' => '🟢',
                    'SELL' => '🔴',
                    'HOLD' => '🟡',
                    default => '⚪',
                };
                $message .= "{$emoji} <b>{$signal}</b>: ";
                $message .= "<code>" . number_format($stats['direction_accuracy'], 1) . "%</code> ";
                $message .= "({$stats['count']})\n";
            }
            $message .= "\n";
        }

        // By confidence level
        if (!empty($summary['by_confidence'])) {
            $message .= "<b>🎯 By Confidence Level</b>\n";
            foreach ($summary['by_confidence'] as $range => $stats) {
                $message .= "• {$range}\n";
                $message .= "  Dir Acc: <code>" . number_format($stats['direction_accuracy'], 1) . "%</code> ";
                $message .= "({$stats['count']})\n";
            }
            $message .= "\n";
        }

        // Top symbols
        if (!empty($summary['by_symbol'])) {
            $message .= "<b>💰 Top Performing Symbols</b>\n";
            $count = 0;
            foreach ($summary['by_symbol'] as $symbol => $stats) {
                if ($count++ >= 3) break;
                $message .= "• <code>{$symbol}</code>: ";
                $message .= "<code>" . number_format($stats['direction_accuracy'], 1) . "%</code> ";
                $message .= "({$stats['count']})\n";
            }
            $message .= "\n";
        }

        // Recommendations
        if (!empty($summary['recommendations'])) {
            $message .= "<b>💡 Key Insights</b>\n";
            foreach ($summary['recommendations'] as $rec) {
                $message .= "• " . strip_tags($rec['message']) . "\n";
            }
            $message .= "\n";
        }

        $message .= "<i>📅 Generated: " . now()->format('Y-m-d H:i:s') . "</i>";

        return $message;
    }
}

