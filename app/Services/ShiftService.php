<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ShiftService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Buka shift baru untuk kasir dengan input Modal Awal Kas.
     *
     * Dilindungi unique index parsial di level aplikasi: satu kasir hanya boleh
     * memiliki satu shift OPEN. Pengecekan dibuat dalam transaksi dengan lock
     * baris user agar aman dari double submit / dua tab terbuka.
     */
    public function start(User $user, float $startingCash): Shift
    {
        if ($user->isOwner()) {
            throw new RuntimeException('Owner tidak memerlukan shift operasional.');
        }

        return DB::transaction(function () use ($user, $startingCash) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($locked->shifts()->where('status', ShiftStatus::OPEN)->exists()) {
                throw new RuntimeException('Anda masih memiliki shift yang belum ditutup.');
            }

            $shift = Shift::create([
                'user_id' => $locked->id,
                'start_time' => now(),
                'starting_cash' => Money::round($startingCash),
                'system_cash_revenue' => 0,
                'system_qris_revenue' => 0,
                'status' => ShiftStatus::OPEN,
            ]);

            $this->audit->record(
                user: $locked,
                shift: $shift,
                event: AuditLog::EVENT_SHIFT_STARTED,
                description: 'Shift dibuka dengan modal awal '.Money::format($shift->starting_cash),
                context: ['starting_cash' => (float) $shift->starting_cash],
            );

            return $shift;
        });
    }

    public function activeShiftFor(User $user): ?Shift
    {
        return $user->shifts()
            ->where('status', ShiftStatus::OPEN)
            ->latest('start_time')
            ->first();
    }

    /**
     * Hitung ulang rekap pendapatan sistem dari sesi rental yang sudah
     * COMPLETED plus pesanan yang sudah dibayar, di dalam shift tersebut,
     * dikelompokkan per metode pembayaran.
     *
     * Aturan pemisahan agar tidak ada uang yang terhitung dua kali:
     * - Pendapatan sesi = biaya sewa + item manual + pesanan QR meja yang
     *   menempel ke sesi itu (sudah ikut dilunasi saat checkout).
     * - Pendapatan order mandiri = hanya pesanan yang TIDAK punya
     *   `rental_session_id`, yaitu hasil scan barcode yang dibayar terpisah.
     *
     * Kalau baris kedua tidak dibatasi, setiap pesanan QR meja akan masuk
     * rekap sekali lewat sesi dan sekali lagi lewat order — dan karena
     * keduanya memakai payment method yang sama, angka kas yang diharapkan
     * saat rekonsiliasi jadi lebih besar dari uang sungguhan di laci.
     *
     * Dipanggil setiap kali ada sesi atau pesanan yang diselesaikan agar
     * angka pada halaman rekonsiliasi selalu sinkron dengan database.
     */
    public function recalculateRevenue(Shift $shift): Shift
    {
        $sessionTotals = $shift->rentalSessions()
            ->where('status', RentalSessionStatus::COMPLETED)
            ->whereNotNull('payment_method')
            ->selectRaw(
                'payment_method, SUM(rental_fee'
                .' + COALESCE((SELECT SUM(subtotal) FROM session_items'
                .'   WHERE session_items.rental_session_id = rental_sessions.id), 0)'
                .' + COALESCE((SELECT SUM(total_price) FROM orders'
                .'   WHERE orders.rental_session_id = rental_sessions.id'
                .'   AND orders.status <> \'CANCELLED\'), 0)) as total'
            )
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $orderTotals = $shift->orders()
            ->where('status', OrderStatus::COMPLETED)
            ->whereNull('rental_session_id')
            ->whereNotNull('payment_method')
            ->selectRaw('payment_method, SUM(total_price) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $shift->forceFill([
            'system_cash_revenue' => Money::round(
                (float) ($sessionTotals[PaymentMethod::CASH->value] ?? 0)
                + (float) ($orderTotals[PaymentMethod::CASH->value] ?? 0),
            ),
            'system_qris_revenue' => Money::round(
                (float) ($sessionTotals[PaymentMethod::QRIS->value] ?? 0)
                + (float) ($orderTotals[PaymentMethod::QRIS->value] ?? 0),
            ),
        ])->save();

        return $shift;
    }

    /**
     * Kalkulasi rekonsiliasi sebelum shift ditutup.
     *
     * Total Ekspektasi Kas = Modal Awal + Total Pendapatan Tunai Shift Berjalan.
     * Selisih (Discrepancy)   = Uang Fisik Riil di Laci - Total Ekspektasi Kas.
     */
    public function previewReconciliation(Shift $shift): array
    {
        $shift = $this->recalculateRevenue($shift);

        $expected = $shift->expectedCash();
        $actual = $shift->actual_physical_cash !== null
            ? (float) $shift->actual_physical_cash
            : null;

        $discrepancy = $actual === null ? null : Money::round($actual - $expected);

        return [
            'starting_cash' => (float) $shift->starting_cash,
            'cash_revenue' => (float) $shift->system_cash_revenue,
            'qris_revenue' => (float) $shift->system_qris_revenue,
            'total_revenue' => $shift->totalSystemRevenue(),
            'expected_cash' => $expected,
            'actual_physical_cash' => $actual,
            'discrepancy' => $discrepancy,
            'requires_note' => $discrepancy !== null && $discrepancy !== 0.0,
            'session_count' => $shift->rentalSessions()
                ->where('status', RentalSessionStatus::COMPLETED)
                ->count(),
            // Penghitung aktivitas, bukan baris pendapatan: pesanan QR meja
            // ikut dihitung di sini meski uangnya sudah masuk lewat
            // `sessionTotals`. Kalau dibatasi hanya pesanan mandiri, jumlah
            // pesanan yang terlihat kasir jadi terlalu kecil.
            'order_count' => $shift->orders()
                ->where('status', OrderStatus::COMPLETED)
                ->count(),
        ];
    }

    /**
     * Tutup shift: simpan uang fisik, hitung selisih, kunci shift (status CLOSED),
     * dan kirim rekap ke audit log Owner.
     */
    public function close(Shift $shift, float $actualPhysicalCash, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($shift, $actualPhysicalCash, $note) {
            $locked = Shift::query()->with('user')->lockForUpdate()->findOrFail($shift->id);

            if ($locked->status === ShiftStatus::CLOSED) {
                throw new RuntimeException('Shift ini sudah ditutup sebelumnya.');
            }

            $running = $locked->rentalSessions()
                ->where('status', RentalSessionStatus::RUNNING)
                ->count();

            if ($running > 0) {
                throw new RuntimeException("Masih ada {$running} unit yang sedang berjalan. Selesaikan dulu sebelum menutup shift.");
            }

            $locked = $this->recalculateRevenue($locked);

            $expected = $locked->expectedCash();
            $discrepancy = Money::round($actualPhysicalCash - $expected);

            $note = filled($note) ? trim($note) : null;

            if ($discrepancy !== 0.0 && $note === null) {
                throw new RuntimeException('Selisih kas tidak nol. Wajib menyertakan catatan keterangan selisih.');
            }

            $locked->forceFill([
                'end_time' => $locked->end_time ?? now(),
                'closed_at' => now(),
                'actual_physical_cash' => Money::round($actualPhysicalCash),
                'discrepancy' => $discrepancy,
                'note' => $discrepancy !== 0.0 ? $note : null,
                'status' => ShiftStatus::CLOSED,
            ])->save();

            $this->audit->record(
                user: $locked->user,
                shift: $locked,
                event: AuditLog::EVENT_SHIFT_CLOSED,
                description: $this->buildReconciliationDescription($locked),
                context: [
                    'starting_cash' => (float) $locked->starting_cash,
                    'cash_revenue' => (float) $locked->system_cash_revenue,
                    'qris_revenue' => (float) $locked->system_qris_revenue,
                    'expected_cash' => $expected,
                    'actual_physical_cash' => (float) $locked->actual_physical_cash,
                    'discrepancy' => $discrepancy,
                    'note' => $locked->note,
                ],
            );

            return $locked;
        });
    }

    private function buildReconciliationDescription(Shift $shift): string
    {
        $discrepancy = (float) $shift->discrepancy;

        if ($discrepancy === 0.0) {
            return sprintf(
                'Rekap shift kas sesuai. Ekspektasi %s = fisik %s.',
                Money::format($shift->expectedCash()),
                Money::format($shift->actual_physical_cash),
            );
        }

        $direction = $discrepancy > 0 ? 'lebih' : 'kurang';

        return sprintf(
            'Selisih kas %s %s dari ekspektasi %s. Alasan: %s',
            $direction,
            Money::format(abs($discrepancy)),
            Money::format($shift->expectedCash()),
            $shift->note ?? '-',
        );
    }
}
