<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemReport extends Model
{
    use HasFactory;

    public const TYPE_LOST = 'lost';
    public const TYPE_FOUND = 'found';

    public const STATUS_LOST = 'lost';
    public const STATUS_FOUND = 'found';
    public const STATUS_POTENTIAL_MATCH = 'potential_match';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'user_id',
        'category_id',
        'report_type',
        'item_name',
        'description',
        'image_url',
        'date_lost_or_found',
        'location_name',
        'latitude',
        'longitude',
        'status',
        'identifying_details',
    ];

    protected function casts(): array
    {
        return [
            'date_lost_or_found' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ItemClaim::class);
    }

    /**
     * Claims still active against this report (not yet rejected) — used to
     * decide whether the report should revert to its original lost/found
     * status when a claim is rejected.
     */
    public function activeClaims(): HasMany
    {
        return $this->claims()->whereIn('status', ['pending', 'approved']);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_LOST, self::STATUS_FOUND, self::STATUS_POTENTIAL_MATCH]);
    }

    /**
     * The opposite report type — a "lost" report's possible matches are
     * "found" reports, and vice versa.
     */
    public function oppositeType(): string
    {
        return $this->report_type === self::TYPE_LOST ? self::TYPE_FOUND : self::TYPE_LOST;
    }
}
