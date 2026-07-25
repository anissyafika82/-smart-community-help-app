<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VerificationQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_report_id',
        'question',
        'expected_answer',
    ];

    public function itemReport(): BelongsTo
    {
        return $this->belongsTo(ItemReport::class);
    }

    public function claimAnswers(): HasMany
    {
        return $this->hasMany(ClaimAnswer::class);
    }
}
