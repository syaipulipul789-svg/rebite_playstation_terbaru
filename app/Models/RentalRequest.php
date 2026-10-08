<?php

namespace App\Models;

use App\Enums\RentalRequestStatus;
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

    public function hasRunningRentalSession(): bool
    {
        return $this->rentalSession !== null && $this->rentalSession->isRunning();
    }
}
