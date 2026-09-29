<?php

namespace App\Enums;

enum ProductCategory: string
{
    case SNACK = 'SNACK';
    case DRINK = 'DRINK';
    case EXTRA = 'EXTRA';

    public function label(): string
    {
        return match ($this) {
            self::SNACK => 'Snack',
            self::DRINK => 'Minuman',
            self::EXTRA => 'Extra Time',
        };
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
