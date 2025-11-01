<?php

namespace App\Providers;

use App\Contracts\IDataProvider;
use App\Contracts\INotifier;
use App\Contracts\INormalizer;
use App\Contracts\ITrainer;
use App\Notifiers\ConsoleNotifier;
use App\Notifiers\TelegramNotifier;
use App\Providers\DataProviders\BinanceProvider;
use App\Providers\DataProviders\MockProvider;
use App\Services\DataNormalizer;
use App\Services\NotificationService;
use App\Services\TrainerService;
use Illuminate\Support\ServiceProvider;

/**
 * SignalStorm Service Provider
 *
 * Composition Root - Wires up all dependencies
 * Implements Dependency Inversion Principle
 */
class SignalStormServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * This is where we bind interfaces to concrete implementations
     * Strategy pattern: Choose implementation based on configuration
     */
    public function register(): void
    {
        // Register Data Provider (Strategy Pattern)
        $this->app->singleton(IDataProvider::class, function ($app) {
            $provider = config('services.data_provider', 'mock');

            return match($provider) {
                'binance' => new BinanceProvider(),
                default => new MockProvider(),
            };
        });

        // Register Normalizer
        $this->app->singleton(INormalizer::class, DataNormalizer::class);

        // Register Trainer Service
        $this->app->singleton(ITrainer::class, TrainerService::class);

        // Register Notification Service with multiple notifiers
        $this->app->singleton(NotificationService::class, function ($app) {
            $notificationService = new NotificationService();

            // Get configured notifiers from env (comma-separated)
            $notifiers = explode(',', config('services.notifiers', 'console'));

            foreach ($notifiers as $notifier) {
                $notifier = trim($notifier);

                $instance = match($notifier) {
                    'telegram' => new TelegramNotifier(),
                    'console' => new ConsoleNotifier(),
                    default => null,
                };

                if ($instance) {
                    $notificationService->addNotifier($instance);
                }
            }

            return $notificationService;
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Log which providers are being used
        $dataProvider = $this->app->make(IDataProvider::class);
        \Log::info('SignalStorm initialized', [
            'data_provider' => $dataProvider->getName(),
            'notifiers' => $this->app->make(NotificationService::class)->getNotifiers(),
        ]);
    }
}

