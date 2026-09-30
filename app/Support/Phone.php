<?php

namespace App\Support;

final class Phone
{
    /**
     * Samakan berbagai cara penulisan nomor jadi satu bentuk lokal.
     *
     * Kolom `bookings.customer_phone` disimpan apa adanya sesuai yang diketik
     * pelanggan, jadi perbandingan harus dinormalkan di kedua sisi:
     * "+62 812-3456-7890", "6281234567890", dan "081234567890" adalah nomor
     * yang sama.
     */
    public static function normalize(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        // Buang awalan negara lalu kembalikan ke format lokal 0...
        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    /**
     * @return list<string> Semua varian yang mungkin tersimpan di database
     *                      untuk nomor yang sama, supaya perbandingan tetap
     *                      cocok meski penulisan pelanggan berbeda.
     */
    public static function variants(?string $phone): array
    {
        $local = self::normalize($phone);

        if ($local === '') {
            return [];
        }

        $national = substr($local, 1);

        return array_values(array_unique([
            $local,
            '62'.$national,
            '+62'.$national,
            $national,
        ]));
    }

    /**
     * Nomor tujuan untuk tautan `wa.me`, yaitu format internasional tanpa
     * tanda "+" dan tanpa nol di depan: "081234567890" -> "628123456789".
     *
     * Mengembalikan `null` kalau nomornya bukan nomor seluler Indonesia yang
     * masuk akal (mis. "ADWD" atau "w2121easad"), supaya pemanggil tidak
     * membuka chat WhatsApp yang dijamin gagal.
     */
    public static function whatsappNumber(?string $phone): ?string
    {
        $local = self::normalize($phone);

        // Nomor seluler Indonesia: 08xx / 09xx, total 9-13 digit.
        if (preg_match('/^0[89]\d{7,11}$/', $local) !== 1) {
            return null;
        }

        return '62'.substr($local, 1);
    }
}
