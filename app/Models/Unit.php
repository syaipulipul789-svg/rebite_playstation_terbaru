<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\UnitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'hourly_rate',
        'status',
        'location',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function rentalSessions(): HasMany
    {
        return $this->hasMany(RentalSession::class);
    }

    public function runningSession(): HasOne
    {
        return $this->hasOne(RentalSession::class)
            ->where('status', 'RUNNING')
            ->latest('start_time');
    }

    /**
     * Booking reservasi yang menempel ke unit (kolom `console_id`).
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'console_id');
    }

    /**
     * Booking yang sudah dikonfirmasi dan masih mengunci slot unit ini,
     * diurutkan berdasarkan jam mulai. Dipakai untuk tahu apakah unit sedang
     * "dipesan" dan kapan slot berikutnya bebas.
     */
    public function confirmedBookings(): HasMany
    {
        return $this->bookings()
            ->where('status', BookingStatus::CONFIRMED)
            ->orderBy('start_time');
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', UnitStatus::READY);
    }

    public function scopeMonitorable(Builder $query): Builder
    {
        return $query->orderBy('type')->orderBy('code');
    }

    public function isFree(): bool
    {
        return $this->hourly_rate <= 0;
    }
}
