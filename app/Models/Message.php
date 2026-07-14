<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'help_offer_id',
        'sender_id',
        'recipient_id',
        'body',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function helpOffer(): BelongsTo
    {
        return $this->belongsTo(HelpOffer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Deterministic channel name for a helper/requester chat thread scoped
     * to one help offer — sorted user ids so both participants derive the
     * same channel regardless of who initiated the conversation.
     */
    public static function channelName(int $helpOfferId, int $userIdA, int $userIdB): string
    {
        $sorted = [$userIdA, $userIdB];
        sort($sorted);

        return "chat.{$helpOfferId}.{$sorted[0]}.{$sorted[1]}";
    }
}
