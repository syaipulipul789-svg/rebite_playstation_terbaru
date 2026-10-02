<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * QR code meja untuk pemesanan mandiri.
 *
 * Isi QR-nya hanya URL menu unit, misalnya `http://rebite.test/m/PS3-01`.
 *
 * Tidak ada token rahasia di dalamnya. booked orang lain tetap bisa
 * memindai, tapi halaman `/m/{unit_code}` selalu cek apakah unit itu punya
 * sesi sewa aktif sebelum menampilkan menu — jadi QR yang hilang dari meja
 * tidak cukup untuk memesan. Token rahasia justru diletakkan di URL halaman
 * sukses pesanan, yang hanya diketahui pelanggan yang sedang memesan.
 */
final class TableQr
{
    /** Prefix URL pemesanan mandiri, sesuai QR yang dicetak di meja. */
    private const PATH_PREFIX = '/m/';

    /**
     * Kode unit dinormalisasi agar URL yang diketik manual (huruf kecil,
     * spasi sisa) tetap ketemu unit aslinya yang kodenya huruf besar.
     */
    public static function normalizeCode(string $code): string
    {
        return Str::upper(trim($code));
    }

    public static function pathFor(string $unitCode): string
    {
        return self::PATH_PREFIX.rawurlencode(self::normalizeCode($unitCode));
    }

    /**
     * URL absolut yang dipindai pelanggan. APP_URL dipakai supaya QR tetap
     * benar saat diakses dari HP yang terhubung ke jaringan lokal, bukan lewat
     * nama host dev.
     */
    public static function urlFor(string $unitCode): string
    {
        return url(self::pathFor($unitCode));
    }

    /**
     * Render QR sebagai SVG (bukan PNG) supaya tetap tajam di ukuran kertas
     * apa pun dan tidak butuh Imagick yang tidak selalu terpasang.
     */
    public static function svg(string $payload, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
