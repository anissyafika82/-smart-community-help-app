<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short-lived, one-time code a user generates in the app and then sends
 * to the Telegram bot (/link CODE) to prove they own both the FindBack
 * account and the Telegram chat, before the bot lets them post reports.
 */
class TelegramLinkCode extends Model
{
    protected $fillable = ['code', 'user_id', 'expires_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
