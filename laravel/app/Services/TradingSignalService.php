<?php

namespace App\Services;

use App\Models\Prediction;
use App\Notifiers\TelegramNotifier;
use Illuminate\Support\Facades\Log;

/**
 * Trading Signal Service
 *
 * Handles sending trading signals via notifications
 */
class TradingSignalService
{
    protected TelegramNotifier $telegramNotifier;

    public function __construct(TelegramNotifier $telegramNotifier)
    {
        $this->telegramNotifier = $telegramNotifier;
    }

    /**
     * Send trading signal notification
     */
    public function sendSignal(Prediction $prediction): bool
    {
        if (!$this->shouldNotify($prediction)) {
            return false;
        }

        $message = $this->formatSignalMessage($prediction);

        // Send trading signals to channel (public)
        return $this->telegramNotifier->sendToChannel($message, [
            'symbol' => $prediction->symbol,
            'signal' => $prediction->signal,
            'confidence' => $prediction->confidence,
        ]);
    }

    /**
     * Check if we should send notification for this prediction
     */
    protected function shouldNotify(Prediction $prediction): bool
    {
        // Always notify for unrealistic predictions (HOLD signals with metadata indicating unrealistic)
        if ($prediction->signal === 'HOLD' && 
            isset($prediction->metadata['unrealistic']) && 
            $prediction->metadata['unrealistic'] === true) {
            return true; // Send "Review needed" alert
        }

        // Only notify for BUY/SELL signals with very high confidence (90%+)
        if (!in_array($prediction->signal, ['BUY', 'SELL'])) {
            return false;
        }

        if ($prediction->confidence < 80) {
            return false;
        }

        return true;
    }

    /**
     * Format the signal message for Telegram
     */
    protected function formatSignalMessage(Prediction $prediction): string
    {
        // Check if this is an unrealistic prediction requiring review
        $isUnrealistic = isset($prediction->metadata['unrealistic']) && $prediction->metadata['unrealistic'] === true;
        
        if ($isUnrealistic) {
            return $this->formatUnrealisticPredictionMessage($prediction);
        }

        $emoji = $this->getSignalEmoji($prediction->signal);
        $priceEmoji = (float) $prediction->price_change_percent > 0 ? '📈' : '📉';

        $message = "<b>{$emoji} TRADING SIGNAL {$emoji}</b>\n\n";

        // Signal type
        $message .= "<b>🎯 Signal:</b> <code>{$prediction->signal}</code>\n";

        // Symbol and timeframe
        $message .= "<b>💰 Symbol:</b> <code>{$prediction->symbol}</code>\n";
        $message .= "<b>⏰ Timeframe:</b> <code>{$prediction->interval}</code>\n\n";

        // Price information
        $message .= "<b>📊 Price Analysis:</b>\n";
        $message .= "• Current: <code>$" . $prediction->current_price . "</code>\n";
        $message .= "• Predicted: <code>$" . $prediction->predicted_price . "</code>\n";
        $message .= "• Change: <code>{$priceEmoji} " . number_format((float) $prediction->price_change_percent, 2) . "%</code>\n\n";

        // Confidence
        $confidenceBar = $this->getConfidenceBar((float) $prediction->confidence);
        $message .= "<b>🎲 Confidence:</b> <code>{$prediction->confidence}%</code> {$confidenceBar}\n\n";

        // Model info
        $message .= "<b>🤖 Model:</b> <code>{$prediction->model_version}</code>\n";

        // Trading suggestion
        $message .= "\n" . $this->getTradingSuggestion($prediction);

        // Disclaimer
        $message .= "\n\n<i>⚠️ This is an AI-generated signal. Always do your own research and manage risk properly.</i>";

        return $message;
    }

    /**
     * Format message for unrealistic predictions requiring manual review
     */
    protected function formatUnrealisticPredictionMessage(Prediction $prediction): string
    {
        $message = "<b>⚠️ SIGNAL REQUIRES REVIEW ⚠️</b>\n\n";
        
        $message .= "<b>🎯 Status:</b> <code>MANUAL REVIEW NEEDED</code>\n";
        $message .= "<b>💰 Symbol:</b> <code>{$prediction->symbol}</code>\n";
        $message .= "<b>⏰ Timeframe:</b> <code>{$prediction->interval}</code>\n\n";
        
        $message .= "<b>🚨 Issue Detected:</b>\n";
        $message .= "• ML model produced unrealistic prediction\n";
        $message .= "• Raw prediction exceeded safety thresholds\n";
        $message .= "• Automatic signal generation suspended\n\n";
        
        $message .= "<b>📊 Current Price:</b> <code>$" . $prediction->current_price . "</code>\n";
        $message .= "<b>🤖 Model:</b> <code>{$prediction->model_version}</code>\n\n";
        
        $message .= "<b>💡 Recommended Actions:</b>\n";
        $message .= "• Manual chart analysis required\n";
        $message .= "• Check for market events or news\n";
        $message .= "• Consider model retraining\n";
        $message .= "• Do not place trades based on this signal\n\n";
        
        $message .= "<i>🛡️ This alert was triggered by SignalStorm's safety system to protect against unrealistic predictions.</i>";
        
        return $message;
    }

