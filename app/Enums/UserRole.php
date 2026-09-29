<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'OWNER';
    case KASIR = 'KASIR';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::KASIR => 'Kasir',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OWNER => 'bg-violet-500/15 text-violet-300 ring-1 ring-inset ring-violet-500/30',
            self::KASIR => 'bg-cyan-500/15 text-cyan-300 ring-1 ring-inset ring-cyan-500/30',
        };
    }

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    public function isCashier(): bool
    {
        return $this === self::KASIR;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
