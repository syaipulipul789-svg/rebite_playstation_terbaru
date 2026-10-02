<?php

namespace App\Enums;

enum ProductCategory: string
{
    // Kategori generik, dipakai untuk produk di luar menu F&B utama.
    case SNACK = 'SNACK';
    case DRINK = 'DRINK';
    case EXTRA = 'EXTRA';

    // Makanan.
    case INDOMIE_GORENG = 'INDOMIE_GORENG';
    case INDOMIE_JUMBO = 'INDOMIE_JUMBO';
    case INDOMIE_REBUS = 'INDOMIE_REBUS';
    case SUKSES_GORENG = 'SUKSES_GORENG';
    case TOPING = 'TOPING';

    // Minuman.
    case GOOD_DAY = 'GOOD_DAY';
    case COFFEE = 'COFFEE';
    case TEA = 'TEA';
    case SWEET_DRINKS = 'SWEET_DRINKS';

    public function label(): string
    {
        return match ($this) {
            self::SNACK => 'Snack',
            self::DRINK => 'Minuman',
            self::EXTRA => 'Extra Time',
            self::INDOMIE_GORENG => 'Indomie Goreng',
            self::INDOMIE_JUMBO => 'Indomie Jumbo',
            self::INDOMIE_REBUS => 'Indomie Rebus',
            self::SUKSES_GORENG => 'Sukses Goreng Isi 2',
            self::TOPING => 'Toping',
            self::GOOD_DAY => 'Good Day',
            self::COFFEE => 'Coffee',
            self::TEA => 'Tea',
            self::SWEET_DRINKS => 'Sweet Drinks',
        };
    }

    /**
     * Kategori yang ditampilkan sebagai menu F&B di halaman publik.
     *
     * `EXTRA` bukan makanan/minuman, melainkan jam tambahan yang dipilih
     * kasir saat menambah waktu sesi.
     *
     * @return array<int, self>
     */
    public static function menuGroups(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category) => $category !== self::EXTRA,
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category) => [$category->value => $category->label()])
            ->all();
    }
}
