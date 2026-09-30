<?php

namespace App\Models;

use App\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'barcode',
        'category',
        'price',
        'stock',
        'low_stock_threshold',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'price' => 'decimal:2',
            'stock' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sessionItems(): HasMany
    {
        return $this->hasMany(SessionItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeWithBarcode(Builder $query): Builder
    {
        return $query->whereNotNull('barcode')->where('barcode', '!=', '');
    }

    /**
     * Produk yang boleh dipindai pelanggan: aktif, punya barcode, dan stok
     * masih ada. Barcode dinormalisasi ke huruf kapital supaya hasil scan
     * tidak_case sensitive.
     */
    public function scopeScannable(Builder $query): Builder
    {
        return $query->active()
            ->whereNotNull('barcode')
            ->where('barcode', '!=', '')
            ->inStock();
    }

    public function hasBarcode(): bool
    {
        return filled($this->barcode);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->low_stock_threshold;
    }
}
