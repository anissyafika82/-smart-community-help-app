<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_claim_id',
        'rated_by_user_id',
        'rated_user_id',
        'stars',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
        ];
    }

    public function itemClaim(): BelongsTo
    {
        return $this->belongsTo(ItemClaim::class);
    }

    public function ratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by_user_id');
    }

    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }
}
