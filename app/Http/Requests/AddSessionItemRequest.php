<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddSessionItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'qty' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Pilih produk yang ditambahkan.',
            'product_id.exists' => 'Produk tidak tersedia.',
            'qty.required' => 'Jumlah minimal 1.',
            'qty.max' => 'Jumlah maksimal 50 per item.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['qty' => (int) $this->input('qty')]);
    }
}
