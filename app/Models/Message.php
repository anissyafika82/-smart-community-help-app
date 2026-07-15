<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_report_id',
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

    public function itemReport(): BelongsTo
    {
        return $this->belongsTo(ItemReport::class);
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
     * Deterministic channel name for a chat thread scoped to one item
     * report — sorted user ids so both participants derive the same
     * channel regardless of who initiated the conversation.
     */
    public static function channelName(int $itemReportId, int $userIdA, int $userIdB): string
    {
        $sorted = [$userIdA, $userIdB];
        sort($sorted);

        return "chat.{$itemReportId}.{$sorted[0]}.{$sorted[1]}";
    }
}
