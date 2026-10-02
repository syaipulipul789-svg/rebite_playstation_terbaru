<?php

namespace Database\Seeders;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Unit konsol yang dipakai di outlet.
 *
 * Jumlah unit per tipe dibuat dari daftar `$types` di bawah, bukan ditulis satu
 * per satu, supaya menambah atau mengurangi jumlah konsol cukup mengubah satu
 * angka. Kode unit (`PS3-01`, `PS4-01`, `PB-01`, ...) adalah kunci unik di DB
 * sekaligus kode yang kasir pindai, jadi formatnya tidak boleh diubah tanpa
 * pemberitahuan ke tim kasir dulu.
 */
class UnitSeeder extends Seeder
{
    /**
     * Kode unit dari versi seeder lama. Sengaja dibersihkan supaya migrasi ke
     * daftar konsol baru tidak meninggalkan unit yatim.
     *
     * Hanya kode unit lama yang disebut eksplisit di sini — bukan "hapus semua
     * yang tidak ada di daftar", karena unit yang ditambahkan sendiri lewat
     * halaman Owner akan ikut terhapus kalau pakai pendekatan terakhir.
     */
    private const LEGACY_CODES = [
        'PS5-01', 'PS5-02', 'PS5-03',
        'VIP-01', 'VIP-02', 'VIP-03',
        'OP-01', 'OP-02',
    ];

    public function run(): void
    {
        foreach ($this->types() as $type) {
            for ($number = 1; $number <= $type['count']; $number++) {
                $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);

                Unit::query()->updateOrCreate(
                    ['code' => $type['code_prefix'].'-'.$suffix],
                    [
                        'name' => $type['name'].' '.$suffix,
                        'type' => $type['type'],
                        'hourly_rate' => $type['hourly_rate'],
                        'status' => UnitStatus::READY,
                        'location' => $type['location'],
                        'notes' => null,
                    ],
                );
            }
        }

        $this->purgeLegacyUnits();
    }

    /**
     * @return list<array{code_prefix: string, name: string, type: string, hourly_rate: int, count: int, location: string}>
     */
    private function types(): array
    {
        return [
            [
                'code_prefix' => 'PS3',
                'name' => 'PS3 Unit',
                'type' => 'PS3',
                'hourly_rate' => 6000,
                'count' => 26,
                'location' => 'Lantai 1',
            ],
            [
                'code_prefix' => 'PS4',
                'name' => 'PS4 Unit',
                'type' => 'PS4',
                'hourly_rate' => 8000,
                'count' => 5,
                'location' => 'Lantai 1',
            ],
            [
                'code_prefix' => 'PB',
                'name' => 'Playbox',
                'type' => 'PLAYBOX',
                'hourly_rate' => 10000,
                'count' => 11,
                'location' => 'Lantai 2',
            ],
        ];
    }

    /**
     * Buang unit konsol lama yang sudah tidak dipakai di outlet.
     *
     * `rental_sessions.unit_id` dan `bookings.console_id` cascade on delete, jadi
     * riwayat sewa unit yang dihapus ikut hilang. Untuk data demo itu memang
     * diinginkan (DemoHistorySeeder dibuat ulang setelahnya), tapi untuk data
     * produksi/unit yang sudah pernah dipakai, hapus manual lewat halaman Owner
     * lebih aman supaya riwayatnya tetap ada.
     */
    private function purgeLegacyUnits(): void
    {
        $deleted = Unit::query()
            ->whereIn('code', self::LEGACY_CODES)
            ->delete();

        if ($deleted > 0) {
            $this->command?->warn($deleted.' unit konsol lama dihapus (riwayat sewanya ikut terhapus).');
        }
    }
}
