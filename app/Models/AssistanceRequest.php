<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistanceRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ON_THE_WAY = 'on_the_way';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'help_offer_id',
        'requester_id',
        'quantity',
        'priority',
        'status',
        'notes',
        'proof_image_url',
        'requested_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function helpOffer(): BelongsTo
    {
        return $this->belongsTo(HelpOffer::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
