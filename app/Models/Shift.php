<?php

namespace App\Models;

use App\Enums\ShiftStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_time',
        'end_time',
        'starting_cash',
        'system_cash_revenue',
        'system_qris_revenue',
        'actual_physical_cash',
        'discrepancy',
        'note',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'closed_at' => 'datetime',
            'starting_cash' => 'decimal:2',
            'system_cash_revenue' => 'decimal:2',
            'system_qris_revenue' => 'decimal:2',
            'actual_physical_cash' => 'decimal:2',
            'discrepancy' => 'decimal:2',
            'status' => ShiftStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rentalSessions(): HasMany
    {
        return $this->hasMany(RentalSession::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::OPEN);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', ShiftStatus::CLOSED);
    }

    public function isOpen(): bool
    {
        return $this->status === ShiftStatus::OPEN;
    }

    /**
     * Total pendapatan seluruh metode pembayaran pada shift ini.
     */
    public function totalSystemRevenue(): float
    {
        return (float) $this->system_cash_revenue + (float) $this->system_qris_revenue;
    }

    /**
     * Total Ekspektasi Kas = Modal Awal + Total Pendapatan Tunai Shift Berjalan.
     */
    public function expectedCash(): float
    {
        return (float) $this->starting_cash + (float) $this->system_cash_revenue;
    }

    public function hasDiscrepancy(): bool
    {
        return $this->discrepancy !== null && (float) $this->discrepancy !== 0.0;
    }

    public function durationInMinutes(): int
    {
        $end = $this->end_time ?? now();

        return (int) $this->start_time->diffInMinutes($end);
    }
}
