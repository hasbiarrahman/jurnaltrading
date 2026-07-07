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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('type'); // BUY or SELL
            $table->decimal('price', 16, 8);
            $table->decimal('amount', 16, 8);
            $table->decimal('total', 16, 8);
            $table->dateTime('trade_time');
            $table->decimal('stoch_rsi_k', 5, 2)->nullable();
            $table->decimal('stoch_rsi_d', 5, 2)->nullable();
            $table->string('divergence')->nullable(); // Bullish, Bearish, None
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
