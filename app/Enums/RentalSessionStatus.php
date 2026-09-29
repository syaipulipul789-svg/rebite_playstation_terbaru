<?php

namespace App\Enums;

enum RentalSessionStatus: string
{
    case RUNNING = 'RUNNING';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'Berjalan',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::RUNNING => 'bg-rose-500/15 text-rose-300 ring-1 ring-inset ring-rose-500/30',
            self::COMPLETED => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
            self::CANCELLED => 'bg-slate-500/15 text-slate-400 ring-1 ring-inset ring-slate-500/30',
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
