<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_claim_id',
        'verification_question_id',
        'answer',
    ];

    public function itemClaim(): BelongsTo
    {
        return $this->belongsTo(ItemClaim::class);
    }

    public function verificationQuestion(): BelongsTo
    {
        return $this->belongsTo(VerificationQuestion::class);
    }
}
