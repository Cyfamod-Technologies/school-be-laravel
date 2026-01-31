<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiChatLog extends Model
{
    protected $table = 'ai_chat_logs';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'school_id',
        'user_id',
        'user_message',
        'assistant_reply',
        'intent',
    ];

    protected static function booted()
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
