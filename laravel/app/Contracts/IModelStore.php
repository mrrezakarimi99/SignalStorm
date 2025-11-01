<?php

namespace App\Contracts;

/**
 * Interface IModelStore
 *
 * Abstraction for model storage (local disk, MinIO, S3, etc.)
 */
interface IModelStore
{
    /**
     * Store a model file
     *
     * @param string $modelVersion Model identifier
     * @param string $filePath Local file path
     * @return bool Success status
     */
    public function store(string $modelVersion, string $filePath): bool;

    /**
     * Retrieve a model file
     *
     * @param string $modelVersion
     * @return string|null Path to retrieved model file
     */
    public function retrieve(string $modelVersion): ?string;

    /**
     * Check if model exists
     *
     * @param string $modelVersion
     * @return bool
     */
    public function exists(string $modelVersion): bool;

    /**
     * Delete a model
     *
     * @param string $modelVersion
     * @return bool
     */
    public function delete(string $modelVersion): bool;

    /**
     * List all stored models
     *
     * @return array
     */
    public function list(): array;
}

