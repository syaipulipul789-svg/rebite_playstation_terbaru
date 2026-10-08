<?php

namespace App\Enums;

enum RentalRequestStatus: string
{
    case PENDING = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
    case COMPLETED = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Konfirmasi',
            self::CONFIRMED => 'Terkonfirmasi',
            self::CANCELLED => 'Dibatalkan',
            self::COMPLETED => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/20 text-amber-300 ring-1 ring-inset ring-amber-500/40',
            self::CONFIRMED => 'bg-emerald-500/20 text-emerald-300 ring-1 ring-inset ring-emerald-500/40',
            self::CANCELLED => 'bg-rose-500/20 text-rose-300 ring-1 ring-inset ring-rose-500/40',
            self::COMPLETED => 'bg-slate-500/20 text-slate-300 ring-1 ring-inset ring-slate-500/40',
        };
    }
}
