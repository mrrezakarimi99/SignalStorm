<?php

namespace App\Services;

use App\Contracts\ITrainer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Trainer Service (Python ML Service Client)
 *
 * Single Responsibility: Communicate with Python ML microservice
 * Dependency Inversion: Depends on ITrainer abstraction
 */
class TrainerService implements ITrainer
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.trainer.url', 'http://python-trainer:8001');
        $this->timeout = config('services.trainer.timeout', 300); // 5 minutes for training
    }

    /**
     * Train a new model via Python service
     */
    public function train(array $data, array $config = []): array
    {
        try {
            Log::info('Initiating model training', [
                'data_count' => count($data),
                'config' => $config,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/train", [
                    'data' => $data,
                    'config' => $config,
                ]);

            if (!$response->successful()) {
                Log::error('Training request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Training request failed: ' . $response->body(),
                ];
            }

            $result = $response->json();

            Log::info('Model training completed', [
                'model_version' => $result['model_version'] ?? null,
                'metrics' => $result['metrics'] ?? null,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Training service error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get prediction from trained model
     */
    public function predict(array $features, ?string $modelVersion = null): array
    {
        try {
            $payload = ['features' => $features];

            if ($modelVersion) {
                $payload['model_version'] = $modelVersion;
            }

            $response = Http::timeout(30)
                ->post("{$this->baseUrl}/predict", $payload);

            if (!$response->successful()) {
                Log::error('Prediction request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Prediction failed',
                ];
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Prediction service error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get model information
     */
    public function getModelInfo(?string $modelVersion = null): array
    {
        try {
            $url = $modelVersion
                ? "{$this->baseUrl}/model/{$modelVersion}"
                : "{$this->baseUrl}/model/info";

            $response = Http::timeout(10)->get($url);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to get model info',
                ];
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Model info request failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check trainer service health
     */
    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Trainer service health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

