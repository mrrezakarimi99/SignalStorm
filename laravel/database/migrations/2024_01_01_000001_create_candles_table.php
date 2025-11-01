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
        Schema::create('candles', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('interval', 10)->index();
            $table->bigInteger('open_time')->index();
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->decimal('volume', 20, 8);
            $table->bigInteger('close_time');
            $table->timestamps();

            // Composite index for common queries
            $table->index(['symbol', 'interval', 'open_time']);

            // Unique constraint to prevent duplicates
            $table->unique(['symbol', 'interval', 'open_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};

