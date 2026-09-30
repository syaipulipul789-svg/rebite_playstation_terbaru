<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah jumlah item di keranjang. qty 0 berarti hapus baris dan
 * kembalikan stoknya (divalidasi & dieksekusi OrderService).
 */
class UpdateOrderItemQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'qty.required' => 'Jumlah wajib diisi.',
            'qty.integer' => 'Jumlah harus berupa angka.',
            'qty.max' => 'Jumlah maksimal 99.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'qty' => 'jumlah',
        ];
    }
}
