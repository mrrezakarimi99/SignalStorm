<?php

namespace App\Services;

use App\Contracts\INotifier;
use Illuminate\Support\Facades\Log;

/**
 * Notification Service
 *
 * Manages multiple notifier strategies
 * Follows Open/Closed Principle - can add notifiers without modifying this class
 */
class NotificationService
{
    /** @var array<INotifier> */
    private array $notifiers = [];

    public function __construct()
    {
        // Notifiers are registered via service provider based on configuration
    }

    /**
     * Register a notifier
     */
    public function addNotifier(INotifier $notifier): void
    {
        if ($notifier->isEnabled()) {
            $this->notifiers[$notifier->getName()] = $notifier;
            Log::info("Notifier registered: {$notifier->getName()}");
        }
    }

    /**
     * Send notification to all registered notifiers
     */
    public function notify(string $message, array $context = []): array
    {
        $results = [];

        foreach ($this->notifiers as $name => $notifier) {
            try {
                $success = $notifier->send($message, $context);
                $results[$name] = $success;

                if (!$success) {
                    Log::warning("Notifier failed: {$name}");
                }
            } catch (\Exception $e) {
                Log::error("Notifier exception: {$name}", [
                    'error' => $e->getMessage(),
                ]);
                $results[$name] = false;
            }
        }

        return $results;
    }

    /**
     * Send notification to specific notifier
     */
    public function notifyVia(string $notifierName, string $message, array $context = []): bool
    {
        if (!isset($this->notifiers[$notifierName])) {
            Log::warning("Notifier not found: {$notifierName}");
            return false;
        }

        try {
            return $this->notifiers[$notifierName]->send($message, $context);
        } catch (\Exception $e) {
            Log::error("Notifier exception: {$notifierName}", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get all registered notifiers
     */
    public function getNotifiers(): array
    {
        return array_keys($this->notifiers);
    }

    /**
     * Check if a specific notifier is registered
     */
    public function hasNotifier(string $name): bool
    {
        return isset($this->notifiers[$name]);
    }
}

