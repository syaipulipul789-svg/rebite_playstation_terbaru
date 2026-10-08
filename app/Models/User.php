<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Support\Phone;
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
        'phone',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'google_id',
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

    /**
     * Booking yang dibuat dari akun pelanggan ini. Untuk kasir/owner selalu
     * kosong karena mereka memakai POS, bukan form booking pelanggan.
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Nomor WhatsApp dalam bentuk lokal, atau `null` untuk akun staff yang
     * tidak punya nomor.
     */
    public function whatsappNumber(): ?string
    {
        return Phone::whatsappNumber($this->phone);
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

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * Nomor yang dipakai kasir saat menghubungi pelanggan soal booking-nya.
     * Akun staff tidak punya, jadi fallback ke kolom booking.
     */
    public function contactPhone(?string $fallback = null): string
    {
        return $this->phone ?: ($fallback ?? '');
    }

    public function getAvatarUrlAttribute(): string
    {
        return 'https://api.dicebear.com/7.x/initials/svg?seed='.urlencode($this->username ?? $this->name);
    }

    /**
     * Halaman milik sendiri untuk user ini — satu-satunya sumber kebenaran
     * "ke mana user diarahkan".
     *
     * Dipakai oleh `RoleBasedLoginResponse` (pasca-login), route `dashboard`,
     * dan landing page `/`. Sebelumnya ketiganya punya salinan logika sendiri
     * dan tidak selalu sama: `/dashboard` mengarahkan kasir ke grid unit
     * tanpa mengecek shift-nya, sehingga kasir tanpa shift kena dua redirect.
     */
    public function landingRoute(): string
    {
        if ($this->isCustomer()) {
            return 'customer.dashboard';
        }

        if ($this->isOwner()) {
            return 'owner.dashboard';
        }

        // Kasir dengan shift OPEN langsung ke grid unit. Tanpa shift, kasir
        // harus lewat modal awal kas lebih dulu.
        return $this->activeShift() === null ? 'shift.start' : 'units.index';
    }

    /**
     * Autentikasi memakai username (fallback ke email) untuk kasir.
     *
     * Akun pelanggan tidak punya username artificial: kolom `username` diisi
     * dengan nomor WhatsApp yang sudah dinormalkan, jadi satu kolom ini
     * sekaligus membuat pelanggan bisa login dengan nomor HP.
     */
    public static function findForLogin(string $login): ?self
    {
        $login = trim($login);

        $query = static::query()->where('username', $login)->orWhere('email', $login);

        // Nomor HP bisa diketik banyak cara ("+62 812-3456-7890", "628123..."),
        // jadi dicoba juga bentuk lokal hasil normalisasi.
        if (($normalized = Phone::normalize($login)) !== '') {
            $query->orWhere('phone', $normalized);
        }

        return $query->first();
    }
}
