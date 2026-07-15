<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    public const REASON_FAKE_REQUEST = 'fake_request';
    public const REASON_SPAM = 'spam';
    public const REASON_INAPPROPRIATE_BEHAVIOUR = 'inappropriate_behaviour';
    public const REASON_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACTION_TAKEN = 'action_taken';

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'assistance_request_id',
        'reason',
        'description',
        'status',
        'admin_notes',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function assistanceRequest(): BelongsTo
    {
        return $this->belongsTo(AssistanceRequest::class);
    }
}
