<?php

namespace App\Contracts;

/**
 * Interface ITrainer
 *
 * Abstraction for ML training service
 * Communicates with Python microservice
 */
interface ITrainer
{
    /**
     * Train a new model
     *
     * @param array $data Training data
     * @param array $config Training configuration
     * @return array Training results (model_version, metrics, etc.)
     */
    public function train(array $data, array $config = []): array;

    /**
     * Get prediction from trained model
     *
     * @param array $features Input features
     * @param string|null $modelVersion Specific model version (null for latest)
     * @return array Prediction results
     */
    public function predict(array $features, ?string $modelVersion = null): array;

    /**
     * Get model information
     *
     * @param string|null $modelVersion
     * @return array Model metadata
     */
    public function getModelInfo(?string $modelVersion = null): array;

    /**
     * Check if trainer service is healthy
     *
     * @return bool
     */
    public function healthCheck(): bool;
}

