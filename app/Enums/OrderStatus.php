<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Draft: pelanggan masih menambahkan item di HP-nya. */
    case PENDING = 'PENDING';

    /** Sudah dikirim lewat alur scan barcode, menunggu dibayar kasir. */
    case PLACED = 'PLACED';

    /** Sudah masuk ke kasir dari QR meja, menunggu dimasak. */
    case PREPARING = 'PREPARING';

    /** Sudah diantar ke meja, belum dibayar. */
    case SERVED = 'SERVED';

    /** Sudah dibayar kasir dan masuk rekap shift. */
    case COMPLETED = 'COMPLETED';

    /** Dibatalkan kasir/pelanggan, stok dikembalikan. */
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Draft',
            self::PLACED => 'Menunggu Bayar',
            self::PREPARING => 'Sedang Dimasak',
            self::SERVED => 'Sudah Diantar',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
            self::PLACED => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
            self::PREPARING => 'bg-orange-500/15 text-orange-300 ring-1 ring-inset ring-orange-500/30',
            self::SERVED => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
            self::COMPLETED => 'bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-500/30',
            self::CANCELLED => 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30',
        };
    }

    /**
     * Pelanggan masih boleh mengubah isi pesanan.
     *
     * Hanya `PENDING`. Begitu pesanan sudah masuk ke kasir, item terkunci:
     * kalau masih bisa diedit, kasir bisa saja sudah mulai menyiapkan
     * sebagian barang yang lalu berubah jumlah atau dibatalkan.
     */
    public function isEditable(): bool
    {
        return $this === self::PENDING;
    }

    /** Sudah masuk ke dapur dan menunggu dimasak. */
    public function isPreparing(): bool
    {
        return $this === self::PREPARING;
    }

    /** Sudah diantar, jadi kasir tinggal menunggu pembayaran. */
    public function isServed(): bool
    {
        return $this === self::SERVED;
    }

    public function isPaid(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    /**
     * Pesanan yang masih harus ikut ditagihkan.
     *
     * Yang batal tidak dihitung; sisanya — baik yang baru dikirim, sudah
     * diantar, atau dibayar terpisah — tetap sah untuk dibayar saat rental
     * di-checkout.
     */
    public function isBillable(): bool
    {
        return $this !== self::CANCELLED;
    }

    /**
     * Pesanan yang masih jadi pekerjaan dapur / kasir, bukan arsip.
     */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::PENDING, self::PLACED, self::PREPARING, self::SERVED], true);
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
