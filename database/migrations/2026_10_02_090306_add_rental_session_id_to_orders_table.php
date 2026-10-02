<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pesanan pemesanan mandiri lewat QR meja ditautkan ke sesi sewa yang sedang
 * berjalan, supaya:
 * - kasir tahu pesanan itu milik sesi/unit mana,
 * - pembayaran rental + makanan digabung jadi satu struk saat checkout.
 *
 * `nullOnDelete` dipakai, bukan cascade: sesi yang dihapus karena dibatalkan
 * tidak boleh ikut menghapus bukti pesanan (dan uang yang sudah masuk rekap
 * shift). Setelah sesi hilang, pesanan tetap bisa dibaca kasir lewat POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('rental_session_id')
                ->nullable()
                ->after('booking_id')
                ->constrained()
                ->nullOnDelete();

            // Pesanan per sesi diambil berulang kali saat checkout & polling.
            $table->index(['rental_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['rental_session_id', 'status']);
            $table->dropConstrainedForeignId('rental_session_id');
        });
    }
};
