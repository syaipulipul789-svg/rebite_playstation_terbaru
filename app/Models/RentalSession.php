<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'shift_id',
        'user_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'planned_minutes',
        'is_free_play',
        'package_name',
        'rental_fee',
        'status',
        'payment_method',
        'extra_minutes',
        'extra_charges_total',
        'time_up_notified_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'time_up_notified_at' => 'datetime',
            'is_free_play' => 'boolean',
            'duration_minutes' => 'integer',
            'planned_minutes' => 'integer',
            'extra_minutes' => 'integer',
            'rental_fee' => 'decimal:2',
            'extra_charges_total' => 'integer',
            'status' => RentalSessionStatus::class,
            'payment_method' => PaymentMethod::class,
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', RentalSessionStatus::RUNNING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RentalSessionStatus::COMPLETED);
    }

    public function isRunning(): bool
    {
        return $this->status === RentalSessionStatus::RUNNING;
    }

    /**
     * Sisa waktu dalam detik, dihitung server-side lalu dikoreksi drift di client.
     */
    public function remainingSeconds(): int
    {
        $endAt = $this->start_time->copy()->addMinutes($this->planned_minutes);

        return max(0, now()->diffInSeconds($endAt, false));
    }

    public function elapsedSeconds(): int
    {
        return (int) $this->start_time->diffInSeconds(now());
    }

    public function itemsTotal(): float
    {
        return (float) $this->items()->sum('subtotal');
    }

    /**
     * Total tagihan = biaya sewa + seluruh pesanan F&B.
     */
    public function grandTotal(): float
    {
        return (float) $this->rental_fee + $this->itemsTotal();
    }

    /**
     * Biru = masih berjalan, kuning = waktu habis.
     */
    public function isTimeUp(): bool
    {
        return $this->remainingSeconds() <= 0;
    }
}
