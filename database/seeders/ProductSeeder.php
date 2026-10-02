<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Menu makanan & minuman yang dijual di outlet.
 *
 * Harga ditulis per item, bukan per kategori, supaya satu kategori bisa campur
 * harga — misalnya Toping yang isinya Telor Rebus 3.000 dan Telor Ceplok 3.500.
 *
 * Kunci `updateOrCreate` memakai `name` + `category`, bukan `name` saja. Satu
 * nama sengaja muncul di lebih dari satu kategori ("Indomie Rendang" ada di
 * Indomie Goreng 6.000 dan di Indomie Jumbo 7.000). Kalau kuncinya cuma
 * `name`, entri kedua akan menimpa entri pertama dan salah satu varian hilang.
 */
class ProductSeeder extends Seeder
{
    /** Stok awal untuk semua item. */
    private const STOCK = 30;

    /** Batas stok menipis; dipakai halaman Owner sebagai penanda "habis". */
    private const LOW_STOCK_THRESHOLD = 5;

    public function run(): void
    {
        foreach ($this->menu() as $group) {
            foreach ($group['items'] as $name => $price) {
                Product::query()->updateOrCreate(
                    ['name' => $name, 'category' => $group['category']->value],
                    [
                        'price' => $price,
                        'stock' => self::STOCK,
                        'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /**
     * Menu per kategori, ditulis sebagai pasangan kategori + isi menu supaya
     * tiap kategori bisa punya harga sendiri-sendiri di dalam itemnya.
     *
     * @return list<array{category: ProductCategory, items: array<string, int>}>
     */
    private function menu(): array
    {
        return [
            [
                'category' => ProductCategory::INDOMIE_GORENG,
                'items' => [
                    'Indomie Original' => 6000,
                    'Indomie Rendang' => 6000,
                    'Indomie Aceh' => 6000,
                    'Indomie Iga Penyet' => 6000,
                    'Indomie Ayam Geprek' => 6000,
                    'Indomie Cabe Ijo' => 6000,
                ],
            ],
            [
                'category' => ProductCategory::INDOMIE_JUMBO,
                'items' => [
                    'Indomie Rendang' => 7000,
                    'Indomie Original' => 7000,
                    'Indomie Ayam Panggang' => 7000,
                ],
            ],
            [
                'category' => ProductCategory::INDOMIE_REBUS,
                'items' => [
                    'Indomie Ayam Bawang' => 6000,
                    'Indomie Kari Ayam' => 6000,
                    'Indomie Rawon' => 6000,
                    'Indomie Seblak Hot' => 6000,
                    'Indomie Soto' => 6000,
                ],
            ],
            [
                'category' => ProductCategory::SUKSES_GORENG,
                'items' => [
                    'Sukses Aceh' => 8000,
                    'Sukses Rendang' => 8000,
                    'Sukses Ayam Geprek' => 8000,
                    'Sukses Ayam Kremes' => 8000,
                ],
            ],
            [
                'category' => ProductCategory::TOPING,
                'items' => [
                    'Telor Rebus' => 3000,
                    'Telor Ceplok' => 3500,
                    'Nasi' => 3000,
                ],
            ],
            [
                'category' => ProductCategory::GOOD_DAY,
                'items' => [
                    'Cappucino' => 5000,
                    'Frezze' => 5000,
                    'Mochacino' => 5000,
                    'Coolin' => 5000,
                    'Chococino' => 5000,
                    'Carribean' => 5000,
                    'Original' => 5000,
                    'Latte' => 5000,
                ],
            ],
            [
                'category' => ProductCategory::COFFEE,
                'items' => [
                    'Kapal Api Spesial Mix' => 5000,
                    'White Coffe Hot' => 5000,
                    'White Coffe Tarik Malaka Ice' => 5000,
                    'Indocafe Coffeemix' => 5000,
                    'Kapal Api Non Sugar' => 3000,
                    'ABC Klepon' => 5000,
                    'ABC Kopi Susu' => 5000,
                    'Kopi Gula Aren' => 5000,
                ],
            ],
            [
                'category' => ProductCategory::TEA,
                'items' => [
                    'Tea' => 3000,
                    'Teh Tarik' => 5000,
                    'Lychee Tea' => 6000,
                ],
            ],
            [
                'category' => ProductCategory::SWEET_DRINKS,
                'items' => [
                    'Susu Coklat/Putih' => 5000,
                    'Creamy Matcha Latte' => 5000,
                    'Beng Beng Drink' => 5000,
                    'Nutrisari Mangga' => 5000,
                    'Nutrisari Jeruk' => 5000,
                    'Taro' => 7000,
                    'Red Velvet' => 7000,
                    'Jahe Susu Sido Muncul' => 5000,
                ],
            ],
        ];
    }
}
