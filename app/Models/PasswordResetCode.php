<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short-lived code sent to a user's linked Telegram chat so they can
 * reset their own password without needing a real email service (there
 * isn't one configured — see App\Services\TelegramService).
 */
class PasswordResetCode extends Model
{
    protected $fillable = ['user_id', 'code', 'expires_at'];

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
