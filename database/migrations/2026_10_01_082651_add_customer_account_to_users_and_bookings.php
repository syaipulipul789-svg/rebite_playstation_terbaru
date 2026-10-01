<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nomor WhatsApp jadi identitas utama akun pelanggan. Nullable
            // karena owner/kasir tetap login pakai `username`.
            $table->string('phone', 30)->nullable()->unique()->after('username');
        });

        // Kolom `role` dibuat sebagai enum MySQL, jadi nilai barunya harus
        // ditambah lewat ALTER — nilai enum tidak bisa disisipkan dari PHP.
        // Index di drop lebih dulu karena `change()` akan menerbitkannya lagi
        // sebagai `add index`, dan MySQL menolak nama index yang sama.
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['OWNER', 'KASIR', 'CUSTOMER'])
                ->default('KASIR')
                ->index()
                ->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            // Nullable: booking lama yang sudah ada sebelum fitur akun
            // pelanggan tidak punya pemilik akun.
            $table->foreignId('user_id')->nullable()->after('console_id')
                ->constrained('users')->nullOnDelete();
        });

        $this->backfillBookingOwners();
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
        });

        // Menyingkatkan enum MySQL hanya berhasil kalau tidak ada baris yang
        // memakai nilai yang akan dihapus. Melempar exception lebih baik
        // daripada diam-diam mengubah akun pelanggan jadi KASIR.
        if (DB::table('users')->where('role', 'CUSTOMER')->exists()) {
            throw new RuntimeException('Rollback ditolak: masih ada akun pelanggan (role CUSTOMER). Hapus akun tersebut lebih dulu.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['OWNER', 'KASIR'])
                ->default('KASIR')
                ->index()
                ->change();
        });
    }

    /**
     * Sambungkan booking yang sudah ada ke akun pelanggan dengan cara
     * menormalkan nomor, karena `bookings.customer_phone` disimpan apa adanya
     * sesuai ketikan pelanggan ("+62 812-3456-7890") sementara
     * `users.phone` selalu bentuk lokal ("081234567890").
     *
     * Lewati bila tabel `users` belum ada (mis. saat migrate dari nol).
     */
    private function backfillBookingOwners(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('bookings', 'user_id')) {
            return;
        }

        $ownersByPhone = DB::table('users')
            ->whereNotNull('phone')
            ->pluck('id', 'phone')
            ->all();

        if ($ownersByPhone === []) {
            return;
        }

        DB::table('bookings')
            ->whereNull('user_id')
            ->orderBy('id')
            ->each(function (object $booking) use ($ownersByPhone): void {
                $ownerId = $ownersByPhone[Phone::normalize($booking->customer_phone)] ?? null;

                if ($ownerId !== null) {
                    DB::table('bookings')->where('id', $booking->id)->update(['user_id' => $ownerId]);
                }
            });
    }
};
