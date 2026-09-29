<?php

namespace Tests\Unit;

use App\Services\PricingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pricing = new PricingService;
    }

    #[DataProvider('durationFeeProvider')]
    public function test_duration_fee_menghitung_jam_utuh(float $rate, int $minutes, bool $complimentary, float $expected): void
    {
        $this->assertSame($expected, $this->pricing->durationFee($rate, $minutes, $complimentary));
    }

    public static function durationFeeProvider(): array
    {
        return [
            'tepat satu jam' => [12000, 60, false, 12000.0],
            'kurang dari satu jam tetap satu jam' => [12000, 45, false, 12000.0],
            'satu jam lima belas menit jadi dua jam' => [12000, 75, false, 24000.0],
            'dua jam setengah' => [12000, 150, false, 36000.0],
            'sesi complimentary gratis' => [12000, 90, true, 0.0],
            'unit gratis tanpa tarif' => [0, 60, false, 0.0],
            'durasi nol' => [12000, 0, false, 0.0],
        ];
    }

    public function test_open_play_tetap_ditagih_per_jam(): void
    {
        // Open Play memakai paket yang sama dengan tarif per jam unit.
        $this->assertSame(24000.0, $this->pricing->durationFee(12000, 90));
    }

    public function test_extra_time_using_tarif_yang_sama(): void
    {
        $this->assertSame(12000.0, $this->pricing->extraTimeFee(12000, 60));
        $this->assertSame(24000.0, $this->pricing->extraTimeFee(12000, 90));
    }
}
