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
    private ?string $channelId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
        $this->channelId = config('services.telegram.channel_id');
    }

    public function send(string $message, array $context = []): bool
    {
        if (!$this->isEnabled()) {
            Log::warning('Telegram notifier is not configured');
            return false;
        }

        // Check if message should be sent to channel instead of PV
        $sendToChannel = $context['send_to_channel'] ?? false;
        $targetChatId = $sendToChannel ? $this->channelId : $this->chatId;

        if (empty($targetChatId)) {
            Log::warning('Target chat ID not configured', [
                'send_to_channel' => $sendToChannel,
                'has_chat_id' => !empty($this->chatId),
                'has_channel_id' => !empty($this->channelId),
            ]);
            return false;
        }

        try {
            // Format message with context
            $formattedMessage = $this->formatMessage($message, $context, $sendToChannel);

            $response = Http::post(
                "https://api.telegram.org/bot{$this->botToken}/sendMessage",
                [
                    'chat_id' => $targetChatId,
                    'text' => $formattedMessage,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            );

            if ($response->successful()) {
                Log::info('Telegram notification sent', [
                    'message' => substr($message, 0, 100) . '...',
                    'send_to_channel' => $sendToChannel,
                    'target_chat' => $sendToChannel ? 'channel' : 'private',
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
        return !empty($this->botToken) && (!empty($this->chatId) || !empty($this->channelId));
    }

    /**
     * Format message with HTML markup and context data
     */
    private function formatMessage(string $message, array $context, bool $isChannel = false): string
    {
        if ($isChannel) {
            return $this->formatChannelMessage($message, $context);
        } else {
            return $this->formatPrivateMessage($message, $context);
        }
    }

    /**
     * Format message for channel (public)
     */
    private function formatChannelMessage(string $message, array $context): string
    {
        // For channel messages, we want a cleaner format
        $formatted = "🚀 <b>SignalStorm</b>\n\n";
        $formatted .= $message;

        // Add essential context only (filter out internal flags)
        $filteredContext = array_filter($context, function($key) {
            return !in_array($key, ['send_to_channel', 'type']);
        }, ARRAY_FILTER_USE_KEY);

        if (!empty($filteredContext)) {
            $formatted .= "\n\n";
            foreach ($filteredContext as $key => $value) {
                if (in_array($key, ['symbol', 'signal', 'confidence', 'model_version'])) {
                    $formatted .= "• <i>" . ucfirst(str_replace('_', ' ', $key)) . ":</i> ";
                    $formatted .= $this->formatValue($value) . "\n";
                }
            }
        }

        return $formatted;
    }

    /**
     * Format message for private chat (admin)
     */
    private function formatPrivateMessage(string $message, array $context): string
    {
        $formatted = "🔔 <b>SignalStorm Alert</b>\n\n";
        $formatted .= $message . "\n";

        // Filter out internal flags
        $filteredContext = array_filter($context, function($key) {
            return !in_array($key, ['send_to_channel', 'type']);
        }, ARRAY_FILTER_USE_KEY);

        if (!empty($filteredContext)) {
            $formatted .= "\n<b>Details:</b>\n";

            foreach ($filteredContext as $key => $value) {
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

    /**
     * Send message to private chat (admin PV)
     */
    public function sendToPrivate(string $message, array $context = []): bool
    {
        $context['send_to_channel'] = false;
        return $this->send($message, $context);
    }

    /**
     * Send message to channel (public)
     */
    public function sendToChannel(string $message, array $context = []): bool
    {
        $context['send_to_channel'] = true;
        return $this->send($message, $context);
    }
}

