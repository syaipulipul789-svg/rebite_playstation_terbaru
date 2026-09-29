<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\RentalSessionStatus;
use App\Enums\ShiftStatus;
use App\Enums\UnitStatus;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\RatePackage;
use App\Models\RentalSession;
use App\Models\SessionItem;
use App\Models\Shift;
use App\Models\Unit;
use App\Models\User;
use App\Support\Duration;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class RentalService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AuditLogger $audit,
        private readonly ShiftService $shifts,
    ) {}

    /**
     * Mulai sewa pada unit READY.
     *
     * Sesi dikunci ke shift OPEN milik kasir yang sedang login supaya seluruh
     * pendapatan sesi masuk ke rekap shift yang benar.
     */
    public function start(
        Unit $unit,
        User $cashier,
        ?RatePackage $package,
        int $openPlayMinutes,
        bool $isFreePlay,
    ): RentalSession {
        return DB::transaction(function () use ($unit, $cashier, $package, $openPlayMinutes, $isFreePlay) {
            $lockedUnit = Unit::query()->lockForUpdate()->findOrFail($unit->id);

            if ($lockedUnit->status !== UnitStatus::READY) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit ini tidak dalam status Ready.',
                ]);
            }

            if ($lockedUnit->runningSession()->exists()) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit sedang menjalankan sesi sebelumnya.',
                ]);
            }

            $shift = $this->resolveOpenShift($cashier);

            $plannedMinutes = $isFreePlay
                ? max(0, $openPlayMinutes)
                : (int) ($package?->duration_minutes ?? 0);

            $rentalFee = $isFreePlay
                ? $this->pricing->durationFee((float) $lockedUnit->hourly_rate, $plannedMinutes)
                : (float) ($package?->price ?? 0);

            if ($plannedMinutes <= 0) {
                throw ValidationException::withMessages([
                    'rate_package_id' => 'Pilih paket jam atau tentukan durasi open play.',
                ]);
            }

            $session = RentalSession::create([
                'unit_id' => $lockedUnit->id,
                'shift_id' => $shift?->id,
                'user_id' => $cashier->id,
                'start_time' => now(),
                'planned_minutes' => $plannedMinutes,
                'duration_minutes' => 0,
                'is_free_play' => $isFreePlay,
                'package_name' => $isFreePlay ? 'Open Play' : ($package?->name ?? 'Paket Jam'),
                'rental_fee' => Money::round($rentalFee),
                'status' => RentalSessionStatus::RUNNING,
            ]);

            $lockedUnit->forceFill(['status' => UnitStatus::BUSY])->save();

            $this->audit->record(
                user: $cashier,
                shift: $shift,
                event: AuditLog::EVENT_SESSION_STARTED,
                description: sprintf(
                    '%s (%s) mulai disewa: %s / %s',
                    $lockedUnit->name,
                    $lockedUnit->code,
                    $session->package_name,
                    Duration::humanize($plannedMinutes),
                ),
                context: [
                    'unit' => $lockedUnit->code,
                    'planned_minutes' => $plannedMinutes,
                    'rental_fee' => (float) $session->rental_fee,
                ],
            );

            return $session->load('unit');
        });
    }

    /**
     * Tambah durasi (extra time) pada sesi yang sedang berjalan.
     */
    public function extend(RentalSession $session, int $extraMinutes): RentalSession
    {
        if ($extraMinutes <= 0) {
            throw ValidationException::withMessages([
                'extra_minutes' => 'Durasi tambahan minimal 1 menit.',
            ]);
        }

        return DB::transaction(function () use ($session, $extraMinutes) {
            $locked = RentalSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isRunning()) {
                throw new RuntimeException('Sesi sudah selesai, tidak bisa menambah durasi.');
            }

            $charge = $this->pricing->extraTimeFee(
                (float) $locked->unit->hourly_rate,
                $extraMinutes,
            );

            $locked->forceFill([
                'planned_minutes' => $locked->planned_minutes + $extraMinutes,
                'extra_minutes' => $locked->extra_minutes + $extraMinutes,
                'extra_charges_total' => $locked->extra_charges_total + (int) $charge,
                'rental_fee' => Money::round((float) $locked->rental_fee + $charge),
            ])->save();

            $this->audit->record(
                user: $locked->user,
                shift: $locked->shift,
                event: AuditLog::EVENT_SESSION_EXTENDED,
                description: sprintf(
                    'Durasi %s ditambah %s (biaya +%s)',
                    $locked->unit->code,
                    Duration::humanize($extraMinutes),
                    Money::format($charge),
                ),
                context: ['extra_minutes' => $extraMinutes, 'charge' => $charge],
            );

            return $locked->fresh('unit');
        });
    }

    /**
     * Tambahkan pesanan F&B ke sesi berjalan. Stok produk dikunci dan berkurang.
     */
    public function addItem(RentalSession $session, int $productId, int $qty): SessionItem
    {
        if ($qty <= 0) {
            throw ValidationException::withMessages(['qty' => 'Jumlah minimal 1.']);
        }

        return DB::transaction(function () use ($session, $productId, $qty) {
            $lockedSession = RentalSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $lockedSession->isRunning()) {
                throw new RuntimeException('Sesi sudah selesai, tidak bisa menambah pesanan.');
            }

            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if (! $product->is_active) {
                throw ValidationException::withMessages(['product_id' => 'Produk sudah nonaktif.']);
            }

            if ($product->stock < $qty) {
                throw ValidationException::withMessages([
                    'product_id' => "Stok {$product->name} tidak cukup (tersisa {$product->stock}).",
                ]);
            }

            $subtotal = Money::round((float) $product->price * $qty);

            $item = SessionItem::create([
                'rental_session_id' => $lockedSession->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'price' => $product->price,
                'subtotal' => $subtotal,
            ]);

            $product->decrement('stock', $qty);

            return $item->load('product');
        });
    }

    /**
     * Selesaikan sewa: hitung durasi aktual, tetapkan metode pembayaran,
     * kunci shift rekap, dan bebaskan unit kembali ke READY.
     */
    public function complete(RentalSession $session, PaymentMethod $paymentMethod, ?string $note = null): RentalSession
    {
        return DB::transaction(function () use ($session, $paymentMethod, $note) {
            $locked = RentalSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isRunning()) {
                throw new RuntimeException('Sesi ini sudah diproses.');
            }

            $unit = Unit::query()->lockForUpdate()->findOrFail($locked->unit_id);

            if ($locked->shift === null || $locked->shift->status !== ShiftStatus::OPEN) {
                throw new RuntimeException('Shift kasir sudah tertutup, sesi tidak bisa diselesaikan.');
            }

            $endedAt = now();
            $elapsedMinutes = (int) $locked->start_time->diffInMinutes($endedAt);

            $locked->forceFill([
                'end_time' => $endedAt,
                'duration_minutes' => max($elapsedMinutes, $locked->planned_minutes),
                'status' => RentalSessionStatus::COMPLETED,
                'payment_method' => $paymentMethod,
                'note' => $note,
            ])->save();

            $unit->forceFill(['status' => UnitStatus::READY])->save();

            // Rekap shift harus ikut mutakhir sekarang, bukan hanya saat
            // halaman rekonsiliasi dibuka / shift ditutup, supaya angka di
            // POS dan dashboard tidak basi selama shift masih berjalan.
            $this->shifts->recalculateRevenue($locked->shift);

            $grandTotal = $locked->grandTotal();

            $this->audit->record(
                user: $locked->user,
                shift: $locked->shift,
                event: AuditLog::EVENT_SESSION_COMPLETED,
                description: sprintf(
                    '%s selesai. %s, total %s (%s)',
                    $unit->name,
                    Duration::humanize($locked->duration_minutes),
                    Money::format($grandTotal),
                    $paymentMethod->label(),
                ),
                context: [
                    'unit' => $unit->code,
                    'rental_fee' => (float) $locked->rental_fee,
                    'items_total' => $locked->itemsTotal(),
                    'grand_total' => $grandTotal,
                    'payment_method' => $paymentMethod->value,
                ],
            );

            return $locked->fresh(['unit', 'items.product', 'shift']);
        });
    }

    /**
     * Batalkan sesi: unit kembali READY, tidak ada pendapatan tercatat.
     */
    public function cancel(RentalSession $session, ?string $reason = null): RentalSession
    {
        return DB::transaction(function () use ($session, $reason) {
            $locked = RentalSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $locked->isRunning()) {
                throw new RuntimeException('Sesi ini sudah diproses.');
            }

            // Kembalikan stok produk yang sempat dipesan.
            foreach ($locked->items as $item) {
                $item->product?->increment('stock', $item->qty);
                $item->delete();
            }

            $locked->forceFill([
                'end_time' => now(),
                'status' => RentalSessionStatus::CANCELLED,
                'rental_fee' => 0,
                'note' => $reason,
            ])->save();

            Unit::query()->whereKey($locked->unit_id)->update(['status' => UnitStatus::READY]);

            $this->audit->record(
                user: $locked->user,
                shift: $locked->shift,
                event: AuditLog::EVENT_SESSION_CANCELLED,
                description: sprintf(
                    'Sewa %s dibatalkan. Alasan: %s',
                    $locked->unit->code,
                    $reason ?? '-',
                ),
                context: ['reason' => $reason],
            );

            return $locked->fresh('unit');
        });
    }

    private function resolveOpenShift(User $cashier): ?Shift
    {
        return Shift::query()
            ->where('user_id', $cashier->id)
            ->where('status', ShiftStatus::OPEN)
            ->latest('start_time')
            ->lockForUpdate()
            ->first();
    }
}
