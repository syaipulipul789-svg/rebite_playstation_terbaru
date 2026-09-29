<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\RentalSession;
use App\Models\SessionItem;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Membuat riwayat shift & sesi sewa 14 hari terakhir supaya dashboard analitik,
 * grafik pendapatan, dan tabel audit log Owner langsung terisi.
 */
class DemoHistorySeeder extends Seeder
{
    private const DAYS = 14;

    public function run(): void
    {
        $cashiers = User::query()->where('role', 'KASIR')->get();

        if ($cashiers->isEmpty()) {
            $this->command?->warn('Tidak ada kasir. Jalankan UserSeeder terlebih dahulu.');

            return;
        }

        $units = Unit::query()->whereIn('status', [UnitStatus::READY, UnitStatus::BUSY])->get();
        $products = Product::query()->active()->where('stock', '>', 0)->get();

        $today = CarbonImmutable::today();

        for ($dayOffset = self::DAYS - 1; $dayOffset >= 0; $dayOffset--) {
            $date = $today->subDays($dayOffset);

            // 2 shift per hari, masing-masing oleh 2 kasir bergantian
            foreach ($cashiers->take(2) as $index => $cashier) {
                $startAt = $date->setTime($index === 0 ? 10 : 17, 0);

                // Jangan buat shift untuk hari ini yang shift kedua-nya
                // belum waktunya (bakal mengganggu demo shift OPEN manual).
                if ($startAt->isFuture()) {
                    continue;
                }

                $this->createShift($cashier, $startAt, $units, $products, $index);
            }
        }

        // Pastikan semua unit kembali READY setelah seeding.
        Unit::query()->where('status', UnitStatus::BUSY)->update(['status' => UnitStatus::READY]);

        $this->command?->info('Demo history '.self::DAYS.' hari selesai.');
    }

    private function createShift(
        User $cashier,
        CarbonImmutable $startAt,
        $units,
        $products,
        int $index,
    ): void {
        $startingCash = [200000, 250000][$index] ?? 200000;

        $shift = Shift::create([
            'user_id' => $cashier->id,
            'start_time' => $startAt,
            'starting_cash' => $startingCash,
            'status' => ShiftStatus::CLOSED,
        ]);

        $cashRevenue = 0.0;
        $qrisRevenue = 0.0;
        $sessionCount = random_int(4, 8);

        for ($i = 0; $i < $sessionCount; $i++) {
            $unit = $units->random();
            $sessionStart = $startAt->addHours(random_int(0, 6))->addMinutes(random_int(0, 50));

            if ($sessionStart->greaterThanOrEqualTo($startAt->addHours(9))) {
                continue;
            }

            $plannedMinutes = [60, 60, 120, 180, 90, 120][random_int(0, 5)];
            $duration = $plannedMinutes + random_int(0, 40);
            $payment = random_int(0, 9) < 6 ? PaymentMethod::CASH : PaymentMethod::QRIS;
            $rentalFee = $unit->isFree() ? 0 : (float) $unit->hourly_rate * (int) ceil($plannedMinutes / 60);

            $session = RentalSession::create([
                'unit_id' => $unit->id,
                'shift_id' => $shift->id,
                'user_id' => $cashier->id,
                'start_time' => $sessionStart,
                'end_time' => $sessionStart->addMinutes($duration),
                'duration_minutes' => $duration,
                'planned_minutes' => $plannedMinutes,
                'is_free_play' => $unit->isFree(),
                'package_name' => $unit->isFree() ? 'Open Play' : "Paket {$plannedMinutes} Menit",
                'rental_fee' => $rentalFee,
                'status' => RentalSessionStatus::COMPLETED,
                'payment_method' => $payment,
            ]);

            $itemsTotal = 0.0;

            if ($products->isNotEmpty() && random_int(0, 100) < 70) {
                foreach ($products->random(min(2, $products->count())) as $product) {
                    $qty = random_int(1, 3);
                    $subtotal = Money::round((float) $product->price * $qty);

                    SessionItem::create([
                        'rental_session_id' => $session->id,
                        'product_id' => $product->id,
                        'qty' => $qty,
                        'price' => $product->price,
                        'subtotal' => $subtotal,
                    ]);

                    $itemsTotal += $subtotal;
                }
            }

            $sessionTotal = $rentalFee + $itemsTotal;

            if ($payment === PaymentMethod::CASH) {
                $cashRevenue += $sessionTotal;
            } else {
                $qrisRevenue += $sessionTotal;
            }
        }

        $expected = $startingCash + $cashRevenue;

        // 1 dari 3 shift punya selisih kas (skenario Founder vs Over).
        $hasDiscrepancy = random_int(1, 3) === 1;
        $discrepancy = $hasDiscrepancy
            ? [-(random_int(1, 4) * 5000), random_int(1, 3) * 5000][random_int(0, 1)]
            : 0.0;

        $note = $discrepancy === 0.0
            ? null
            : ($discrepancy < 0
                ? 'Selisih kurang: kemungkinan salah hitung kembalian untuk pelanggan yang bayar QRIS tapi nota dicatat tunai.'
                : 'Selisih lebih: ada uang 5.000 ditemukan tertinggal di laci dari shift sebelumnya.');

        $shift->forceFill([
            'end_time' => $startAt->addHours(9),
            'closed_at' => $startAt->addHours(9),
            'system_cash_revenue' => Money::round($cashRevenue),
            'system_qris_revenue' => Money::round($qrisRevenue),
            'actual_physical_cash' => Money::round($expected + $discrepancy),
            'discrepancy' => Money::round($discrepancy),
            'note' => $note,
        ])->save();

        AuditLog::query()->create([
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
            'event' => AuditLog::EVENT_SHIFT_STARTED,
            'description' => 'Shift dibuka dengan modal awal '.Money::format($startingCash),
            'context' => ['starting_cash' => $startingCash],
            'created_at' => $startAt,
            'updated_at' => $startAt,
        ]);

        AuditLog::query()->create([
            'user_id' => $cashier->id,
            'shift_id' => $shift->id,
            'event' => AuditLog::EVENT_SHIFT_CLOSED,
            'description' => $discrepancy === 0.0
                ? sprintf('Rekap shift kas sesuai. Ekspektasi %s = fisik %s.', Money::format($expected), Money::format($expected))
                : sprintf(
                    'Selisih kas %s %s dari ekspektasi %s. Alasan: %s',
                    $discrepancy > 0 ? 'lebih' : 'kurang',
                    Money::format(abs($discrepancy)),
                    Money::format($expected),
                    $note,
                ),
            'context' => [
                'expected_cash' => $expected,
                'actual_physical_cash' => Money::round($expected + $discrepancy),
                'discrepancy' => Money::round($discrepancy),
                'note' => $note,
            ],
            'created_at' => $startAt->addHours(9),
            'updated_at' => $startAt->addHours(9),
        ]);
    }
}
