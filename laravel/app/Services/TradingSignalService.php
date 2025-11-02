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

        return $this->telegramNotifier->send($message, []);
    }

    /**
     * Check if we should send notification for this prediction
     */
    protected function shouldNotify(Prediction $prediction): bool
    {
        // Only notify for BUY/SELL signals with high confidence
        if (!in_array($prediction->signal, ['BUY', 'SELL'])) {
            return false;
        }

        if ($prediction->confidence < 70) {
            return false;
        }

        return true;
    }

    /**
     * Format the signal message for Telegram
     */
    protected function formatSignalMessage(Prediction $prediction): string
    {
        $emoji = $this->getSignalEmoji($prediction->signal);
        $priceEmoji = $prediction->price_change_percent > 0 ? '📈' : '📉';

        $message = "<b>{$emoji} TRADING SIGNAL {$emoji}</b>\n\n";

        // Signal type
        $message .= "<b>🎯 Signal:</b> <code>{$prediction->signal}</code>\n";

        // Symbol and timeframe
        $message .= "<b>💰 Symbol:</b> <code>{$prediction->symbol}</code>\n";
        $message .= "<b>⏰ Timeframe:</b> <code>{$prediction->interval}</code>\n\n";

        // Price information
        $message .= "<b>📊 Price Analysis:</b>\n";
        $message .= "• Current: <code>\${$prediction->current_price}</code>\n";
        $message .= "• Predicted: <code>\${$prediction->predicted_price}</code>\n";
        $message .= "• Change: <code>{$priceEmoji} " . number_format($prediction->price_change_percent, 2) . "%</code>\n\n";

        // Confidence
        $confidenceBar = $this->getConfidenceBar($prediction->confidence);
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
        return $this->telegramNotifier->send($message, []);
    }

    /**
     * Format daily summary message
     */
    protected function formatDailySummary($predictions): string
    {
        $message = "<b>📊 Daily Trading Signals Summary</b>\n";
        $message .= "<i>" . now()->format('F d, Y') . "</i>\n\n";

        $buyCount = $predictions->where('signal', 'BUY')->count();
        $sellCount = $predictions->where('signal', 'SELL')->count();

        $message .= "<b>📈 Total Signals:</b> {$predictions->count()}\n";
        $message .= "• Buy: {$buyCount}\n";
        $message .= "• Sell: {$sellCount}\n\n";

        $message .= "<b>🔝 Top Signals:</b>\n\n";

        foreach ($predictions->take(5) as $index => $prediction) {
            $emoji = $this->getSignalEmoji($prediction->signal);
            $message .= ($index + 1) . ". {$emoji} <b>{$prediction->symbol}</b> ({$prediction->interval})\n";
            $message .= "   Signal: <code>{$prediction->signal}</code> | ";
            $message .= "Confidence: <code>{$prediction->confidence}%</code>\n";
            $message .= "   Change: <code>" . number_format($prediction->price_change_percent, 2) . "%</code>\n\n";
        }

        return $message;
    }
}

