<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramRecipient extends Model
{
    protected $table = 'telegram_recipients';

    protected $fillable = [
        'name',
        'chat_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
