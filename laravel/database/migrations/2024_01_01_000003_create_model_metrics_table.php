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
        Schema::create('model_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('model_version', 50)->index();
            $table->string('metric_name', 50)->index();
            $table->decimal('metric_value', 20, 8);
            $table->string('dataset_type', 20)->default('train'); // train, validation, test
            $table->timestamps();

            // Index for querying model metrics
            $table->index(['model_version', 'metric_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_metrics');
    }
};

