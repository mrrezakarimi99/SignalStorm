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
        'model_version',
        'predicted_price',
        'confidence',
        'features',
        'actual_price',
    ];

    protected $casts = [
        'predicted_price' => 'decimal:8',
        'confidence' => 'decimal:4',
        'actual_price' => 'decimal:8',
        'features' => 'array',
    ];

    /**
     * Scope: Get predictions for specific symbol
     */
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Scope: Get predictions for specific model version
     */
    public function scopeForModel($query, string $modelVersion)
    {
        return $query->where('model_version', $modelVersion);
    }

    /**
     * Scope: Get recent predictions
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}

