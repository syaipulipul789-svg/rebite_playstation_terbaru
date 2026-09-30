<?php

namespace App\Http\Requests;

use App\Enums\ProductCategory;
use App\Support\Barcode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('products', 'barcode')->ignore($this->route('product')?->id),
            ],
            'category' => ['required', Rule::in(array_column(ProductCategory::cases(), 'value'))],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
            'barcode.regex' => 'Barcode hanya boleh berisi huruf dan angka.',
            'barcode.unique' => 'Barcode ini sudah dipakai produk lain.',
            'category.required' => 'Kategori wajib dipilih.',
            'price.required' => 'Harga wajib diisi.',
            'stock.required' => 'Stok wajib diisi.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'barcode' => 'barcode',
            'category' => 'kategori',
            'price' => 'harga',
            'stock' => 'stok',
            'low_stock_threshold' => 'batas stok menipis',
        ];
    }

    protected function prepareForValidation(): void
    {
        $barcode = Barcode::normalize($this->input('barcode'));

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'barcode' => $barcode === '' ? null : $barcode,
        ]);
    }
}
