<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_column(ProductCategory::cases(), 'value'))],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
            'category.required' => 'Kategori wajib dipilih.',
            'price.required' => 'Harga wajib diisi.',
            'stock.required' => 'Stok wajib diisi.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'category' => 'kategori',
            'price' => 'harga',
            'stock' => 'stok',
            'low_stock_threshold' => 'batas stok menipis',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
