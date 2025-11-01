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
            $table->string('model_version', 50)->index();
            $table->decimal('predicted_price', 20, 8);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('features')->nullable();
            $table->decimal('actual_price', 20, 8)->nullable();
            $table->timestamps();

            // Index for querying recent predictions
            $table->index(['symbol', 'created_at']);
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

