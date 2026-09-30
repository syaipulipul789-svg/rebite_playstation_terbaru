<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu',
            self::CONFIRMED => 'Dikonfirmasi',
            self::CANCELLED => 'Dibatalkan',
            self::COMPLETED => 'Selesai',
        };
    }

    /**
     * Label yang dilihat pelanggan di halaman publik.
     *
     * Sengaja dibedakan dari `label()` (dipakai kasir): pelanggan perlu tahu
     * apakah booking-nya sudah *berjalan* atau baru disetujui untuk jadwal
     * nanti, jadi "Terisi" hanya muncul saat sesinya benar-benar berjalan.
     */
    public function customerLabel(bool $isOccupied): string
    {
        return match (true) {
            $this === self::PENDING => 'Menunggu Persetujuan',
            $this === self::CONFIRMED && $isOccupied => 'Sudah Terisi',
            $this === self::CONFIRMED => 'Disetujui',
            $this === self::CANCELLED => 'Dibatalkan',
            default => 'Selesai',
        };
    }

    public function badgeClass(bool $isOccupied = false): string
    {
        return match (true) {
            $this === self::PENDING => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
            $this === self::CONFIRMED && $isOccupied => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
            $this === self::CONFIRMED => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
            $this === self::CANCELLED => 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30',
            default => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
        };
    }

    /**
     * Keterangan singkat yang menjelaskan langkah berikutnya ke pelanggan.
     */
    public function customerHint(bool $isOccupied): string
    {
        return match (true) {
            $this === self::PENDING => 'Kasir akan memverifikasi booking ini. Status berubah otomatis setelah disetujui.',
            $this === self::CONFIRMED && $isOccupied => 'Unit sedang dipakai sesuai jadwalmu. Tunjukkan kode booking ke kasir.',
            $this === self::CONFIRMED => 'Booking disetujui dan unit diamankan untuk jadwalmu. Sesi berjalan otomatis saat jam mulai.',
            $this === self::CANCELLED => 'Booking ini dibatalkan oleh kasir. Silakan buat booking baru bila masih ingin bermain.',
            default => 'Sesi selesai. Terima kasih sudah bermain di Rebite Playstation.',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->all();
    }
}
