<?php

namespace App\Http\Requests;

use App\Support\Barcode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Satu hasil pindai barcode dari kamera / scanner. Barcode TIDAK dicek
 * ke tabel products di sini — pengecekan Existence + status produk +
 * stok dilakukan di OrderService supaya satu sumber kebenaran.
 */
class ScanBarcodeRequest extends FormRequest
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
            'barcode' => ['required', 'string', 'max:64'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'barcode.required' => 'Barcode tidak terbaca. Arahkan kamera ke label produk.',
            'barcode.max' => 'Barcode terlalu panjang.',
            'qty.min' => 'Jumlah minimal 1.',
            'qty.max' => 'Jumlah maksimal 99 per pindai.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'barcode' => 'barcode',
            'qty' => 'jumlah',
        ];
    }

    protected function prepareForValidation(): void
    {
        $barcode = Barcode::normalize($this->input('barcode'));

        $this->merge([
            'barcode' => $barcode,
            'qty' => max(1, (int) $this->input('qty', 1)),
        ]);
    }
}
