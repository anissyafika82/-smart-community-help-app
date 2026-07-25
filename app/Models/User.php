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
    public const ROLE_USER = 'user';

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
        'telegram_chat_id',
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

    public function itemReports(): HasMany
    {
        return $this->hasMany(ItemReport::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(ItemClaim::class, 'claimant_id');
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

    /**
     * Items this user found and successfully returned to their owner —
     * the basis for the Finder trust badge. Only counts "found" reports
     * (not the lost-report-returned-via-claimant path) to keep the trust
     * score simple to reason about.
     */
    public function itemsReturnedCount(): int
    {
        return $this->itemReports()
            ->where('report_type', ItemReport::TYPE_FOUND)
            ->where('status', ItemReport::STATUS_RETURNED)
            ->count();
    }

    /**
     * Finder trust badge, computed from successful returns rather than
     * stored — always reflects the current total, no award event to miss.
     */
    public function badge(): ?string
    {
        $returned = $this->itemsReturnedCount();

        foreach (self::BADGE_THRESHOLDS as $badge => $threshold) {
            if ($returned >= $threshold) {
                return $badge;
            }
        }

        return null;
    }
}
