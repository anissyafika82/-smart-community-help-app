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
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_EMERGENCY = 'emergency';

    protected $fillable = [
        'help_offer_id',
        'helper_id',
        'category_id',
        'requester_id',
        'quantity',
        'priority',
        'is_sos',
        'scheduled_at',
        'latitude',
        'longitude',
        'address',
        'helper_latitude',
        'helper_longitude',
        'helper_location_updated_at',
        'status',
        'notes',
        'proof_image_url',
        'requested_at',
        'resolved_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_sos' => 'boolean',
            'scheduled_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'helper_latitude' => 'decimal:7',
            'helper_longitude' => 'decimal:7',
            'helper_location_updated_at' => 'datetime',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function helpOffer(): BelongsTo
    {
        return $this->belongsTo(HelpOffer::class);
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
