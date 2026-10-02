<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu F&B dipisah per jenis (Indomie Goreng/Jumbo/Rebus, Good Day, Coffee, dst)
 * supaya kasir bisa memindai dan memilih satu item yang jelas, bukan daftar
 * snack/minuman generik.
 *
 * Kolom `category` dibuat sebagai enum di migration awal, dan SQLite menyimpan
 * nilai enum itu sebagai CHECK constraint di level tabel. Jadi menambah kasus
 * baru butuh `change()` di sini — di SQLite Laravel membangun ulang tabel,
 * dan constraint lama akan hilang kalau daftar nilainya tidak ditulis ulang.
 *
 * Nilai ditulis persis sama dengan `App\Enums\ProductCategory` supaya CHECK
 * constraint dan enum PHP tidak berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('category', [
                'SNACK',
                'DRINK',
                'EXTRA',
                'INDOMIE_GORENG',
                'INDOMIE_JUMBO',
                'INDOMIE_REBUS',
                'SUKSES_GORENG',
                'TOPING',
                'GOOD_DAY',
                'COFFEE',
                'TEA',
                'SWEET_DRINKS',
            ])->default('SNACK')->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->enum('category', ['SNACK', 'DRINK', 'EXTRA'])->default('SNACK')->change();
        });
    }
};
