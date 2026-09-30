<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('confirmed_at')->nullable()->after('status');

            // Kasir yang menyetujui booking. Dipakai untuk mengaitkan sesi
            // rental hasil promote ke shift kasir yang benar.
            $table->foreignId('confirmed_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();

            // Sesi rental yang lahir dari booking ini. Satu booking hanya
            // menghasilkan satu sesi; dipakai supaya kasir yang menutup
            // booking ikut menyelesaikan sesi & membebaskan unit.
            $table->foreignId('rental_session_id')->nullable()->after('confirmed_by')->constrained('rental_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['rental_session_id']);
            $table->dropForeign(['confirmed_by']);
            $table->dropColumn(['rental_session_id', 'confirmed_by', 'confirmed_at']);
        });
    }
};