    /**
     * Get emoji for signal type
     */
    protected function getSignalEmoji(string $signal): string
    {
        return match($signal) {
            'BUY' => '🟢',
            'SELL' => '🔴',
            'HOLD' => '🟡',
            default => '⚪',
        };
    }

    /**
     * Get confidence bar visualization
     */
    protected function getConfidenceBar(float $confidence): string
    {
        $bars = floor($confidence / 10);
        $filled = str_repeat('█', $bars);
        $empty = str_repeat('░', 10 - $bars);
        return $filled . $empty;
    }

    /**
     * Get trading suggestion based on signal and confidence
     */
    protected function getTradingSuggestion(Prediction $prediction): string
    {
        $suggestion = "<b>💡 Suggestion:</b>\n";

        if ($prediction->signal === 'BUY') {
            if ($prediction->confidence >= 85) {
                $suggestion .= "✅ <b>Strong Buy Signal</b> - High probability of upward movement\n";
                $suggestion .= "• Consider entering a position\n";
                $suggestion .= "• Recommended stop-loss: " . number_format($prediction->current_price * 0.98, 2) . " (-2%)";
            } elseif ($prediction->confidence >= 70) {
                $suggestion .= "✅ <b>Moderate Buy Signal</b> - Good entry opportunity\n";
                $suggestion .= "• Consider entering with reduced position size\n";
                $suggestion .= "• Recommended stop-loss: " . number_format($prediction->current_price * 0.97, 2) . " (-3%)";
            }
        } elseif ($prediction->signal === 'SELL') {
            if ($prediction->confidence >= 85) {
                $suggestion .= "⛔ <b>Strong Sell Signal</b> - High probability of downward movement\n";
                $suggestion .= "• Consider taking profits or opening short position\n";
                $suggestion .= "• Recommended stop-loss: " . number_format($prediction->current_price * 1.02, 2) . " (+2%)";
            } elseif ($prediction->confidence >= 70) {
                $suggestion .= "⛔ <b>Moderate Sell Signal</b> - Potential downward movement\n";
                $suggestion .= "• Consider reducing position or opening small short\n";
                $suggestion .= "• Recommended stop-loss: " . number_format($prediction->current_price * 1.03, 2) . " (+3%)";
            }
        }

        return $suggestion;
    }

    /**
     * Send daily summary of signals
     */
    public function sendDailySummary(): bool
    {
        $predictions = Prediction::whereIn('signal', ['BUY', 'SELL'])
            ->where('confidence', '>=', 70)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('confidence', 'desc')
            ->get();

        if ($predictions->isEmpty()) {
            return false;
        }

        $message = $this->formatDailySummary($predictions);
        
        // Send daily summary to channel (public)
        return $this->telegramNotifier->sendToChannel($message, [
            'total_signals' => $predictions->count(),
        ]);
    }

