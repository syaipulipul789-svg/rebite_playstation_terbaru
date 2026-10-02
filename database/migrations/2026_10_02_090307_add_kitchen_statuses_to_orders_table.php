<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah status dapur untuk pesanan QR meja.
 *
 * Alur pemesanan mandiri: PENDING (draft di HP pelanggan) -> PREPARING
 * (sudah masuk ke kasir, menunggu dimasak) -> SERVED (sudah diantar).
 * Payment belum terjadi di sini — pembayaran tetap satu struk saat rental
 * di-checkout, jadi SERVED masih dianggap belum dibayar.
 *
 * `PLACED` sengaja dipertahankan: itu status alur lama pemesanan lewat scan
 * barcode, dan masih dipakai halaman POS.
 *
 * Di SQLite kolom enum disimpan sebagai CHECK constraint di level tabel, jadi
 * daftar nilainya harus ditulis ulang di sini.
 */
return new class extends Migration
{
    /** Status yang dipakai untuk alur pemesanan mandiri via QR meja. */
    private const KITCHEN_FLOW = [
        'PENDING',
        'PLACED',
        'PREPARING',
        'SERVED',
        'COMPLETED',
        'CANCELLED',
    ];

    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', self::KITCHEN_FLOW)->default('PENDING')->change();
        });
    }

    public function down(): void
    {
        // Persempit daftar status harus Scopus baris yang nilainya sudah keluar
        // dari daftar lama, kalau tidak constraint SQLite akan menolak saat
        // tabel dibangun ulang. PREPARING/SERVED dipetakan ke PLACED karena
        // sama-sama "sudah dikirim pelanggan, belum dibayar".
        DB::table('orders')
            ->whereIn('status', ['PREPARING', 'SERVED'])
            ->update(['status' => 'PLACED']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'PLACED', 'COMPLETED', 'CANCELLED'])->default('PENDING')->change();
        });
    }
};
