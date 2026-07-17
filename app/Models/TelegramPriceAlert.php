<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramPriceAlert extends Model
{
    protected $table = 'telegram_price_alerts';

    protected $fillable = [
        'symbol',
        'last_alert_price',
        'last_alert_time',
    ];

    protected $dates = [
        'last_alert_time',
    ];
}
