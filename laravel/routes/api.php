<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Candle;
use App\Models\Prediction;
use App\Jobs\FetchHistoricalDataJob;
use App\Jobs\TrainModelJob;

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'SignalStorm API',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::prefix('data')->group(function () {
    // Fetch historical data
    Route::post('/fetch', function (Request $request) {
        $request->validate([
            'symbol' => 'required|string',
            'interval' => 'required|string',
            'limit' => 'integer|min:1|max:1000',
        ]);

        FetchHistoricalDataJob::dispatch(
            $request->input('symbol'),
            $request->input('interval'),
            $request->input('limit', 100)
        );

        return response()->json([
            'success' => true,
            'message' => 'Data fetch job queued',
            'symbol' => $request->input('symbol'),
            'interval' => $request->input('interval'),
        ]);
    });

    // Get candles
    Route::get('/candles/{symbol}/{interval}', function ($symbol, $interval) {
        $candles = Candle::forSymbol($symbol)
            ->forInterval($interval)
            ->orderBy('open_time', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $candles->count(),
            'data' => $candles,
        ]);
    });
});

Route::prefix('model')->group(function () {
    // Train model
    Route::post('/train', function (Request $request) {
        $request->validate([
            'symbol' => 'required|string',
            'interval' => 'required|string',
            'limit' => 'integer|min:50|max:10000',
            'epochs' => 'integer|min:1|max:1000',
            'batch_size' => 'integer|min:1|max:256',
        ]);

        TrainModelJob::dispatch(
            $request->input('symbol'),
            $request->input('interval'),
            $request->input('limit', 1000),
            [
                'epochs' => $request->input('epochs', 50),
                'batch_size' => $request->input('batch_size', 32),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Training job queued',
            'symbol' => $request->input('symbol'),
            'interval' => $request->input('interval'),
        ]);
    });
});

Route::prefix('predictions')->group(function () {
    // Get latest predictions
    Route::get('/latest', function (Request $request) {
        $symbol = $request->query('symbol');
        $limit = $request->query('limit', 10);

        $query = Prediction::orderBy('created_at', 'desc')->limit($limit);

        if ($symbol) {
            $query->forSymbol($symbol);
        }

        $predictions = $query->get();

        return response()->json([
            'success' => true,
            'count' => $predictions->count(),
            'data' => $predictions,
        ]);
    });

    // Get predictions for symbol
    Route::get('/{symbol}', function ($symbol) {
        $predictions = Prediction::forSymbol($symbol)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'symbol' => $symbol,
            'count' => $predictions->count(),
            'data' => $predictions,
        ]);
    });
});

