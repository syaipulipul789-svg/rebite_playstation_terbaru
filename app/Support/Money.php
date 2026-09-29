<?php

namespace App\Support;

final class Money
{
    public static function round(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }

    /**
     * Format Rupiah: 15000 -> "Rp 15.000"
     */
    public static function format(float|int|string|null $value, bool $withPrefix = true): string
    {
        $amount = (float) $value;

        if (abs($amount - round($amount)) < 0.005) {
            $formatted = number_format($amount, 0, ',', '.');
        } else {
            $formatted = number_format($amount, 2, ',', '.');
        }

        return $withPrefix ? 'Rp '.$formatted : $formatted;
    }

    /**
     * Selisih kas dengan tanda eksplisit (+ / - / 0) untuk laporan audit.
     */
    public static function formatSigned(float|int|string|null $value): string
    {
        $amount = (float) $value;

        if (abs($amount) < 0.005) {
            return 'Rp 0';
        }

        return ($amount > 0 ? '+' : '-').' '.self::format(abs($amount));
    }

    public static function formatPercent(float $value, int $decimals = 1): string
    {
        return number_format($value, $decimals, ',', '.').'%';
    }

    /**
     * Ubah input "15.000" / "15000" / "Rp 15.000" menjadi float.
     *
     * Catatan: string berpoin seperti "200.000" TIDAK boleh lewat is_numeric()
     * dulu, karena PHP membacanya sebagai float 200.0 (desimal), bukan 200000.
     * Karena itu baru digit-nya yang dibersihkan.
     */
    public static function parse(string|int|float|null $value): float
    {
        if (is_int($value) || is_float($value)) {
            return self::round($value);
        }

        $raw = trim((string) $value);

        // Tanpa pemisah ribuan dan tanpa koma desimal: aman dif-cast langsung.
        if ($raw !== '' && preg_match('/^\d+$/', $raw) === 1) {
            return self::round((float) $raw);
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);

        return self::round($digits === '' ? 0 : (float) $digits);
    }
}
