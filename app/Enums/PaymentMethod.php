<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case QRIS = 'QRIS';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Tunai',
            self::QRIS => 'QRIS',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CASH => 'bg-amber-500/15 text-amber-300 ring-1 ring-inset ring-amber-500/30',
            self::QRIS => 'bg-sky-500/15 text-sky-300 ring-1 ring-inset ring-sky-500/30',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method) => [$method->value => $method->label()])
            ->all();
    }
}
