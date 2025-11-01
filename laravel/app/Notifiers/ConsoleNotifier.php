<?php

namespace App\Notifiers;

use App\Contracts\INotifier;
use Illuminate\Support\Facades\Log;

/**
 * Console Notifier
 *
 * Outputs notifications to application logs
 * Useful for development and debugging
 */
class ConsoleNotifier implements INotifier
{
    public function send(string $message, array $context = []): bool
    {
        $formatted = $this->formatMessage($message, $context);

        Log::info('📢 NOTIFICATION', [
            'message' => $message,
            'context' => $context,
            'formatted' => $formatted,
        ]);

        // Also output to console if running in CLI
        if (app()->runningInConsole()) {
            echo "\n" . str_repeat('=', 70) . "\n";
            echo $formatted . "\n";
            echo str_repeat('=', 70) . "\n";
        }

        return true;
    }

    public function getName(): string
    {
        return 'console';
    }

    public function isEnabled(): bool
    {
        return true; // Always enabled
    }

    /**
     * Format message for console output
     */
    private function formatMessage(string $message, array $context): string
    {
        $formatted = "🔔 SignalStorm Alert\n";
        $formatted .= str_repeat('-', 70) . "\n";
        $formatted .= $message . "\n";

        if (!empty($context)) {
            $formatted .= "\nDetails:\n";

            foreach ($context as $key => $value) {
                $formatted .= "  • " . ucfirst(str_replace('_', ' ', $key)) . ": ";
                $formatted .= $this->formatValue($value) . "\n";
            }
        }

        $formatted .= "\nTimestamp: " . now()->toDateTimeString() . "\n";

        return $formatted;
    }

    /**
     * Format context value for display
     */
    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_float($value)) {
            return number_format($value, 2);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}

