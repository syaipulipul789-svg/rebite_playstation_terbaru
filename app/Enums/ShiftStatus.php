<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Berjalan',
            self::CLOSED => 'Ditutup',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OPEN => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
            self::CLOSED => 'bg-slate-500/15 text-slate-300 ring-1 ring-inset ring-slate-500/30',
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
