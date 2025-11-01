<?php

namespace App\Contracts;

/**
 * Interface INotifier
 *
 * Abstraction for notification channels (Telegram, Console, Slack, etc.)
 * Follows Open/Closed Principle - open for extension, closed for modification
 */
interface INotifier
{
    /**
     * Send notification message
     *
     * @param string $message Message content
     * @param array $context Additional context data
     * @return bool Success status
     */
    public function send(string $message, array $context = []): bool;

    /**
     * Get notifier name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if notifier is enabled/configured
     *
     * @return bool
     */
    public function isEnabled(): bool;
}

