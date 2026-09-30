<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class PromoteDueBookings extends Command
{
    protected $signature = 'bookings:promote';

    protected $description = 'Ubah booking yang sudah dikonfirmasi dan jam mulainya lewat menjadi sesi berjalan (unit Terisi)';

    public function handle(BookingService $bookings): int
    {
        $promoted = $bookings->promoteDueBookings();

        if ($promoted === 0) {
            warning('Tidak ada booking yang perlu di-promote.');

            return self::SUCCESS;
        }

        info(sprintf('%d booking dipromote menjadi sesi berjalan.', $promoted));

        return self::SUCCESS;
    }
}
