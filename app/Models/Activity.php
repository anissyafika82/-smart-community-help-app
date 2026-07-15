<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    public const TYPE_REQUEST_CREATED = 'request_created';
    public const TYPE_SOS_CREATED = 'sos_created';
    public const TYPE_REQUEST_ACCEPTED = 'request_accepted';
    public const TYPE_REQUEST_ON_THE_WAY = 'request_on_the_way';
    public const TYPE_REQUEST_COMPLETED = 'request_completed';
    public const TYPE_RATING_SUBMITTED = 'rating_submitted';

    protected $fillable = [
        'user_id',
        'type',
        'description',
        'assistance_request_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assistanceRequest(): BelongsTo
    {
        return $this->belongsTo(AssistanceRequest::class);
    }

    public static function log(int $userId, string $type, string $description, ?int $assistanceRequestId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'assistance_request_id' => $assistanceRequestId,
        ]);
    }
}
