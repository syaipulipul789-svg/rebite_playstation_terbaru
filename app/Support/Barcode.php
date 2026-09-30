<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Barcode
{
    /**
     * Prefix internal 899 — rentang GS1 unofficial, aman dipakai untuk
     * barcode toko sendiri tanpa interferes dengan produk bersertifikat.
     */
    private const PREFIX = '899';

    /**
     * Hasilkan barcode EAN-13 numeric 13 digit yang stabil & unik untuk satu
     * produk. Memakai id produk supaya kodenya bisa dibaca kembali tanpa
     * perlu tabel tambahan.
     */
    public static function generateFor(int $productId): string
    {
        $body = str_pad((string) $productId, 9, '0', STR_PAD_LEFT);

        $partial = self::PREFIX.$body;

        return $partial.self::checkDigit($partial);
    }

    /**
     * Check digit EAN-13: jumlah digit pada posisi ganjil dikali 3,-even
     * dikali 1, jumlah modulo 10, lalu compliment sampai kelipatan 10.
     */
    private static function checkDigit(string $first12): string
    {
        $sum = 0;

        foreach (str_split($first12) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    /**
     * Bersihkan hasil scan sebelum dicocokkan ke database: hanya karakter
     * alphanumeric, huruf dipaksa kapital.
     *
     * Scan QR/barcode kadang membawa spasi, enter, atau sufiks "http",
     * jadi normalisasi di satu tempat supaya semua pemanggil konsisten.
     */
    public static function normalize(string|int|null $value): string
    {
        $raw = Str::upper(trim((string) $value));

        return (string) preg_replace('/[^A-Z0-9]/', '', $raw);
    }
}
