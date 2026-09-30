<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Pelanggan masih menambahkan item lewat scan barcode. */
    case PENDING = 'PENDING';

    /** Sudah dikirim pelanggan, menunggu dibayar kasir. */
    case PLACED = 'PLACED';

    /** Sudah dibayar kasir dan masuk rekap shift. */
    case COMPLETED = 'COMPLETED';

    /** Dibatalkan kasir, stok dikembalikan. */
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Draft',
            self::PLACED => 'Menunggu Bayar',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
            self::PLACED => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
            self::COMPLETED => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
            self::CANCELLED => 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30',
        };
    }

    /**
     * Order hanya bisa diubah itemnya selama belum dikirim ke kasir.
     */
    public function isEditable(): bool
    {
        return $this === self::PENDING;
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
