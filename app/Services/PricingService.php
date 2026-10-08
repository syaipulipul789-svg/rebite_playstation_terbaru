<?php

namespace App\Services;

use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\RatePackage;
use App\Models\Unit;
use App\Support\Money;

final class PricingService
{
    /**
     * Perkiraan harga untuk satu permintaan sewa (rental request).
     *
     * Bila paket dipilih, harga memakai harga tetap paket. Untuk sisa durasi
     * di luar durasi paket ditagih per jam dari tarif unit. Tanpa paket,
     * seluruh durasi dihitung per jam terbanyak dari hourly_rate unit.
     *
     * @return array{hourly_rate: float, total_price: float}
     */
    public function calculate(Unit $unit, ?RatePackage $package, int $minutes): array
    {
        if ($package === null) {
            $total = $this->durationFee((float) $unit->hourly_rate, $minutes);

            return [
                'hourly_rate' => (float) $unit->hourly_rate,
                'total_price' => $total,
            ];
        }

        $packageHours = max(1 / 60, $package->duration_minutes / 60);
        $overMinutes = max(0, $minutes - $package->duration_minutes);
        $total = $package->price + $this->durationFee((float) $unit->hourly_rate, $overMinutes);

        return [
            'hourly_rate' => round((float) $package->price / $packageHours, 2),
            'total_price' => Money::round($total),
        ];
    }

    /**
     * Harga dasar durasi (menit) untuk sebuah unit.
     *
     * Tarif dihitung per jam UTUH (dibulatkan ke atas) memakai hourly_rate
     * unit. Bila durasi tidak sampai 1 jam penuh, minimal 1 jam ditagih —
     * standar lahim rental konsol.
     *
     * Penting: sesi "Open Play" TIDAK memakai parameter $isComplimentary.
     * Open Play hanya berarti tidak memakai paket harga tetap, sehingga tetap
     * ditagih per jam dari hourly_rate unit. $isComplimentary hanya untuk unit
     * yang tarifnya benar-benar 0 (mis. unit complimentary / gratis).
     */
    public function durationFee(float $hourlyRate, int $minutes, bool $isComplimentary = false): float
    {
        if ($isComplimentary || $hourlyRate <= 0) {
            return 0.0;
        }

        if ($minutes <= 0) {
            return 0.0;
        }

        $billableHours = max(1, (int) ceil($minutes / 60));

        return round($billableHours * $hourlyRate, 2);
    }

    /**
     * Biaya untuk menambah durasi (extra time) di tengah sesi berjalan.
     *
     * Berlaku sama untuk sesi paket maupun Open Play: tambahan durasi selalu
     * dihitung dari tarif per jam unit.
     */
    public function extraTimeFee(float $hourlyRate, int $extraMinutes, bool $isComplimentary = false): float
    {
        return $this->durationFee($hourlyRate, $extraMinutes, $isComplimentary);
    }

    /**
     * Harga sebuah produk F&B.
     */
    public function productPrice(Product $product): float
    {
        return (float) $product->price;
    }

    public function isExtraTime(Product $product): bool
    {
        return $product->category === ProductCategory::EXTRA;
    }
}
