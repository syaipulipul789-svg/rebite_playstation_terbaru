<?php

namespace App\Models;

use App\Enums\RentalRequestStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalRequest extends Model
{
    protected $fillable = [
        'rental_request_code',
        'customer_name',
        'customer_phone',
        'customer_whatsapp',
        'unit_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'package_id',
        'package_name',
        'hourly_rate',
        'total_price',
        'status',
        'notes',
        'confirmed_by',
        'confirmed_at',
        'rental_session_id',
        'user_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'duration_minutes' => 'integer',
        'hourly_rate' => 'decimal:2',
        'total_price' => 'decimal:2',
        'status' => RentalRequestStatus::class,
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(RatePackage::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rentalSession(): BelongsTo
    {
        return $this->belongsTo(RentalSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === RentalRequestStatus::PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === RentalRequestStatus::CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === RentalRequestStatus::CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === RentalRequestStatus::COMPLETED;
    }

    public function rentalRequestCode(): string
    {
        return $this->rental_request_code;
    }

    /**
     * Snapshot siap-kirim untuk halaman pelanggan (riwayat & respon JSON
     * modal "Ajukan Sewa") — bentuknya sama dengan yang dihasilkan saat
     * permintaan baru dibuat.
     *
     * @return array<string, mixed>
     */
    public function customerSnapshot(): array
    {
        return [
            'rental_code' => $this->rentalRequestCode(),
            'unit_name' => $this->unit?->name,
            'unit_code' => $this->unit?->code,
            'package_name' => $this->package_name,
            'start_time_label' => $this->start_time->format('d M Y H:i'),
            'end_time_label' => $this->end_time->format('d M Y H:i'),
            'total_price_label' => Money::format($this->total_price),
            'notes' => $this->notes,
            'created_at_label' => $this->created_at->diffForHumans(),
            'status' => [
                'label' => $this->status->label(),
                'badge_class' => $this->status->badgeClass(),
            ],
        ];
    }

    public function hasRunningRentalSession(): bool
    {
        return $this->rentalSession !== null && $this->rentalSession->isRunning();
    }
}
