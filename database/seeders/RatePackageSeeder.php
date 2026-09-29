<?php

namespace Database\Seeders;

use App\Models\RatePackage;
use Illuminate\Database\Seeder;

class RatePackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            // Paket global (unit_type null = berlaku untuk semua tipe)
            ['name' => 'Paket 1 Jam', 'unit_type' => null, 'duration_minutes' => 60, 'price' => 8000, 'sort_order' => 1, 'description' => 'Paket standar 1 jam untuk PS4.'],
            ['name' => 'Paket 2 Jam', 'unit_type' => null, 'duration_minutes' => 120, 'price' => 15000, 'sort_order' => 2, 'description' => 'Hemat 1.000 dari tarif normal 2 jam.'],
            ['name' => 'Paket 3 Jam', 'unit_type' => null, 'duration_minutes' => 180, 'price' => 21000, 'sort_order' => 3, 'description' => 'Paket untuk main Longer.'],
            ['name' => 'Paket 5 Jam', 'unit_type' => null, 'duration_minutes' => 300, 'price' => 33000, 'sort_order' => 4, 'description' => 'Paket hemat untuk marathon.'],
            ['name' => 'Paket 10 Jam', 'unit_type' => null, 'duration_minutes' => 600, 'price' => 60000, 'sort_order' => 5, 'description' => 'Paket marathon paling hemat.'],
            ['name' => 'Extra 30 Menit', 'unit_type' => null, 'duration_minutes' => 30, 'price' => 5000, 'sort_order' => 6, 'description' => 'Perpanjangan waktu setelah paket habis.'],
        ];

        foreach ($packages as $package) {
            RatePackage::query()->updateOrCreate(
                ['name' => $package['name']],
                [
                    'unit_type' => $package['unit_type'],
                    'duration_minutes' => $package['duration_minutes'],
                    'price' => $package['price'],
                    'is_active' => true,
                    'sort_order' => $package['sort_order'],
                    'description' => $package['description'],
                ],
            );
        }
    }
}
