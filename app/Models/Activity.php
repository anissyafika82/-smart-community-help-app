<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    public const TYPE_ITEM_REPORTED = 'item_reported';
    public const TYPE_CLAIM_SUBMITTED = 'claim_submitted';
    public const TYPE_CLAIM_VERIFIED = 'claim_verified';
    public const TYPE_CLAIM_REJECTED = 'claim_rejected';
    public const TYPE_ITEM_RETURNED = 'item_returned';
    public const TYPE_RATING_SUBMITTED = 'rating_submitted';

    protected $fillable = [
        'user_id',
        'type',
        'description',
        'item_claim_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itemClaim(): BelongsTo
    {
        return $this->belongsTo(ItemClaim::class);
    }

    public static function log(int $userId, string $type, string $description, ?int $itemClaimId = null): self
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'description' => $description,
            'item_claim_id' => $itemClaimId,
        ]);
    }
}
