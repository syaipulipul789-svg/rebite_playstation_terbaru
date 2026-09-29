<?php

namespace App\Enums;

enum UnitStatus: string
{
    case READY = 'READY';
    case BUSY = 'BUSY';
    case MAINTENANCE = 'MAINTENANCE';

    public function label(): string
    {
        return match ($this) {
            self::READY => 'Ready',
            self::BUSY => 'Main',
            self::MAINTENANCE => 'Servis',
        };
    }

    /**
     * Warna indikator card pada grid monitoring unit.
     */
    public function indicator(): array
    {
        return match ($this) {
            self::READY => [
                'ring' => 'ring-emerald-500/40',
                'dot' => 'bg-emerald-400',
                'glow' => 'shadow-emerald-500/10',
                'text' => 'text-emerald-300',
                'badge' => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
            ],
            self::BUSY => [
                'ring' => 'ring-rose-500/40',
                'dot' => 'bg-rose-400',
                'glow' => 'shadow-rose-500/10',
                'text' => 'text-rose-300',
                'badge' => 'bg-rose-500/15 text-rose-300 ring-1 ring-inset ring-rose-500/30',
            ],
            self::MAINTENANCE => [
                'ring' => 'ring-amber-500/40',
                'dot' => 'bg-amber-400',
                'glow' => 'shadow-amber-500/10',
                'text' => 'text-amber-300',
                'badge' => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
            ],
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

    /**
     * Varian warna untuk komponen <x-badge>.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::READY => 'emerald',
            self::BUSY => 'rose',
            self::MAINTENANCE => 'amber',
        };
    }
}
