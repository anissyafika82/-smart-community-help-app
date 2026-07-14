<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpOffer extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'helper_id',
        'category_id',
        'title',
        'description',
        'quantity',
        'unit',
        'available_until',
        'image_url',
        'location_address',
        'latitude',
        'longitude',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'available_until' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'quantity' => 'integer',
        ];
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function assistanceRequests(): HasMany
    {
        return $this->hasMany(AssistanceRequest::class);
    }

    /**
     * Requests still holding stock against this offer (pending, approved,
     * or already completed) — used to compute how much has been allocated.
     */
    public function activeRequests(): HasMany
    {
        return $this->assistanceRequests()->whereIn('status', ['pending', 'approved', 'completed']);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }
}