    /**
     * Send batch of recent predictions in one message
     * This prevents notification spam by grouping predictions
     */
    public function sendBatchSignals(?int $minutes = null): bool
    {
        $minutes = $minutes ?? config('trading.notifications.batch_interval', 15);
        $maxPerMessage = config('trading.notifications.batch_max_per_message', 5);

        // Get recent predictions that haven't been notified yet
        $allPredictions = Prediction::whereIn('signal', ['BUY', 'SELL'])
            ->where('confidence', '>=', config('trading.notifications.instant_min_confidence', 80))
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->whereNull('notified_at') // Only get predictions that haven't been sent
            ->orderBy('confidence', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($allPredictions->isEmpty()) {
            Log::info("No new predictions to send in batch notification");
            return false;
        }

        // Remove duplicates - keep only the latest prediction per symbol+interval
        $predictions = $allPredictions->groupBy(function($prediction) {
            return $prediction->symbol . '_' . $prediction->interval;
        })->map(function($group) {
            // Return the prediction with highest confidence, or most recent if tied
            return $group->sortByDesc('confidence')
                        ->sortByDesc('created_at')
                        ->first();
        })->values();

        if ($predictions->isEmpty()) {
            Log::info("No unique predictions to send after deduplication");
            return false;
        }

        Log::info("Sending batch notification for {$predictions->count()} unique predictions (filtered from {$allPredictions->count()})");

        // Split predictions into chunks to avoid Telegram's 4096 character limit
        $chunks = $predictions->chunk($maxPerMessage);
        $success = true;
        $sentPredictionIds = [];

        foreach ($chunks as $index => $chunk) {
            $message = $this->formatBatchSignalMessage($chunk, $minutes, $index + 1, $chunks->count());

            // Send batch to channel
            $result = $this->telegramNotifier->sendToChannel($message, [
                'batch_count' => $chunk->count(),
                'batch_part' => $index + 1,
                'total_parts' => $chunks->count(),
            ]);

            if ($result) {
                // Mark these predictions as notified
                $chunkIds = $chunk->pluck('id')->toArray();
                $sentPredictionIds = array_merge($sentPredictionIds, $chunkIds);
            } else {
                $success = false;
                Log::error("Failed to send batch notification part " . ($index + 1) . "/" . $chunks->count());
            }

            // Small delay between messages to avoid rate limiting
            if ($chunks->count() > 1 && $index < $chunks->count() - 1) {
                usleep(500000); // 0.5 second delay
            }
        }

        // Mark all sent predictions as notified
        if (!empty($sentPredictionIds)) {
            Prediction::whereIn('id', $sentPredictionIds)
                ->update(['notified_at' => now()]);
            Log::info("Marked " . count($sentPredictionIds) . " predictions as notified");
        }

        return $success;
    }

    /**
     * Format batch signal message with lowercase text
     */
    protected function formatBatchSignalMessage($predictions, int $minutes, int $part = 1, int $totalParts = 1): string
    {
        $buyCount = $predictions->where('signal', 'BUY')->count();
        $sellCount = $predictions->where('signal', 'SELL')->count();

        // Header with part number if multiple parts
        if ($totalParts > 1) {
            $message = "<b>🔔 trading signals ({$minutes}min) [{$part}/{$totalParts}]</b>\n\n";
        } else {
            $message = "<b>🔔 trading signals ({$minutes}min)</b>\n\n";
        }

        $message .= "<b>📊 summary:</b>\n";
        $message .= "• total: {$predictions->count()} signals\n";
        $message .= "• buy: {$buyCount} | sell: {$sellCount}\n\n";

        $message .= "<b>🎯 signals:</b>\n\n";

        foreach ($predictions as $index => $prediction) {
            $emoji = $this->getSignalEmoji($prediction->signal);
            $priceEmoji = (float) $prediction->price_change_percent > 0 ? '📈' : '📉';

            $signal = strtolower($prediction->signal);
            $symbol = strtolower($prediction->symbol);
            $interval = strtolower($prediction->interval);

            $message .= "{$emoji} <b>{$signal}</b> {$symbol} ({$interval})\n";
            $message .= "• price: $" . number_format((float)$prediction->current_price, 2) . " → $" . number_format((float)$prediction->predicted_price, 2) . "\n";
            $message .= "• change: {$priceEmoji} " . number_format((float) $prediction->price_change_percent, 2) . "%\n";
            $message .= "• confidence: {$prediction->confidence}% ";
            $message .= $this->getConfidenceBar((float) $prediction->confidence) . "\n";

            if ($index < $predictions->count() - 1) {
                $message .= "\n";
            }
        }
        return $message;
    }

    /**
     * Format daily summary message
     */
    protected function formatDailySummary($predictions): string
    {
        $message = "<b>📊 daily trading signals summary</b>\n";
        $message .= "<i>" . now()->format('F d, Y') . "</i>\n\n";

        $buyCount = $predictions->where('signal', 'BUY')->count();
        $sellCount = $predictions->where('signal', 'SELL')->count();

        $message .= "<b>📈 total signals:</b> {$predictions->count()}\n";
        $message .= "• buy: {$buyCount}\n";
        $message .= "• sell: {$sellCount}\n\n";

        $message .= "<b>🔝 top signals:</b>\n\n";

        foreach ($predictions->take(5) as $index => $prediction) {
            $emoji = $this->getSignalEmoji($prediction->signal);
            $symbol = strtolower($prediction->symbol);
            $signal = strtolower($prediction->signal);
            $message .= ($index + 1) . ". {$emoji} <b>{$symbol}</b> ({$prediction->interval})\n";
            $message .= "   signal: <code>{$signal}</code> | ";
            $message .= "confidence: <code>{$prediction->confidence}%</code>\n";
            $message .= "   change: <code>" . number_format($prediction->price_change_percent, 2) . "%</code>\n\n";
        }

        return $message;
    }
}

