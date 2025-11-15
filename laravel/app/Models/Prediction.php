<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Prediction Model
 *
 * Stores ML model predictions
 */
class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'interval',
        'model_version',
        'current_price',
        'predicted_price',
        'price_change_percent',
        'signal',
        'confidence',
        'prediction_time',
        'target_time',
        'actual_price',
        'actual_change_percent',
        'accuracy',
        'features',
        'metadata',
        'notified_at',
    ];

    protected $casts = [
        'current_price' => 'decimal:8',
        'predicted_price' => 'decimal:8',
        'price_change_percent' => 'decimal:4',
        'confidence' => 'decimal:4',
        'actual_price' => 'decimal:8',
        'actual_change_percent' => 'decimal:4',
        'accuracy' => 'decimal:4',
        'prediction_time' => 'datetime',
        'target_time' => 'datetime',
        'notified_at' => 'datetime',
        'features' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Scope: Get predictions for specific symbol
     */
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Scope: Get predictions for specific interval
     */
    public function scopeForInterval($query, string $interval)
    {
        return $query->where('interval', $interval);
    }

    /**
     * Scope: Get predictions for specific model version
     */
    public function scopeForModel($query, string $modelVersion)
    {
        return $query->where('model_version', $modelVersion);
    }

    /**
     * Scope: Get predictions with specific signal
     */
    public function scopeWithSignal($query, string $signal)
    {
        return $query->where('signal', $signal);
    }

    /**
     * Scope: Get high confidence predictions
     */
    public function scopeHighConfidence($query, float $minConfidence = 70)
    {
        return $query->where('confidence', '>=', $minConfidence);
    }

    /**
     * Scope: Get recent predictions
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope: Get accurate predictions (where actual price is set)
     */
    public function scopeVerified($query)
    {
        return $query->whereNotNull('actual_price');
    }
}

