<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_HELPER = 'helper';
    public const ROLE_REQUESTER = 'requester';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'address',
        'avatar_url',
        'onesignal_player_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function helpOffers(): HasMany
    {
        return $this->hasMany(HelpOffer::class, 'helper_id');
    }

    public function assistanceRequests(): HasMany
    {
        return $this->hasMany(AssistanceRequest::class, 'requester_id');
    }

    /**
     * Ratings this user has received from others.
     */
    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function averageRating(): ?float
    {
        $avg = $this->ratingsReceived()->avg('stars');

        return $avg !== null ? round($avg, 1) : null;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isHelper(): bool
    {
        return $this->role === self::ROLE_HELPER;
    }

    public function isRequester(): bool
    {
        return $this->role === self::ROLE_REQUESTER;
    }
}
