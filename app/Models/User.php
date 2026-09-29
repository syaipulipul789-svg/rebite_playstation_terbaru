<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function rentalSessions(): HasMany
    {
        return $this->hasMany(RentalSession::class);
    }

    public function activeShift(): ?Shift
    {
        return $this->shifts()
            ->where('status', ShiftStatus::OPEN)
            ->latest('start_time')
            ->first();
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function isCashier(): bool
    {
        return $this->role === UserRole::KASIR;
    }

    public function getAvatarUrlAttribute(): string
    {
        return 'https://api.dicebear.com/7.x/initials/svg?seed='.urlencode($this->username ?? $this->name);
    }

    /**
     * Autentikasi memakai username (fallback ke email) untuk kasir.
     */
    public static function findForLogin(string $login): ?self
    {
        return static::query()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->first();
    }
}
