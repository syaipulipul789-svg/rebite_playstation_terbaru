<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class StartShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCashier() ?? false;
    }

    public function rules(): array
    {
        return [
            'starting_cash' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'starting_cash.required' => 'Modal awal kas wajib diisi.',
            'starting_cash.numeric' => 'Modal awal kas harus berupa angka.',
            'starting_cash.min' => 'Modal awal kas tidak boleh negatif.',
        ];
    }

    /**
     * Kasir mengetik "200.000" di keypad. Angka harus dinormalisasi SEBELUM
     * aturan "numeric" dievaluasi — karena itu parsing berada di
     * prepareForValidation(), bukan withValidator().
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('starting_cash')) {
            $this->merge([
                'starting_cash' => $this->filled('starting_cash')
                    ? Money::parse($this->input('starting_cash'))
                    : null,
            ]);
        }
    }
}
