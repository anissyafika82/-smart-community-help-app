<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks where a linked user is in a multi-step bot conversation (e.g.
 * /addfoundreport walks them through item name -> description -> category
 * -> location one message at a time). Telegram webhook calls are stateless
 * HTTP requests, so this table is what remembers "what step are we on"
 * between them.
 */
class TelegramConversationState extends Model
{
    protected $primaryKey = 'chat_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['chat_id', 'user_id', 'step', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
