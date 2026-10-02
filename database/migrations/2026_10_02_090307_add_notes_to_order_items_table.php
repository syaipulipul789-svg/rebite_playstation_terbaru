<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan per baris pesanan, mis. "Pedas 3" atau "Telor Ceplok".
 *
 * Catatan harus menempel di baris item, bukan di `orders.notes` (catatan
 * tingkat pesanan), karena tiap item bisa punya permintaan berbeda dan kasir
 * hanya perlu membaca catatan di samping item yang relevan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('notes', 120)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
