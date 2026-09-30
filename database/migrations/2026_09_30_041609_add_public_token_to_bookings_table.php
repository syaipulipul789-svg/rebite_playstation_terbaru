<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->after('id');
        });

        // Booking lama dibuat sebelum token ada, jadi harus diberi token agar
        // halaman status pelanggan tidak gagal diam-diam.
        Booking::query()->whereNull('public_token')->eachById(
            fn (Booking $booking) => $booking->forceFill([
                'public_token' => (string) Str::uuid(),
            ])->saveQuietly()
        );

        Schema::table('bookings', function (Blueprint $table) {
            $table->unique('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
