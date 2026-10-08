<?php

namespace App\Services;

use App\Enums\RentalRequestStatus;
use App\Models\RatePackage;
use App\Models\RentalRequest;
use App\Models\Unit;
use App\Models\User;
use App\Support\AvailabilityConflict;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RentalRequestService
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * Membuat permintaan sewa (rental request) oleh pelanggan yang sedang login.
     * Status default PENDING, membutuhkan konfirmasi kasir (no.1).
     */
    public function createForCustomer(
        User $customer,
        Unit $unit,
        ?RatePackage $package,
        Carbon $startTime,
        Carbon $endTime,
        ?string $notes = null
    ): RentalRequest {
        if ($startTime->greaterThanOrEqualTo($endTime)) {
            throw new \InvalidArgumentException('Waktu selesai harus lebih besar dari waktu mulai.');
        }

        $duration = (int) $startTime->diffInMinutes($endTime, true);

        if ($duration < 60) {
            throw new \InvalidArgumentException('Durasi sewa minimal 1 jam.');
        }

        $this->ensureUnitAvailable($unit, $startTime, $endTime);

        $pricingResult = $this->pricing->calculate($unit, $package, $duration);

        return RentalRequest::create([
            'rental_request_code' => $this->generateCode(),
            'customer_name' => $customer->name ?? 'Pelanggan',
            'customer_phone' => Phone::normalize($customer->phone ?? ''),
            'customer_whatsapp' => $customer->whatsapp ? Phone::normalize($customer->whatsapp) : null,
            'unit_id' => $unit->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $duration,
            'package_id' => $package?->id,
            'package_name' => $package?->name,
            'hourly_rate' => $pricingResult['hourly_rate'],
            'total_price' => $pricingResult['total_price'],
            'status' => RentalRequestStatus::PENDING,
            'notes' => $notes,
            'user_id' => $customer->id,
        ]);
    }

    /**
     * Konfirmasi permintaan sewa oleh kasir (sama seperti booking - no.2).
     * Tidak auto-start rental session di sini, mengikuti pola booking (konfirmasi dulu).
     */
    public function confirm(RentalRequest $rentalRequest, User $confirmer): RentalRequest
    {
        if (! $rentalRequest->isPending()) {
            throw new \InvalidArgumentException('Hanya permintaan sewa berstatus PENDING yang bisa dikonfirmasi.');
        }

        $this->ensureUnitAvailable($rentalRequest->unit, $rentalRequest->start_time, $rentalRequest->end_time, $rentalRequest);

        $rentalRequest->update([
            'status' => RentalRequestStatus::CONFIRMED,
            'confirmed_by' => $confirmer->id,
            'confirmed_at' => now(),
        ]);

        return $rentalRequest->fresh(['unit', 'package', 'confirmer', 'rentalSession']);
    }

    public function cancel(RentalRequest $rentalRequest): RentalRequest
    {
        if ($rentalRequest->isCompleted()) {
            throw new \InvalidArgumentException('Permintaan sewa yang sudah selesai tidak bisa dibatalkan.');
        }

        $rentalRequest->update([
            'status' => RentalRequestStatus::CANCELLED,
        ]);

        return $rentalRequest->fresh(['unit']);
    }

    public function complete(RentalRequest $rentalRequest, array $payload = []): RentalRequest
    {
        if (! $rentalRequest->isConfirmed()) {
            throw new \InvalidArgumentException('Hanya permintaan sewa berstatus CONFIRMED yang bisa diselesaikan.');
        }

        $rentalRequest->update([
            'status' => RentalRequestStatus::COMPLETED,
        ]);

        return $rentalRequest->fresh(['unit', 'rentalSession']);
    }

    private function ensureUnitAvailable(Unit $unit, Carbon $startTime, Carbon $endTime, ?RentalRequest $ignore = null): void
    {
        $conflicts = $this->detectConflicts($unit, $startTime, $endTime, $ignore);

        if ($conflicts->isNotEmpty()) {
            throw AvailabilityConflict::forUnit($unit, $conflicts);
        }
    }

    private function detectConflicts(Unit $unit, Carbon $startTime, Carbon $endTime, ?RentalRequest $ignore): Collection
    {
        $conflicts = collect();

        // Conflicts with bookings (confirmed)
        $bookingQuery = $unit->confirmedBookings()
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($bookingQuery->exists()) {
            $conflicts->push('Terdapat booking terkonfirmasi yang bentrok dengan jadwal ini.');
        }

        // Conflicts with running rental sessions
        $unit->loadMissing('runningSession');
        if ($unit->runningSession) {
            $session = $unit->runningSession;
            $sessionEnd = $session->estimatedEndTime() ?? now()->addMinutes(5);
            if ($startTime->lt($sessionEnd)) {
                $conflicts->push('Unit sedang digunakan dalam sesi rental yang berjalan.');
            }
        }

        // Conflicts with other rental requests (PENDING/CONFIRMED)
        $rrQuery = $unit->rentalRequests()
            ->whereIn('status', [RentalRequestStatus::PENDING, RentalRequestStatus::CONFIRMED])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($ignore) {
            $rrQuery->where('id', '!=', $ignore->id);
        }

        if ($rrQuery->exists()) {
            $conflicts->push('Terdapat permintaan sewa lain (PENDING/CONFIRMED) yang bentrok dengan jadwal ini.');
        }

        return $conflicts;
    }

    private function generateCode(): string
    {
        do {
            $code = 'RR-'.Str::upper(Str::padLeft((string) random_int(1, 9999), 4, '0'));
        } while (RentalRequest::where('rental_request_code', $code)->exists());

        return $code;
    }
}
