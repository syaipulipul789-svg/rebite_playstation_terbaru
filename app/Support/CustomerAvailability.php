<?php

namespace App\Support;

use App\Enums\UnitStatus;
use App\Models\Booking;
use App\Models\Unit;

/**
 * Bentuk data unit dan booking yang dilihat pelanggan.
 *
 * Dipakai bersama oleh landing page publik dan dashboard pelanggan supaya
 * kartu unit serta kartu status booking tampil identik di kedua tempat.
 */
final class CustomerAvailability
{
    /**
     * Unit yang boleh dipesan (READY / BUSY) beserta harga dan kapan slot
     * berikutnya bebas. Untuk unit BUSY, `session_end_timestamp` dipakai
     * sebagai jam mulai default di form booking.
     *
     * @return array<string, mixed>
     */
    public static function bookingUnit(Unit $unit): array
    {
        $session = $unit->runningSession;
        $reservation = $unit->confirmedBookings->first();

        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'type' => $unit->type,
            'status' => $unit->status->value,
            'hourly_rate' => (float) $unit->hourly_rate,
            'is_free' => $unit->isFree(),
            'is_reserved' => $reservation !== null,
            'reservation_label' => $reservation !== null
                ? $reservation->start_time->format('d M Y H:i').' → '.$reservation->end_time->format('d M Y H:i')
                : null,
            'reservation_start_timestamp' => $reservation?->start_time->getTimestampMs(),
            'reservation_end_timestamp' => $reservation?->end_time->getTimestampMs(),
            'session_end_timestamp' => $session !== null
                ? $session->start_time->copy()->addMinutes($session->planned_minutes)->getTimestampMs()
                : null,
            'session_remaining_minutes' => $session !== null ? max(0, (int) ceil($session->remainingSeconds() / 60)) : null,
            'session_package_name' => $session?->package_name ?? 'Open Play',
        ];
    }

    /**
     * @param  iterable<Unit>  $units
     * @return list<array<string, mixed>>
     */
    public static function bookingUnits(iterable $units): array
    {
        $result = [];

        foreach ($units as $unit) {
            if ($unit->status !== UnitStatus::MAINTENANCE) {
                $result[] = self::bookingUnit($unit);
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function booking(Booking $booking): array
    {
        $status = $booking->customerStatus();
        $session = $booking->rentalSession;

        return [
            'code' => $booking->bookingCode(),
            'console_name' => $booking->console?->name,
            'console_code' => $booking->console?->code,
            'start_time_label' => $booking->start_time->format('d M Y H:i'),
            'end_time_label' => $booking->end_time->format('d M Y H:i'),
            'total_price_label' => Money::format($booking->total_price),
            'status' => $status,
            'remaining_seconds' => $status['is_occupied'] ? $session->remainingSeconds() : null,
        ];
    }
}
