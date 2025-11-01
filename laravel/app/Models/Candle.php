<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Candle Model
 *
 * Represents OHLC candle data stored in database
 */
class Candle extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'interval',
        'open_time',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'close_time',
    ];

    protected $casts = [
        'open_time' => 'integer',
        'close_time' => 'integer',
        'open' => 'decimal:8',
        'high' => 'decimal:8',
        'low' => 'decimal:8',
        'close' => 'decimal:8',
        'volume' => 'decimal:8',
    ];

    /**
     * Scope: Get candles for specific symbol
     */
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    /**
     * Scope: Get candles for specific interval
     */
    public function scopeForInterval($query, string $interval)
    {
        return $query->where('interval', $interval);
    }

    /**
     * Scope: Get latest candles
     */
    public function scopeLatest($query, int $limit = 100)
    {
        return $query->orderBy('open_time', 'desc')->limit($limit);
    }

    /**
     * Scope: Get candles in time range
     */
    public function scopeInTimeRange($query, int $startTime, int $endTime)
    {
        return $query->whereBetween('open_time', [$startTime, $endTime]);
    }
}

