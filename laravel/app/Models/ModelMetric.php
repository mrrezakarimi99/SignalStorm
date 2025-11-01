<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Model Metric
 *
 * Stores training metrics for ML models
 */
class ModelMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_version',
        'metric_name',
        'metric_value',
        'dataset_type',
    ];

    protected $casts = [
        'metric_value' => 'decimal:8',
    ];

    /**
     * Scope: Get metrics for specific model
     */
    public function scopeForModel($query, string $modelVersion)
    {
        return $query->where('model_version', $modelVersion);
    }

    /**
     * Scope: Get specific metric
     */
    public function scopeMetric($query, string $metricName)
    {
        return $query->where('metric_name', $metricName);
    }

    /**
     * Scope: Get metrics for dataset type (train, validation, test)
     */
    public function scopeForDataset($query, string $datasetType)
    {
        return $query->where('dataset_type', $datasetType);
    }
}

