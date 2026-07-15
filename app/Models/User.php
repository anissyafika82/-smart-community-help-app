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

    public const BADGE_BRONZE = 'bronze';
    public const BADGE_SILVER = 'silver';
    public const BADGE_GOLD = 'gold';

    private const BADGE_THRESHOLDS = [
        self::BADGE_GOLD => 30,
        self::BADGE_SILVER => 15,
        self::BADGE_BRONZE => 5,
    ];

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
     * Requests this user has helped with (as the accepting volunteer),
     * regardless of whether they came from a help offer or an SOS request.
     */
    public function helpedRequests(): HasMany
    {
        return $this->hasMany(AssistanceRequest::class, 'helper_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function reportsFiled(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
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

    public function completedHelpsCount(): int
    {
        return $this->helpedRequests()->where('status', AssistanceRequest::STATUS_COMPLETED)->count();
    }

    /**
     * Volunteer badge, computed from completed-help count rather than
     * stored — always reflects the current total, no award event to miss.
     */
    public function badge(): ?string
    {
        $completed = $this->completedHelpsCount();

        foreach (self::BADGE_THRESHOLDS as $badge => $threshold) {
            if ($completed >= $threshold) {
                return $badge;
            }
        }

        return null;
    }
}
