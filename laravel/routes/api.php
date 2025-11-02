<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Models\Candle;
use App\Models\Prediction;
use App\Models\ModelMetric;
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

Route::prefix('models')->group(function () {
    // List all trained models
    Route::get('/', function () {
        try {
            $trainerUrl = config('services.trainer.url', 'http://python-trainer:8001');
            $response = Http::get("{$trainerUrl}/models");

            if ($response->successful()) {
                $data = $response->json();

                // Enhance with database metrics if available
                if (isset($data['details'])) {
                    foreach ($data['details'] as &$model) {
                        $metrics = ModelMetric::where('model_version', $model['model_version'])
                            ->get()
                            ->keyBy('metric_name');

                        if ($metrics->isNotEmpty()) {
                            $model['metrics'] = [
                                'train_loss' => $metrics->get('train_loss')?->metric_value,
                                'val_loss' => $metrics->get('val_loss')?->metric_value,
                                'train_mae' => $metrics->get('train_mae')?->metric_value,
                                'val_mae' => $metrics->get('val_mae')?->metric_value,
                            ];
                        }
                    }
                }

                return response()->json($data);
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch models from trainer service'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });

    // Get specific model info
    Route::get('/{model_version}/info', function ($model_version) {
        try {
            $trainerUrl = config('services.trainer.url', 'http://python-trainer:8001');
            $response = Http::get("{$trainerUrl}/models/{$model_version}/info");

            if ($response->successful()) {
                $data = $response->json();

                // Add database metrics
                $metrics = ModelMetric::where('model_version', $model_version)
                    ->get()
                    ->keyBy('metric_name');

                if ($metrics->isNotEmpty()) {
                    $data['metrics'] = [
                        'train_loss' => $metrics->get('train_loss')?->metric_value,
                        'val_loss' => $metrics->get('val_loss')?->metric_value,
                        'train_mae' => $metrics->get('train_mae')?->metric_value,
                        'val_mae' => $metrics->get('val_mae')?->metric_value,
                    ];
                    $data['trained_at'] = $metrics->first()?->created_at;
                }

                return response()->json($data);
            }

            return response()->json([
                'success' => false,
                'error' => 'Model not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });

    // Download model file
    Route::get('/{model_version}/download', function ($model_version) {
        try {
            $trainerUrl = config('services.trainer.url', 'http://python-trainer:8001');
            $response = Http::get("{$trainerUrl}/models/{$model_version}/download");

            if ($response->successful()) {
                return response($response->body())
                    ->header('Content-Type', 'application/octet-stream')
                    ->header('Content-Disposition', "attachment; filename=\"{$model_version}.h5\"");
            }

            return response()->json([
                'success' => false,
                'error' => 'Model not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

