<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Snack
            ['name' => 'Indomie Goreng', 'category' => ProductCategory::SNACK, 'price' => 8000, 'stock' => 40, 'low_stock_threshold' => 10],
            ['name' => 'Indomie Kuah', 'category' => ProductCategory::SNACK, 'price' => 8000, 'stock' => 35, 'low_stock_threshold' => 10],
            ['name' => 'Keripik Kentang Ori', 'category' => ProductCategory::SNACK, 'price' => 10000, 'stock' => 24, 'low_stock_threshold' => 8],
            ['name' => 'Cokelat Batang', 'category' => ProductCategory::SNACK, 'price' => 12000, 'stock' => 18, 'low_stock_threshold' => 6],
            ['name' => 'Roti Bakar Slice', 'category' => ProductCategory::SNACK, 'price' => 7000, 'stock' => 15, 'low_stock_threshold' => 5],
            ['name' => 'Sosis Bakar', 'category' => ProductCategory::SNACK, 'price' => 9000, 'stock' => 4, 'low_stock_threshold' => 6],

            // Minuman
            ['name' => 'Air Mineral 600ml', 'category' => ProductCategory::DRINK, 'price' => 5000, 'stock' => 60, 'low_stock_threshold' => 15],
            ['name' => 'Teh Botol Sosro', 'category' => ProductCategory::DRINK, 'price' => 6000, 'stock' => 45, 'low_stock_threshold' => 12],
            ['name' => 'Kopi Susu Gula Aren', 'category' => ProductCategory::DRINK, 'price' => 15000, 'stock' => 30, 'low_stock_threshold' => 8],
            ['name' => 'Es Teh Manis', 'category' => ProductCategory::DRINK, 'price' => 5000, 'stock' => 50, 'low_stock_threshold' => 12],
            ['name' => 'Cola Kaleng', 'category' => ProductCategory::DRINK, 'price' => 12000, 'stock' => 3, 'low_stock_threshold' => 6],
            ['name' => 'Air Mineral Galon 600ml', 'category' => ProductCategory::DRINK, 'price' => 4000, 'stock' => 80, 'low_stock_threshold' => 20],

            // Extra time
            ['name' => 'Extra Time 30 Menit (PS4)', 'category' => ProductCategory::EXTRA, 'price' => 5000, 'stock' => 999, 'low_stock_threshold' => 0],
            ['name' => 'Extra Time 30 Menit (PS5)', 'category' => ProductCategory::EXTRA, 'price' => 7000, 'stock' => 999, 'low_stock_threshold' => 0],
            ['name' => 'Extra Time 30 Menit (VIP)', 'category' => ProductCategory::EXTRA, 'price' => 12000, 'stock' => 999, 'low_stock_threshold' => 0],
        ];

        foreach ($products as $product) {
            Product::query()->updateOrCreate(
                ['name' => $product['name']],
                [
                    'category' => $product['category'],
                    'price' => $product['price'],
                    'stock' => $product['stock'],
                    'low_stock_threshold' => $product['low_stock_threshold'],
                    'is_active' => true,
                ],
            );
        }
    }
}
