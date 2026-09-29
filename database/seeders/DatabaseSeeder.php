<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            UnitSeeder::class,
            RatePackageSeeder::class,
            ProductSeeder::class,
            DemoHistorySeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Rebite Playstation siap dipakai.');
        $this->command?->table(
            ['Role', 'Username', 'Password'],
            [
                ['OWNER', 'owner', 'password'],
                ['KASIR', 'kasir01', 'password'],
                ['KASIR', 'kasir02', 'password'],
            ]
        );
    }
}
