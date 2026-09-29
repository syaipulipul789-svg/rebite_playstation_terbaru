<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[DataProvider('parseProvider')]
    public function test_parse_menormalisasi_format_rupiah(mixed $input, float $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public static function parseProvider(): array
    {
        return [
            'format ribuan' => ['200.000', 200000.0],
            'tanpa pemisah' => ['200000', 200000.0],
            'dengan prefix' => ['Rp 15.000', 15000.0],
            'angka bulat' => [15000, 15000.0],
            'kosong jadi nol' => ['', 0.0],
            'nol' => ['0', 0.0],
        ];
    }

    #[DataProvider('formatProvider')]
    public function test_format_rupiah(mixed $value, bool $withPrefix, string $expected): void
    {
        $this->assertSame($expected, Money::format($value, $withPrefix));
    }

    public static function formatProvider(): array
    {
        return [
            'bulat dengan prefix' => [15000, true, 'Rp 15.000'],
            'bulat tanpa prefix' => [15000, false, '15.000'],
            'desimal' => [15000.5, true, 'Rp 15.000,50'],
            'nol' => [0, true, 'Rp 0'],
        ];
    }

    public function test_format_signed_menandai_arah_selisih(): void
    {
        $this->assertSame('+ Rp 5.000', Money::formatSigned(5000));
        $this->assertSame('- Rp 5.000', Money::formatSigned(-5000));
        $this->assertSame('Rp 0', Money::formatSigned(0));
    }
}
