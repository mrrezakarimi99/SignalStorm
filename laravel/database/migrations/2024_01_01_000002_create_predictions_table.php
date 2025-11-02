<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('predictions', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('interval', 10)->index();
            $table->string('model_version', 50)->index();

            // Prediction data
            $table->decimal('current_price', 20, 8);
            $table->decimal('predicted_price', 20, 8);
            $table->decimal('price_change_percent', 10, 4);

            // Signal and confidence
            $table->enum('signal', ['BUY', 'SELL', 'HOLD'])->index();
            $table->decimal('confidence', 5, 2);

            // Timestamps
            $table->timestamp('prediction_time');
            $table->timestamp('target_time');

            // Actual results (for backtesting)
            $table->decimal('actual_price', 20, 8)->nullable();
            $table->decimal('actual_change_percent', 10, 4)->nullable();
            $table->decimal('accuracy', 5, 2)->nullable();

            // Metadata
            $table->json('features')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for querying
            $table->index(['symbol', 'created_at']);
            $table->index(['symbol', 'interval', 'created_at']);
            $table->index(['signal', 'confidence', 'created_at']);
            $table->index(['model_version', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictions');
    }
};

