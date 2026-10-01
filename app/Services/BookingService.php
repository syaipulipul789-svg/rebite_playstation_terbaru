<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Models\Booking;
use App\Models\RentalSession;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class BookingService
{
    public function __construct(private readonly RentalService $rental) {}

    /**
     * Buat booking pending untuk satu konsol dengan pengecekan bentrok
     * jadwal (sesi rental berjalan + booking lain yang memegang slot).
     */
    public function create(
        Unit $unit,
        string $customerName,
        string $customerPhone,
        Carbon $start,
        Carbon $end,
        ?string $notes,
        ?User $customer = null,
    ): Booking {
        return DB::transaction(function () use ($unit, $customerName, $customerPhone, $start, $end, $notes, $customer) {
            $locked = Unit::query()->lockForUpdate()->findOrFail($unit->id);

            if (! in_array($locked->status, [UnitStatus::READY, UnitStatus::BUSY], true)) {
                throw ValidationException::withMessages([
                    'console_id' => 'Unit ini sedang tidak dapat dipesan (masih dalam servis).',
                ]);
            }

            $this->assertNoConflict($locked, $start, $end);

            $hours = max(1, $start->diffInHours($end));
            $totalPrice = Money::round((float) $locked->hourly_rate * $hours);

            return Booking::create([
                'console_id' => $locked->id,
                'user_id' => $customer?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'start_time' => $start,
                'end_time' => $end,
                'status' => BookingStatus::PENDING,
                'total_price' => $totalPrice,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Booking yang dibuat pelanggan dari akunnya sendiri.
     *
     * Nama dan nomor WhatsApp diambil dari akun, bukan dari form, supaya
     * kasir selalu punya kontak yang bisa ditelepon dan riwayat booking
     * terhubung ke akun yang bisa dibuka lagi dari perangkat lain.
     */
    public function createForCustomer(
        User $customer,
        Unit $unit,
        Carbon $start,
        Carbon $end,
        ?string $notes,
    ): Booking {
        return $this->create(
            unit: $unit,
            customerName: $customer->name,
            customerPhone: $customer->phone,
            start: $start,
            end: $end,
            notes: $notes,
            customer: $customer,
        );
    }

    /**
     * Setujui booking pending. Konflik dicek ulang karena bisa saja sudah
     * berdiri sesi rental atau booking confirmed lain yang bentrok.
     *
     *Begitu dikonfirmasi, booking yang slotnya sedang berjalan langsung
     * di-promote menjadi sesi rental: unit jadi BUSY ("Terisi") dan monitor
     * menampilkan sisa waktu. Booking untuk slot masa depan tetap hanya
     * terkunci — promote-nya dilakukan saat jam mulai lewat command
     * `bookings:promote`.
     */
    public function confirm(Booking $booking, User $cashier): Booking
    {
        return DB::transaction(function () use ($booking, $cashier) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->isPending()) {
                throw new RuntimeException('Booking ini sudah diproses sebelumnya.');
            }

            $this->assertNoConflict($locked->console, $locked->start_time, $locked->end_time, ignoreBookingId: $locked->id);

            $locked->forceFill([
                'status' => BookingStatus::CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => $cashier->id,
            ])->save();

            $this->promoteToSession($locked);

            return $locked->fresh('console');
        });
    }

    public function cancel(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($locked->status, [BookingStatus::PENDING, BookingStatus::CONFIRMED], true)) {
                throw new RuntimeException('Booking ini sudah selesai atau dibatalkan.');
            }

            // Jangan lepas booking sementara pelanggan masih(main) — unit
            // akan kembali READY tapi sesinya masih jalan.
            if ($locked->hasRunningSession()) {
                throw new RuntimeException('Sesi rental pelanggan masih berjalan. Selesaikan sesi dulu lewat menu Kasir.');
            }

            $locked->forceFill(['status' => BookingStatus::CANCELLED])->save();

            return $locked->fresh('console');
        });
    }

    /**
     * Tandai booking selesai.
     *
     * Kalau booking ini sudah menghasilkan sesi rental yang berjalan,
     * sesi ikut diselesaikan supaya unit benar-benar dibebaskan kembali
     * ke READY dan pendapatan masuk rekap shift kasir.
     */
    public function complete(Booking $booking, ?PaymentMethod $paymentMethod = null, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $paymentMethod) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->isConfirmed()) {
                throw new RuntimeException('Hanya booking yang dikonfirmasi yang bisa ditandai selesai.');
            }

            $locked->forceFill(['status' => BookingStatus::COMPLETED])->save();

            $session = $locked->rentalSession;

            if ($session !== null && $session->isRunning()) {
                $this->rental->complete(
                    $session,
                    $paymentMethod ?? PaymentMethod::CASH,
                    'Selesai via booking '.$locked->bookingCode(),
                );
            }

            return $locked->fresh('console');
        });
    }

    /**
     * Promote booking CONFIRMED menjadi sesi rental berjalan sehingga unit
     * berubah jadi BUSY ("Terisi") dan monitor menampilkan sisa waktu.
     *
     * Sesi dibuat lewat `RentalService::start()` supaya resolutionshift,
     * pencatatan audit, dan penghitungan tarif tetap satu sumber kebenaran.
     * Atribusinya mengikuti shift: kasir yang mengonfirmasi booking, atau —
     * untuk booking yang di-promote otomatis oleh scheduler — kasir yang
     * sedang relieve shift OPEN.
     *
     * Hanya berlaku bila slot booking SEDANG berjalan sekarang
     * (`start_time <= now < end_time`) dan unit masih kosong. Booking di
     * masa depan sengaja dilewati agar unit tidak terpakai sebelum jadwalnya.
     *
     * Mengembalikan sesi yang dibuat, atau null bila belum waktunya, unit
     * sudah dipakai, atau belum ada shift OPEN yang bisa menerima
     * pendapatan.
     */
    public function promoteToSession(Booking $booking): ?RentalSession
    {
        $console = $booking->console;

        if ($console === null || ! $this->isSlotActive($booking)) {
            return null;
        }

        if ($console->status !== UnitStatus::READY || $console->runningSession()->exists()) {
            return null;
        }

        $cashier = $booking->confirmedBy ?? $this->resolveOpenShiftOwner();

        if ($cashier === null) {
            Log::warning('Booking confirmed tidak bisa dipromote: belum ada shift OPEN untuk sesi.', [
                'booking_id' => $booking->id,
                'booking_code' => $booking->bookingCode(),
            ]);

            return null;
        }

        try {
            $session = $this->rental->start(
                unit: $console,
                cashier: $cashier,
                package: null,
                openPlayMinutes: $booking->durationMinutes(),
                isFreePlay: true,
            );
        } catch (ValidationException) {
            // Unit keburu dipakai sesi lain di antara pengecekan dan promote.
            return null;
        }

        $booking->forceFill([
            'rental_session_id' => $session->id,
            'total_price' => $session->rental_fee,
        ])->save();

        return $session->load('unit');
    }

    /**
     * Promote booking CONFIRMED yang jam mulainya sudah lewat supaya berubah
     * jadi "Terisi" tanpa kasir perlu menekan apa pun. Dipanggil command
     * terjadwal.
     *
     * Konflik yang wajar (unit masih dipakai, belum ada shift OPEN) sudah
     * ditangani `promoteToSession()` dengan mengembalikan null, jadi error
     * lain sengaja dibiarkan naik agar tidak hilang diam-diam.
     *
     * @return int jumlah booking yang berhasil dipromote
     */
    public function promoteDueBookings(): int
    {
        $due = Booking::query()
            ->where('status', BookingStatus::CONFIRMED)
            ->whereNull('rental_session_id')
            ->where('start_time', '<=', now())
            ->where('end_time', '>', now())
            ->orderBy('start_time')
            ->get();

        $promoted = 0;

        foreach ($due as $booking) {
            $session = DB::transaction(fn () => $this->promoteToSession($booking));

            if ($session !== null) {
                $promoted++;
            }
        }

        return $promoted;
    }

    private function isSlotActive(Booking $booking): bool
    {
        $now = now();

        return $booking->start_time->lte($now) && $booking->end_time->gt($now);
    }

    /**
     * Kasir yang sedang relieve shift OPEN, dipakai sebagai pemilik sesi saat
     * booking dipromote otomatis oleh scheduler.
     */
    private function resolveOpenShiftOwner(): ?User
    {
        $userId = Shift::query()
            ->where('status', ShiftStatus::OPEN)
            ->latest('start_time')
            ->value('user_id');

        return $userId !== null ? User::find($userId) : null;
    }

    /**
     * Pastikan rentang [start, end) tidak bertabrakan dengan sesi rental yang
     * sedang berjalan maupun booking lain yang sedang memegang slot
     * (PENDING maupun CONFIRMED) pada konsol yang sama.
     */
    private function assertNoConflict(Unit $unit, Carbon $start, Carbon $end, ?int $ignoreBookingId = null): void
    {
        $running = $unit->runningSession;

        if ($running !== null) {
            $sessionEnd = $running->start_time->copy()->addMinutes($running->planned_minutes);

            if ($start->lt($sessionEnd) && $end->gt($running->start_time)) {
                throw ValidationException::withMessages([
                    'start_time' => sprintf(
                        'Jadwal bentrok dengan sesi rental berjalan — unit dipakai sampai %s.',
                        $sessionEnd->format('d M Y H:i'),
                    ),
                ]);
            }
        }

        $overlap = Booking::query()
            ->where('console_id', $unit->id)
            ->when($ignoreBookingId !== null, fn ($query) => $query->where('id', '!=', $ignoreBookingId))
            ->holdingSlot()
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_time' => 'Jadwal bentrok dengan booking lain yang sedang memakai unit ini di jam tersebut.',
            ]);
        }
    }
}
