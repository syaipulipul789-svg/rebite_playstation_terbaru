<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Booking online yang sudah dikonfirmasi kasir harus otomatis berubah jadi
// sesi berjalan (unit "Terisi") begitu jam mulainya lewat — tanpa perlu
// kasir menekan tombol apa pun.
Schedule::command('bookings:promote')->everyMinute()->withoutOverlapping();
