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
        Schema::create('telegram_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('chat_id')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('telegram_price_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();
            $table->double('last_alert_price');
            $table->timestamp('last_alert_time')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_price_alerts');
        Schema::dropIfExists('telegram_recipients');
    }
};
