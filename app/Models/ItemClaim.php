<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemClaim extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'item_report_id',
        'claimant_id',
        'claim_message',
        'proof_description',
        'proof_image_url',
        'status',
        'verified_at',
        'returned_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    public function itemReport(): BelongsTo
    {
        return $this->belongsTo(ItemReport::class);
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimant_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * The claimant's answers to the item report's verification questions
     * (if any). Only ever exposed to the finder (report owner) alongside
     * the expected_answer — see ItemClaimController::answers().
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ClaimAnswer::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function isReturned(): bool
    {
        return $this->returned_at !== null;
    }
}
