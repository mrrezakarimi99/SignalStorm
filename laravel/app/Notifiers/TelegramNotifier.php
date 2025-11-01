<?php

namespace App\Notifiers;

use App\Contracts\INotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Notifier
 *
 * Sends notifications via Telegram Bot API
 * Single Responsibility: Only handles Telegram notifications
 */
class TelegramNotifier implements INotifier
{
    private ?string $botToken;
    private ?string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
    }

    public function send(string $message, array $context = []): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Telegram notifier is not configured');
            return false;
        }

        try {
            // Format message with context
            $formattedMessage = $this->formatMessage($message, $context);

            $response = Http::post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                [
                    'chat_id' => $this->chatId,
                    'text' => $formattedMessage,
                    'parse_mode' => 'HTML',
                ]
            );

            if ($response->successful()) {
                Log::info('Telegram notification sent', [
                    'message' => $message,
                    'context' => $context,
                ]);
                return true;
            }

            Log::error('Telegram API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Failed to send Telegram notification', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);
            return false;
        }
    }

    public function getName(): string
    {
        return 'telegram';
    }

    public function isEnabled(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Format message with HTML markup and context data
     */
    private function formatMessage(string $message, array $context): string
    {
        $formatted = "🔔 <b>SignalStorm Alert</b>\n\n";
        $formatted .= $message . "\n";

        if (!empty($context)) {
            $formatted .= "\n<b>Details:</b>\n";

            foreach ($context as $key => $value) {
                $formatted .= "• <i>" . ucfirst(str_replace('_', ' ', $key)) . ":</i> ";
                $formatted .= $this->formatValue($value) . "\n";
            }
        }

        $formatted .= "\n<i>Timestamp:</i> " . now()->toDateTimeString();

        return $formatted;
    }

    /**
     * Format context value for display
     */
    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_float($value)) {
            return number_format($value, 2);
        }

        return (string) $value;
    }
}

