<?php

namespace Database\Seeders;

use App\Enums\UnitStatus;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            // PS4 Reguler
            ['code' => 'PS4-01', 'name' => 'PS4 Reguler 01', 'type' => 'PS4', 'hourly_rate' => 8000, 'location' => 'Lantai 1 - Row A'],
            ['code' => 'PS4-02', 'name' => 'PS4 Reguler 02', 'type' => 'PS4', 'hourly_rate' => 8000, 'location' => 'Lantai 1 - Row A'],
            ['code' => 'PS4-03', 'name' => 'PS4 Reguler 03', 'type' => 'PS4', 'hourly_rate' => 8000, 'location' => 'Lantai 1 - Row B'],
            ['code' => 'PS4-04', 'name' => 'PS4 Reguler 04', 'type' => 'PS4', 'hourly_rate' => 8000, 'location' => 'Lantai 1 - Row B'],

            // PS5 Reguler
            ['code' => 'PS5-01', 'name' => 'PS5 Reguler 01', 'type' => 'PS5', 'hourly_rate' => 12000, 'location' => 'Lantai 1 - Row C'],
            ['code' => 'PS5-02', 'name' => 'PS5 Reguler 02', 'type' => 'PS5', 'hourly_rate' => 12000, 'location' => 'Lantai 1 - Row C'],
            ['code' => 'PS5-03', 'name' => 'PS5 Reguler 03', 'type' => 'PS5', 'hourly_rate' => 12000, 'location' => 'Lantai 1 - Row D'],

            // VIP
            ['code' => 'VIP-01', 'name' => 'VIP Room 01', 'type' => 'VIP', 'hourly_rate' => 20000, 'location' => 'Lantai 2 - Kamar A'],
            ['code' => 'VIP-02', 'name' => 'VIP Room 02', 'type' => 'VIP', 'hourly_rate' => 20000, 'location' => 'Lantai 2 - Kamar A'],
            ['code' => 'VIP-03', 'name' => 'VIP Room 03', 'type' => 'VIP', 'hourly_rate' => 20000, 'location' => 'Lantai 2 - Kamar B'],

            // Open Play (gratis)
            ['code' => 'OP-01', 'name' => 'Open Play 01', 'type' => 'OPEN_PLAY', 'hourly_rate' => 0, 'location' => 'Lantai 1 - Area Umum'],
            ['code' => 'OP-02', 'name' => 'Open Play 02', 'type' => 'OPEN_PLAY', 'hourly_rate' => 0, 'location' => 'Lantai 1 - Area Umum'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(
                ['code' => $unit['code']],
                [
                    'name' => $unit['name'],
                    'type' => $unit['type'],
                    'hourly_rate' => $unit['hourly_rate'],
                    'status' => UnitStatus::READY,
                    'location' => $unit['location'],
                ],
            );
        }

        // Satu unit contoh servis supaya kartu Kuning (Maintenance) terlihat.
        Unit::query()->where('code', 'PS4-04')->update([
            'status' => UnitStatus::MAINTENANCE,
            'notes' => 'Joystick kanan drift, sedang overhaul.',
        ]);
    }
}
