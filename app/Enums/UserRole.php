<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'OWNER';
    case KASIR = 'KASIR';
    case CUSTOMER = 'CUSTOMER';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::KASIR => 'Kasir',
            self::CUSTOMER => 'Pelanggan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::OWNER => 'bg-violet-500/15 text-violet-300 ring-1 ring-inset ring-violet-500/30',
            self::KASIR => 'bg-cyan-500/15 text-cyan-300 ring-1 ring-inset ring-cyan-500/30',
            self::CUSTOMER => 'bg-emerald-500/15 text-emerald-300 ring-1 ring-inset ring-emerald-500/30',
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

    public function isCustomer(): bool
    {
        return $this === self::CUSTOMER;
    }

    /**
     * Role yang boleh masuk ke area internal (grid unit, POS, laporan).
     * Pelanggan sengaja tidak termasuk: ia hanya punya dashboard booking.
     */
    public function isStaff(): bool
    {
        return $this === self::OWNER || $this === self::KASIR;
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
